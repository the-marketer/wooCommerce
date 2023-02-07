<?php
/**
 * @copyright   © EAX LEX SRL. All rights reserved.
 **/

namespace Mktr\Tracker;

use Mktr\Tracker\Model\Order;
use Mktr\Tracker\Model\Product;

class Observer
{
    private static $init = null;
    private static $eventName = null;
    private static $eventData = [];

    public static function init()
    {
        if (self::$init == null) {
            self::$init = new self();
        }
        return self::$init;
    }

    public static function addToCart($product_id, $quantity, $variation_id)
    {
        Product::getById($variation_id ? : $product_id );

        self::$eventName = 'addToCart';

        self::$eventData = array(
            'product_id' => Product::getParentId() == 0 ? Product::getId() :Product::getParentId(),
            'quantity'=> (int) $quantity,
            'variation' => array(
                'id' => Product::getId(),
                'sku' => Product::getSku()
            )
        );

        self::SessionSet();
    }

    public static function removeFromCart($product_id, $quantity, $variation_id)
    {
        Product::getById($variation_id ? : $product_id );

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
        Product::getById($variation_id ? : $product_id );

        self::$eventName = 'addToWishlist';

        self::$eventData = array(
            'product_id' => Product::getParentId() == 0 ? Product::getId() :Product::getParentId(),
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
        Product::getById($variation_id ? : $product_id );

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

        if (Config::getPushStatus() != 0)
        {
            FileSystem::writeFile("firebase-config.js", Config::getFireBase());
            FileSystem::writeFile("firebase-messaging-sw.js", Config::getFireBaseMessaging());
        } else {
            FileSystem::deleteFile("firebase-config.js");
            FileSystem::deleteFile("firebase-messaging-sw.js");
        }
    }

    public static function orderUp($oID, $status)
    {
        $send = array(
            'order_number' => $oID,
            'order_status' => $status
        );

        Api::send("update_order_status", $send, false);
    }

    public static function saveOrder($orderId)
    {
        Order::getById($orderId);
        // ['email_address']
        // ['phone']

        self::$eventName = 'saveOrder';
        self::$eventData = Order::toArray();

        self::SessionSet($orderId);
    }

    public static function registerOrLogIn($user_login, $user = null )
    {
        if (!is_null($user)) {
            setcookie("mktr", (
                is_array($user) ?
                    $user["user_email"] : $user->user_email
            ), strtotime( '+30 days' ));
        }
    }

    public static function getEmail($email = null, $user = null)
    {
        if ($user === null)
        {
            $user = get_user_by('email', $email);
        }

        $send = array(
            'email_address' => $user->user_email
        );

        if (!empty($user->first_name)) {
            $send['firstname'] = $user->first_name;
        } else {
            $send['firstname'] = get_user_meta($user->ID,'billing_first_name', true);
            if (empty($send['firstname']))
            {
                unset($send['firstname']);
            }
        }

        if (!empty($user->last_name)) {
            $send['lastname'] = $user->last_name;
        } else {
            $send['lastname'] = get_user_meta($user->ID,'billing_last_name', true);
            if (empty($send['lastname']))
            {
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

        if ($key === null)
        {
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