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

class OrderSync
{
    const DB_VERSION = '1';
    const DB_OPTION = 'mktr_order_sync_db';
    const PENDING_OPTION = 'mktr_order_sync_pending';

    const MAX_ATTEMPTS = 10;
    const MAX_AGE = 604800;
    const KEEP_SENT = 2592000;
    const MAX_RUNTIME = 20;
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

    public static function checkDb()
    {
        if (get_option(self::DB_OPTION) === self::DB_VERSION) {
            return;
        }

        self::up();

        update_option(self::DB_OPTION, self::DB_VERSION);
    }

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

    public static function schedule($orderId = null)
    {
        $orderId = (int) $orderId;

        if ($orderId <= 0 || isset(self::$queued[$orderId]) || !self::isEnabled()) {
            return;
        }

        $order = wc_get_order($orderId);

        if (!$order instanceof WC_Order) {
            return;
        }

        if (in_array($order->get_status(), array('checkout-draft', 'trash'), true)) {
            return;
        }

        $created = $order->get_date_created();
        if ($created !== null && $created->getTimestamp() < time() - self::MAX_AGE) {
            return;
        }

        $row = self::getRow($orderId);

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

        self::enqueue($orderId);

        if (!self::claim($orderId)) {
            $row = self::getRow($orderId);

            if ($row !== null && !empty($row['sent_at'])) {
                self::$done[$orderId] = true;
                return true;
            }

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

        if (in_array($order->get_status(), array('checkout-draft', 'trash'), true)) {
            self::touch($orderId);
            return false;
        }

        Order::getById($orderId);
        $payload = Order::toArray();

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

    public static function retry($limit = 50)
    {
        if (!self::isEnabled()) {
            return 0;
        }

        self::cleanUp();

        $db = Config::db();

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

    private static function cleanUp()
    {
        $db = Config::db();
        $table = self::tableName();

        $db->query($db->prepare(
            "DELETE FROM `$table` WHERE sent_at IS NULL AND queued_at < %s",
            gmdate('Y-m-d H:i:s', time() - self::MAX_AGE)
        ));

        $db->query($db->prepare(
            "DELETE FROM `$table` WHERE sent_at IS NOT NULL AND sent_at < %s",
            gmdate('Y-m-d H:i:s', time() - self::KEEP_SENT)
        ));
    }
}
