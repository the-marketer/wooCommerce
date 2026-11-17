<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      Alexandru Buzica (EAX LEX S.R.L.) <b.alex@eax.ro>
 * @license     https://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker\Routes;

use Mktr\Tracker\Config;
use Mktr\Tracker\Events;
use Mktr\Tracker\Session;

class syncEvents
{
    const SYNC = 'syncEvents';
    const DELIVER = 'deliverEvents';
    const MAX_DELIVERY_ATTEMPTS = 3;
    const DELIVERY_ATTEMPTS = 'mktrDeliveryAttempts';
    const PUSHED_EMAILS = 'mktrPushedEmails';
    const PUSHED_ORDERS = 'mktrPushedOrders';

    public static function maybeHandle()
    {
        if (!Session::cacheMode()) {
            return;
        }

        $action = self::requestedAction();

        if ($action === null || !self::isScriptPost()) {
            return;
        }

        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        nocache_headers();

        wp_send_json($action === self::SYNC ? self::execute() : self::deliver(self::postedKeys('orders'), self::postedKeys('emails')));
    }

    private static function requestedAction()
    {
        if (!isset($_GET[Config::$name])) {
            return null;
        }

        $action = sanitize_text_field(wp_unslash($_GET[Config::$name]));

        return in_array($action, array(self::SYNC, self::DELIVER), true) ? $action : null;
    }

    private static function isScriptPost()
    {
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper(sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']))) : '';
        $requestedWith = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_REQUESTED_WITH'])) : '';

        return $method === 'POST' && $requestedWith === 'XMLHttpRequest';
    }

    private static function postedKeys($field)
    {
        if (!isset($_POST[$field])) {
            return array();
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $keys = json_decode(wp_unslash($_POST[$field]), true);

        if (!is_array($keys)) {
            return array();
        }

        return array_values(array_filter($keys, function ($key) {
            return is_string($key) || is_int($key);
        }));
    }

    public static function execute()
    {
        ob_start();
        Session::$syncHint = false;
        $events = array();
        $deliver = array('orders' => array(), 'emails' => array());

        try {
            self::rememberWishlistBaseline();
            $events = loadEvents::execute(false);

            /* An order already in the browser's dataLayer is only retried on the
               server; pushing __sm__order again would count it twice. */
            $pushedOrders = Config::session()->get(self::PUSHED_ORDERS, array());
            foreach (self::deliverableOrders() as $key => $orderId) {
                if (!isset($pushedOrders[$key])) {
                    $event = Events::getEvent('__sm__order', $orderId);
                    if ($event === false) {
                        continue;
                    }
                    $events[] = $event->toArray();
                }
                $deliver['orders'][] = $key;
            }

            $pushed = Config::session()->get(self::PUSHED_EMAILS, array());
            foreach (self::pendingEmails() as $key => $email) {
                if (!isset($pushed[$key])) {
                    $event = Events::getEvent('__sm__set_email', $email);
                    if ($event === false) {
                        continue;
                    }
                    $events[] = $event->toArray();
                }
                $deliver['emails'][] = $key;
            }

        } catch (\Throwable $e) {
            self::logError($e);
        }

        Session::save();
        ob_end_clean();

        return array(
            'events' => array_values($events),
            'deliver' => empty($deliver['orders']) && empty($deliver['emails']) ? false : $deliver
        );
    }

    public static function deliver($orderIds, $emailKeys)
    {
        ob_start();
        Session::$syncHint = false;
        $retry = false;

        try {
            self::markEmailsPushed($emailKeys);
            self::markOrdersPushed($orderIds);

            foreach (array_keys(self::deliverableOrders(array_flip($orderIds))) as $orderId) {
                $retry = self::deliverItem('saveOrder', 'order', $orderId, function () {
                    saveOrder::execute();
                }) || $retry;
            }

            foreach (array_keys(array_intersect_key(self::pendingEmails(), array_flip($emailKeys))) as $key) {
                $retry = self::deliverItem('setEmail', 'email', $key, function () {
                    setEmail::execute();
                }) || $retry;
            }
        } catch (\Exception $e) {
            $retry = true;
            self::logError($e);
        } catch (\Throwable $e) {
            $retry = true;
            self::logError($e);
        }

        self::forgetDeliveredEmails();
        self::forgetDeliveredOrders();
        self::forgetSettledAttempts();

        if ($retry) {
            Session::armSyncHint();
        }

        Session::save();
        ob_end_clean();

        return array();
    }

    private static function deliverItem($sessionKey, $type, $key, $send)
    {
        $attempt = self::countAttempt(self::attemptKey($type, $key));
        $all = Config::session()->get($sessionKey);
        $item = array($key => $all[$key]);
        $rest = array_diff_key($all, $item);
        $stillPending = true;

        Config::session()->set($sessionKey, $item);

        /* A failing item is logged and retried; it must not stop the items after it. */
        try {
            $send();
        } catch (\Exception $e) {
            self::logError($e);
        } catch (\Throwable $e) {
            self::logError($e);
        } finally {
            $left = Config::session()->get($sessionKey);
            $stillPending = is_array($left) && isset($left[$key]);
            $exhausted = $stillPending && $attempt >= self::MAX_DELIVERY_ATTEMPTS;

            Config::session()->set($sessionKey, $exhausted || !$stillPending ? $rest : $rest + $left);
        }

        return $stillPending && $attempt < self::MAX_DELIVERY_ATTEMPTS;
    }

    private static function deliverableOrders($only = null)
    {
        $orders = Config::session()->get('saveOrder');

        if (empty($orders)) {
            return array();
        }

        if ($only !== null) {
            $orders = array_intersect_key($orders, $only);
        }

        return array_filter($orders, function ($orderId) {
            return !self::isCheckoutDraft($orderId);
        });
    }

    private static function isCheckoutDraft($orderId)
    {
        $order = wc_get_order($orderId);

        return $order && $order->get_status() === 'checkout-draft';
    }

    private static function pendingEmails()
    {
        $emails = Config::session()->get('setEmail');

        return is_array($emails) ? $emails : array();
    }

    private static function markEmailsPushed($keys)
    {
        $pushed = Config::session()->get(self::PUSHED_EMAILS, array());
        $known = array_intersect_key(array_fill_keys($keys, true), self::pendingEmails());

        Config::session()->set(self::PUSHED_EMAILS, $pushed + $known);
    }

    private static function forgetDeliveredEmails()
    {
        $pushed = Config::session()->get(self::PUSHED_EMAILS, array());

        Config::session()->set(self::PUSHED_EMAILS, array_intersect_key($pushed, self::pendingEmails()));
    }

    private static function markOrdersPushed($keys)
    {
        $pushed = Config::session()->get(self::PUSHED_ORDERS, array());
        $known = array_intersect_key(array_fill_keys($keys, true), self::deliverableOrders());

        Config::session()->set(self::PUSHED_ORDERS, $pushed + $known);
    }

    private static function forgetDeliveredOrders()
    {
        $pushed = array_intersect_key(Config::session()->get(self::PUSHED_ORDERS, array()), self::deliverableOrders());

        Config::session()->set(self::PUSHED_ORDERS, empty($pushed) ? null : $pushed);
    }

    private static function attemptKey($type, $key)
    {
        return $type . ':' . $key;
    }

    private static function countAttempt($attemptKey)
    {
        $attempts = Config::session()->get(self::DELIVERY_ATTEMPTS, array());
        $attempts[$attemptKey] = (isset($attempts[$attemptKey]) ? $attempts[$attemptKey] : 0) + 1;
        Config::session()->set(self::DELIVERY_ATTEMPTS, $attempts);

        return $attempts[$attemptKey];
    }

    private static function forgetSettledAttempts()
    {
        $pending = array();

        foreach (self::deliverableOrders() as $orderId) {
            $pending[self::attemptKey('order', $orderId)] = true;
        }
        foreach (self::pendingEmails() as $key => $email) {
            $pending[self::attemptKey('email', $key)] = true;
        }

        $attempts = array_intersect_key(Config::session()->get(self::DELIVERY_ATTEMPTS, array()), $pending);
        Config::session()->set(self::DELIVERY_ATTEMPTS, empty($attempts) ? null : $attempts);
    }

    private static function rememberWishlistBaseline()
    {
        if (Config::session()->get('woodmart_wishlist_count') !== null || !isset($_COOKIE['woodmart_wishlist_count'])) {
            return;
        }

        $products = isset($_COOKIE['woodmart_wishlist_products']) ? sanitize_text_field(wp_unslash($_COOKIE['woodmart_wishlist_products'])) : '{}';

        Config::session()->set('woodmart_wishlist_products', $products);
        Config::session()->set('woodmart_wishlist_count', sanitize_text_field(wp_unslash($_COOKIE['woodmart_wishlist_count'])));
    }

    private static function logError($e)
    {
        if (defined('MKTR_DEBUG') && MKTR_DEBUG) {
            \Mktr\Tracker\Logs::debug(array($e->getMessage(), $e->getTraceAsString()), 'sync_events_error');
        }
    }
}
