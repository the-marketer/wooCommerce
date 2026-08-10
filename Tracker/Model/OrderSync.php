<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      Alexandru Buzica (EAX LEX S.R.L.) <b.alex@eax.ro>
 * @license     https://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker\Model;

use Mktr\Tracker\Api;
use Mktr\Tracker\Config;
use WC_Order;

/**
 * Sends save_order from the server, so an order still reaches theMarketer when the
 * customer never comes back from the payment page, or when it is created without a
 * browser at all (gateway webhook, admin, Store API, WP-CLI).
 */
class OrderSync
{
    const META_ATTEMPTS = '_mktr_sync_attempts';
    const PENDING_OPTION = 'mktr_order_sync_pending';
    const MAX_ATTEMPTS = 10;
    const MAX_AGE = 604800; /* 7 days */

    private static $queue = array();
    private static $shutdown = false;
    private static $done = array();

    public static function isEnabled()
    {
        return Config::getOnboarding() === 2 && Config::getStatus() === 1
            && !empty(Config::getRestKey()) && !empty(Config::getCustomerId());
    }

    /** Queue for the end of this request; several hooks fire for the same order. */
    public static function schedule($orderId = null)
    {
        $orderId = (int) $orderId;

        if ($orderId <= 0 || !self::isEnabled()) {
            return;
        }

        self::addPending($orderId);
        self::$queue[$orderId] = $orderId;

        if (!self::$shutdown) {
            self::$shutdown = true;
            add_action('shutdown', array(__CLASS__, 'flush'), PHP_INT_MAX);
        }
    }

    /** fastcgi_finish_request releases the customer before our HTTP call runs. */
    public static function flush()
    {
        $queue = self::$queue;
        self::$queue = array();

        if (empty($queue)) {
            return;
        }

        @ignore_user_abort(true);

        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }

        foreach ($queue as $orderId) {
            self::push($orderId);
        }
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

        $order = wc_get_order($orderId);

        /* Refunds are WC_Order_Refund, not WC_Order. */
        if (!$order instanceof WC_Order) {
            self::removePending($orderId);
            return false;
        }

        /* A draft becomes a real order moments later, so leave it queued. */
        if (in_array($order->get_status(), array('checkout-draft', 'trash'), true)) {
            return false;
        }

        $attempts = (int) $order->get_meta(self::META_ATTEMPTS);

        if ($attempts >= self::MAX_ATTEMPTS) {
            self::removePending($orderId);
            return false;
        }

        Order::getById($orderId);
        $payload = Order::toArray();

        /* Same check the browser path has always applied. Stays queued rather than
           dropped: an order started in the admin gets its items and customer in
           later requests, and would otherwise never be sent. */
        if (empty($payload['products'])
            || (empty($payload['email_address']) && empty($payload['phone']))) {
            return false;
        }

        Api::send('save_order', $payload);

        $sent = (int) Api::getStatus() === 200;

        if ($sent) {
            self::removePending($orderId);
        } else {
            $order->update_meta_data(self::META_ATTEMPTS, $attempts + 1);
            $order->save_meta_data();
            self::addPending($orderId);
        }

        self::$done[$orderId] = $sent;

        return $sent;
    }

    /** Drained by the OrderSync route, called from a real cron job. */
    public static function retry($limit = 50)
    {
        if (!self::isEnabled()) {
            return 0;
        }

        $sent = 0;
        $expired = time() - self::MAX_AGE;

        foreach (array_slice(self::getPending(), 0, (int) $limit, true) as $orderId => $queuedAt) {
            /* An order that never got completed would otherwise sit at the head of
               the queue forever and keep real ones from being reached. */
            if ((int) $queuedAt < $expired) {
                self::removePending($orderId);
                continue;
            }

            if (self::push($orderId)) {
                $sent++;
            }
        }

        return $sent;
    }

    public static function getPending()
    {
        $pending = get_option(self::PENDING_OPTION, array());

        return is_array($pending) ? $pending : array();
    }

    private static function addPending($orderId)
    {
        $pending = self::getPending();

        if (!isset($pending[$orderId])) {
            $pending[$orderId] = time();
            update_option(self::PENDING_OPTION, $pending, false);
        }
    }

    private static function removePending($orderId)
    {
        $pending = self::getPending();

        if (isset($pending[$orderId])) {
            unset($pending[$orderId]);
            update_option(self::PENDING_OPTION, $pending, false);
        }
    }
}
