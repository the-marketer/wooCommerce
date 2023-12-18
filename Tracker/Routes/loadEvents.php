<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      Alexandru Buzica (EAX LEX S.R.L.) <b.alex@eax.ro>
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker\Routes;

use Mktr\Tracker\Events;
use Mktr\Tracker\Valid;
use Mktr\Tracker\Config;

class loadEvents
{
    private static $init = null;

    public static function init() {
        if (self::$init == null) { self::$init = new self(); }
        return self::$init;
    }

    public static function checkAdded($n, $o) {
        return array_keys(array_diff_key($n, $o));
    }
    
    public static function execute() {
        Valid::setParam('mime-type', 'js');
        $lines = [ '/* TheMaketer */' ];
        
        if (isset( $_COOKIE['woodmart_wishlist_count'] )) {
            $wishList = Config::session()->get("woodmart_wishlist_products"); $wishListC = Config::session()->get("woodmart_wishlist_count");
            if ($wishListC === null) {
                $wishListC = 0;
            }
            if ($wishList === null || $wishListC != $_COOKIE['woodmart_wishlist_count']) {
                $wishList0 = (isset($_COOKIE['woodmart_wishlist_products']) ? $_COOKIE['woodmart_wishlist_products'] : '{}');
                if ($wishListC !== null && $wishListC != $_COOKIE['woodmart_wishlist_count']) {
                    $n = json_decode(stripslashes($wishList0), true); $o = json_decode(stripslashes($wishList), true);
                    
                    $add = self::checkAdded($n, $o);
                    $remove = self::checkAdded($o, $n);
                    foreach ($add as $value) { \Mktr\Tracker\Observer::addToWishlist($value, 0); }
                    foreach ($remove as $value) { \Mktr\Tracker\Observer::removeFromWishlist($value, 0); }
                }
                Config::session()->set("woodmart_wishlist_products", $wishList0);
                Config::session()->set("woodmart_wishlist_count", $_COOKIE['woodmart_wishlist_count']);
            }
        }

        foreach (Events::observerGetEvents as $event => $Name) {
            if (!$Name[0]) {
                $eventData = Config::session()->get($event);
                if (!empty($eventData)) {
                    foreach ($eventData as $value) {
                        $lines[] = "dataLayer.push(" . Events::getEvent($Name[1], $value)->toJson() . ");";
                    }
                }
                Config::session()->set($event, array());
            }
        }

        return implode(PHP_EOL, $lines);
    }
}