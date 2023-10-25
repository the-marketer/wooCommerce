<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      Alexandru Buzica (EAX LEX S.R.L.) <b.alex@eax.ro>
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker;

use Mktr\Tracker\Model\Product;

class Observer
{
    private static $init = null;
    private static $eventName = null;
    private static $eventData = [];

    private static $OrderUP = false;

    public static function init()
    {
        if (self::$init == null) {
            self::$init = new self();
        }
        return self::$init;
    }

    public static function addToCart($product_id, $quantity, $variation_id)
    {
        $v = Product::getById($variation_id ?: $product_id);
        if ($v !== false) {
            self::$eventName = 'addToCart';

            self::$eventData = array(
                'product_id' => Product::getParentId() == 0 ? Product::getId() : Product::getParentId(),
                'quantity' => (int) $quantity,
                'variation' => array(
                    'id' => Product::getId(),
                    'sku' => Product::getSku()
                )
            );

            self::SessionSet();
        }
    }

    public static function removeFromCart($product_id, $quantity, $variation_id)
    {
        Product::getById($variation_id ?: $product_id);

        self::$eventName = 'removeFromCart';

        self::$eventData = array(
            'product_id' => Product::getParentId() == 0 ? Product::getId() : Product::getParentId(),
            'quantity'=> (int) $quantity,
            'variation' => array(
                'id' => Product::getId(),
                'sku' => Product::getSku()
            )
        );

        self::SessionSet();
    }
    public static function addToWishlist($product_id, $variation_id)
    {
        Product::getById($variation_id ?: $product_id);

        self::$eventName = 'addToWishlist';

        self::$eventData = array(
            'product_id' => Product::getParentId() == 0 ? Product::getId() : Product::getParentId(),
            //'quantity'=> (int) $quantity,
            'variation' => array(
                'id' => Product::getId(),
                'sku' => Product::getSku()
            )
        );

        self::SessionSet();
    }

    public static function removeFromWishlist($product_id, $variation_id)
    {
        Product::getById($variation_id ?: $product_id);

        self::$eventName = 'removeFromWishlist';

        self::$eventData = array(
            'product_id' => Product::getParentId() == 0 ? Product::getId() : Product::getParentId(),
            //'quantity'=> (int) $quantity,
            'variation' => array(
                'id' => Product::getId(),
                'sku' => Product::getSku()
            )
        );

        self::SessionSet();
    }

    public static function pushStatus()
    {
        FileSystem::setWorkDirectory('base');

        if (Config::getPushStatus() != 0) {
            FileSystem::writeFile("firebase-config.js", Config::getFireBase());
            FileSystem::writeFile("firebase-messaging-sw.js", Config::getFireBaseMessaging());
        } else {
            FileSystem::deleteFile("firebase-config.js");
            FileSystem::deleteFile("firebase-messaging-sw.js");
        }
    }

    public static function orderUp($oID, $status)
    {
        if (self::$OrderUP === false) {
            self::$OrderUP = true;
            $send = array(
                'order_number' => $oID,
                'order_status' => $status
            );
    
            Api::send("update_order_status", $send, false);
        }
    }

    public static function orderUpApi($oID, $order)
    {
        if (self::$OrderUP === false) {
            // FileSystem::setWorkDirectory('base');
            // FileSystem::writeFile("baseTest.js",'baseLinkUpdate');
            self::$OrderUP = true;
            $send = array(
                'order_number' => $oID,
                'order_status' => $order->get_status()
            );
            Api::send("update_order_status", $send, false);
        }
    }

    public static function saveOrder($orderId = null)
    {
        // Order::getById($orderId);
        // ['email_address']
        // ['phone']

        self::$eventName = 'saveOrder';
        self::$eventData = $orderId;

        self::SessionSet($orderId);
    }

    public static function registerOrLogIn($user_login, $user = null)
    {
        if (!is_null($user)) {
            setcookie("mktr", sanitize_email((
                is_array($user) ?
                    $user["user_email"] : $user->user_email
            )), strtotime('+30 days'));
        }
    }

    public static function getEmail($email = null, $user = null)
    {
        if ($user === null) {
            $user = get_user_by('email', $email);
        }

        $send = array(
            'email_address' => $user->user_email
        );

        if (!empty($user->first_name)) {
            $send['firstname'] = $user->first_name;
        } else {
            $send['firstname'] = get_user_meta($user->ID, 'billing_first_name', true);
            if (empty($send['firstname'])) {
                unset($send['firstname']);
            }
        }

        if (!empty($user->last_name)) {
            $send['lastname'] = $user->last_name;
        } else {
            $send['lastname'] = get_user_meta($user->ID, 'billing_last_name', true);
            if (empty($send['lastname'])) {
                unset($send['lastname']);
            }
        }
        return $send;
    }

    public static function emailAndPhone($email)
    {
        $user = get_user_by('email', $email);

        $send = self::getEmail($email, $user);

        self::$eventName = "setPhone";

        self::$eventData = array(
            'phone' => get_user_meta($user->ID, 'billing_phone', true)
        );

        self::SessionSet();

        self::$eventName = 'setEmail';
        self::$eventData = $send;

        self::SessionSet();
    }

    private static function SessionSet($key = null)
    {
        $add = WC()->session->get(self::$eventName);

        if ($key === null) {
            $n = '';

            for ($i = 0, $indexMax = 9; $i < 5; ++$i) {
                $n .= random_int(0, 9);
            }

            $add[time().$n] = self::$eventData;
        } else {
            $add[$key] = self::$eventData;
        }

        WC()->session->set(self::$eventName, $add);
    }
}
