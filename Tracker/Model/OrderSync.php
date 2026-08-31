<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      theMarketer
 * @license     https://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker\Model;

use Mktr\Tracker\Api;
use Mktr\Tracker\Config;
use Mktr\Tracker\Defer;
use WC_Order;

/**
 * Sends save_order from the server, so an order still reaches theMarketer when the
 * customer never comes back from the payment page, or when it is created without a
 * browser at all (gateway webhook, admin, Store API, WP-CLI).
 *
 * The delivery state lives in its own table rather than in order meta or in a
 * wp_option. Order meta is not usable for it: saving meta fires
 * woocommerce_update_order, which this plugin answers with an update_order_status
 * call, so bookkeeping about a failed send would itself cause API traffic. A single
 * wp_option is not usable either: every hook would have to read, unserialise and
 * rewrite the whole queue, and two parallel requests would lose each other's writes.
 * A row per order gives atomic INSERT ... ON DUPLICATE KEY UPDATE instead.
 */
class OrderSync
{
    /** Bump when the table below changes; checkDb() then applies it on the next request. */
    const DB_VERSION = '1';
    const DB_OPTION = 'mktr_order_sync_db';

    /** Queue left behind by the wp_option implementation this table replaces. */
    const PENDING_OPTION = 'mktr_order_sync_pending';

    const MAX_ATTEMPTS = 10;
    /** How long an undelivered order stays worth retrying. */
    const MAX_AGE = 604800; /* 7 days */
    /** How long a delivered order is remembered, so nothing sends it twice. */
    const KEEP_SENT = 2592000; /* 30 days */
    /** A full batch of timing-out sends would otherwise outlast max_execution_time. */
    const MAX_RUNTIME = 20;
    /** Longer than the API timeout, while still allowing a retry after a crashed request. */
    const LOCK_TTL = 300;

    private static $done = array();
    private static $queued = array();

    public static function tableName()
    {
        return Config::db()->prefix . 'mktr_order_sync';
    }

    public static function isEnabled()
    {
        return Config::getOnboarding() === 2 && Config::getStatus() === 1
            && !empty(Config::getRestKey()) && !empty(Config::getCustomerId());
    }

    /* ---------------------------------------------------------------- schema */

    public static function up()
    {
        $table = self::tableName();
        $charset_collate = Config::db()->get_charset_collate();

        $sql = "CREATE TABLE `$table` (
            `order_id` bigint(20) unsigned NOT NULL,
            `queued_at` datetime NOT NULL,
            `last_try` datetime DEFAULT NULL,
            `sent_at` datetime DEFAULT NULL,
            `locked_until` datetime DEFAULT NULL,
            `attempts` smallint(5) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (order_id),
            KEY mktr_sync_pending (sent_at,last_try)
          ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        self::migratePendingOption();
    }

    public static function down()
    {
        Config::db()->query("DROP TABLE IF EXISTS `" . self::tableName() . "`;");

        \delete_option(self::DB_OPTION);
        \delete_option(self::PENDING_OPTION);
    }

    /**
     * Activation does not run when a plugin is merely updated, so the table is also
     * created from the normal request path the first time the version moves.
     * The option is autoloaded, which makes the check free on every later request.
     */
    public static function checkDb()
    {
        if (get_option(self::DB_OPTION) === self::DB_VERSION) {
            return;
        }

        self::up();

        update_option(self::DB_OPTION, self::DB_VERSION);
    }

    /** Nothing queued before the update is dropped on the floor. */
    private static function migratePendingOption()
    {
        $pending = get_option(self::PENDING_OPTION, array());

        if (is_array($pending)) {
            foreach ($pending as $orderId => $queuedAt) {
                self::enqueue((int) $orderId, (int) $queuedAt);
            }
        }

        \delete_option(self::PENDING_OPTION);
    }

    /* ----------------------------------------------------------------- queue */

    /**
     * Several hooks fire for the same order, and the send is deferred to shutdown so
     * the customer is not kept waiting for it.
     */
    public static function schedule($orderId = null)
    {
        $orderId = (int) $orderId;

        if ($orderId <= 0 || isset(self::$queued[$orderId]) || !self::isEnabled()) {
            return;
        }

        $order = wc_get_order($orderId);

        /* Refunds are WC_Order_Refund, not WC_Order. */
        if (!$order instanceof WC_Order) {
            return;
        }

        /* A draft is not an order yet, and the block checkout creates one for every
           customer who reaches the payment step. Keeping drafts out of the table is
           what stops it from filling with carts that were never paid for. */
        if (in_array($order->get_status(), array('checkout-draft', 'trash'), true)) {
            return;
        }

        /* Beyond the retry window a delivered order may no longer be remembered, so a
           late status change would look like an order that was never sent. */
        $created = $order->get_date_created();
        if ($created !== null && $created->getTimestamp() < time() - self::MAX_AGE) {
            return;
        }

        $row = self::getRow($orderId);

        /* Already delivered. A status change carries no new save_order payload:
           order_status is not part of it and travels on update_order_status. */
        if ($row !== null && !empty($row['sent_at'])) {
            return;
        }

        self::$queued[$orderId] = true;

        self::enqueue($orderId);

        Defer::add(function () use ($orderId) {
            self::push($orderId);
        });
    }

    public static function push($orderId = null)
    {
        $orderId = (int) $orderId;

        if ($orderId <= 0 || !self::isEnabled()) {
            return false;
        }

        if (isset(self::$done[$orderId])) {
            return self::$done[$orderId];
        }

        /* A browser queue can call push() without passing through schedule(). */
        self::enqueue($orderId);

        if (!self::claim($orderId)) {
            $row = self::getRow($orderId);

            if ($row !== null && !empty($row['sent_at'])) {
                self::$done[$orderId] = true;
                return true;
            }

            /* Another request is sending this order. Its result will either mark it
               sent or release the lock for a later retry. */
            return false;
        }

        $row = self::getRow($orderId);

        if ($row !== null && !empty($row['sent_at'])) {
            self::$done[$orderId] = true;
            return true;
        }

        if ($row !== null && (int) $row['attempts'] >= self::MAX_ATTEMPTS) {
            return false;
        }

        $order = wc_get_order($orderId);

        if (!$order instanceof WC_Order) {
            self::forget($orderId);
            return false;
        }

        /* A draft becomes a real order moments later, so leave it queued. */
        if (in_array($order->get_status(), array('checkout-draft', 'trash'), true)) {
            self::touch($orderId);
            return false;
        }

        Order::getById($orderId);
        $payload = Order::toArray();

        /* Same check the browser path has always applied. Stays queued rather than
           dropped: an order started in the admin gets its items and customer in
           later requests, and would otherwise never be sent. touch() moves it behind
           everything else so it cannot hold up the orders that are ready. */
        if (empty($payload['products'])
            || (empty($payload['email_address']) && empty($payload['phone']))) {
            self::touch($orderId);
            return false;
        }

        Api::send('save_order', $payload);

        $sent = (int) Api::getStatus() === 200;

        if ($sent) {
            self::markSent($orderId);
        } else {
            self::markFailed($orderId);
        }

        self::$done[$orderId] = $sent;

        return $sent;
    }

    /** Drained by the MKTR_ORDER_SYNC cron and by the OrderSync route. */
    public static function retry($limit = 50)
    {
        if (!self::isEnabled()) {
            return 0;
        }

        self::cleanUp();

        $db = Config::db();

        /* Least recently tried first, so an order that keeps coming back unsendable
           rotates to the back instead of sitting at the head of the queue. */
        $ids = $db->get_col($db->prepare(
            "SELECT order_id FROM `" . self::tableName() . "`
             WHERE sent_at IS NULL AND attempts < %d
             ORDER BY IFNULL(last_try, queued_at) ASC
             LIMIT %d",
            self::MAX_ATTEMPTS,
            (int) $limit
        ));

        if (empty($ids)) {
            return 0;
        }

        $sent = 0;
        $stopAt = time() + self::MAX_RUNTIME;

        foreach ($ids as $orderId) {
            if (self::push($orderId)) {
                $sent++;
            }

            /* Whatever is left keeps its place in the queue for the next run. */
            if (time() >= $stopAt) {
                break;
            }
        }

        return $sent;
    }

    public static function pendingCount()
    {
        $db = Config::db();

        return (int) $db->get_var($db->prepare(
            "SELECT COUNT(*) FROM `" . self::tableName() . "` WHERE sent_at IS NULL AND attempts < %d",
            self::MAX_ATTEMPTS
        ));
    }

    /* ------------------------------------------------------------------ rows */

    private static function getRow($orderId)
    {
        $db = Config::db();

        $row = $db->get_row($db->prepare(
            "SELECT order_id, queued_at, last_try, sent_at, locked_until, attempts
             FROM `" . self::tableName() . "` WHERE order_id = %d",
            $orderId
        ), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    /** Keeps the original queued_at and any sent_at when the row is already there. */
    private static function enqueue($orderId, $queuedAt = null)
    {
        $db = Config::db();

        return $db->query($db->prepare(
            "INSERT INTO `" . self::tableName() . "` (order_id, queued_at) VALUES (%d, %s)
             ON DUPLICATE KEY UPDATE order_id = order_id",
            $orderId,
            gmdate('Y-m-d H:i:s', $queuedAt === null ? time() : $queuedAt)
        ));
    }

    /** Inserts as well: the browser path reaches push() for orders never scheduled. */
    private static function markSent($orderId)
    {
        $db = Config::db();
        $now = gmdate('Y-m-d H:i:s');

        $db->query($db->prepare(
            "INSERT INTO `" . self::tableName() . "` (order_id, queued_at, last_try, sent_at, locked_until, attempts)
             VALUES (%d, %s, %s, %s, NULL, 0)
             ON DUPLICATE KEY UPDATE last_try = VALUES(last_try), sent_at = VALUES(sent_at), locked_until = NULL",
            $orderId,
            $now,
            $now,
            $now
        ));
    }

    private static function markFailed($orderId)
    {
        $db = Config::db();
        $now = gmdate('Y-m-d H:i:s');

        $db->query($db->prepare(
            "INSERT INTO `" . self::tableName() . "` (order_id, queued_at, last_try, locked_until, attempts)
             VALUES (%d, %s, %s, NULL, 1)
             ON DUPLICATE KEY UPDATE last_try = VALUES(last_try), locked_until = NULL, attempts = attempts + 1",
            $orderId,
            $now,
            $now
        ));
    }

    /** Moves an order to the back of the queue without counting an attempt. */
    private static function touch($orderId)
    {
        $db = Config::db();

        $db->query($db->prepare(
            "UPDATE `" . self::tableName() . "` SET last_try = %s, locked_until = NULL WHERE order_id = %d",
            gmdate('Y-m-d H:i:s'),
            $orderId
        ));
    }

    private static function forget($orderId)
    {
        $db = Config::db();

        $db->query($db->prepare(
            "DELETE FROM `" . self::tableName() . "` WHERE order_id = %d",
            $orderId
        ));
    }

    /**
     * Claims an order with one conditional write. This closes the race between the
     * browser queue and server-side hooks: only the request that acquires this lock
     * may call save_order. A lock is allowed to expire after a crashed request.
     */
    private static function claim($orderId)
    {
        $db = Config::db();
        $now = gmdate('Y-m-d H:i:s');
        $until = gmdate('Y-m-d H:i:s', time() + self::LOCK_TTL);

        return $db->query($db->prepare(
            "UPDATE `" . self::tableName() . "`
             SET locked_until = %s
             WHERE order_id = %d
               AND sent_at IS NULL
               AND attempts < %d
               AND (locked_until IS NULL OR locked_until < %s)",
            $until,
            $orderId,
            self::MAX_ATTEMPTS,
            $now
        )) === 1;
    }

    /** Drops what can no longer be delivered, and what no longer needs remembering. */
    private static function cleanUp()
    {
        $db = Config::db();
        $table = self::tableName();

        $db->query($db->prepare(
            "DELETE FROM `$table` WHERE sent_at IS NULL AND queued_at < %s",
            gmdate('Y-m-d H:i:s', time() - self::MAX_AGE)
        ));

        /* Kept well past MAX_AGE, so schedule() can still tell a delivered order from
           one that was never seen. */
        $db->query($db->prepare(
            "DELETE FROM `$table` WHERE sent_at IS NOT NULL AND sent_at < %s",
            gmdate('Y-m-d H:i:s', time() - self::KEEP_SENT)
        ));
    }
}
