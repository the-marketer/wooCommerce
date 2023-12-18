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

use Mktr\Tracker\Model\Order;
use Mktr\Tracker\Model\Category;
use Mktr\Tracker\Model\Product;

class Events
{
    private static $init = null;
    private static $shName = null;
    private static $data = array();

    public static $isWoodMart = false;

    private static $assets = array();

    const actions = [
        "is_home" => "__sm__view_homepage",
        "is_product_category" => "__sm__view_category",
        "is_product" => "__sm__view_product",
        "is_checkout" => "__sm__initiate_checkout",
        "is_search" => "__sm__search"
    ];

    const observerGetEvents = [
        "addToCart"=> [false, "__sm__add_to_cart"],
        "removeFromCart"=> [false, "__sm__remove_from_cart"],
        "addToWishlist"=> [false, "__sm__add_to_wishlist"],
        "removeFromWishlist"=> [false, "__sm__remove_from_wishlist"],
        "saveOrder"=> [true, "__sm__order"],
        "setEmail"=> [true, "__sm__set_email"],
        "setPhone"=> [false, "__sm__set_phone"]
    ];

    const eventsName = [
        "__sm__view_homepage" =>"HomePage",
        "__sm__view_category" => "Category",
        "__sm__view_brand" => "Brand",
        "__sm__view_product" => "Product",
        "__sm__add_to_cart" => "addToCart",
        "__sm__remove_from_cart" => "removeFromCart",
        "__sm__add_to_wishlist" => "addToWishlist",
        "__sm__remove_from_wishlist" => "removeFromWishlist",
        "__sm__initiate_checkout" => "Checkout",
        "__sm__order" => "saveOrder",
        "__sm__search" => "Search",
        "__sm__set_email" => "setEmail",
        "__sm__set_phone" => "setPhone"
    ];

    const eventsSchema = [
        "HomePage" => null,
        "Checkout" => null,
        "Cart" => null,

        "Category" => [
            "category" => "category"
        ],

        "Brand" => [
            "name" => "name"
        ],

        "Product" => [
            "product_id" => "product_id"
        ],

        "Search" => [
            "search_term" => "search_term"
        ],

        "setPhone" => [
            "phone" => "phone"
        ],

        "addToWishlist" => [
            "product_id" => "product_id",
            "variation" => [
                "@key" => "variation",
                "@schema" => [
                    "id" => "id",
                    "sku" => "sku"
                ]
            ]
        ],

        "removeFromWishlist" => [
            "product_id" => "product_id",
            "variation" => [
                "@key" => "variation",
                "@schema" => [
                    "id" => "id",
                    "sku" => "sku"
                ]
            ]
        ],

        "addToCart" => [
            "product_id" => "product_id",
            "quantity" => "quantity",
            "variation" => [
                "@key" => "variation",
                "@schema" => [
                    "id" => "id",
                    "sku" => "sku"
                ]
            ]
        ],

        "removeFromCart" => [
            "product_id" => "product_id",
            "quantity" => "quantity",
            "variation" => [
                "@key" => "variation",
                "@schema" => [
                    "id" => "id",
                    "sku" => "sku"
                ]
            ]
        ],

        "saveOrder" => [
            "number" => "number",
            "email_address" => "email_address",
            "phone" => "phone",
            "firstname" => "firstname",
            "lastname" => "lastname",
            "city" => "city",
            "county" => "county",
            "address" => "address",
            "discount_value" => "discount_value",
            "discount_code" => "discount_code",
            "shipping" => "shipping",
            "tax" => "tax",
            "total_value" => "total_value",
            "products" => [
                "@key" => "products",
                "@schema" =>
                    [
                        "product_id" => "product_id",
                        "price" => "price",
                        "quantity" => "quantity",
                        "variation_sku" => "variation_sku"
                    ]
            ]
        ],

        "setEmail" => [
            "email_address" => "email_address",
            "firstname" => "firstname",
            "lastname" => "lastname"
        ]
    ];
    /**
     * @var array
     */
    private static $bMultiCat;

    public static function init()
    {
        if (self::$init == null) {
            self::$init = new self();
        }
        return self::$init;
    }

    public static function loader()
    {
        $lines = array();

        $key = Config::getKey();

        $lines[] = vsprintf(Config::loader, array( $key ));

        $lines[] = 'window.mktr = window.mktr || {};
window.mktr.debug = function () { if (typeof dataLayer != "undefined") { for (let i of dataLayer) { console.log("Mktr","Google",i); } } };';
        $lines[] = '';
        $wh =  array(Config::space, implode(Config::space, $lines));
        $rep = array("%space%","%implode%");
        /** @noinspection BadExpressionStatementJS */
        /** @noinspection JSUnresolvedVariable */
        echo str_replace($rep, $wh, '<!-- Mktr Script Start -->%space%<script type="text/javascript">%space%%implode%%space%</script>%space%<!-- Mktr Script END -->');
    }

    public function loadEvents()
    {
        $loadJS = $lines = array();
        $lines[] = "window.mktr.try = 0; window.mktr.LoadEvents = function () { if (window.mktr.try <= 5 && typeof dataLayer != 'undefined') { ";
        foreach (self::actions as $key=>$value) {
            if ( $key === 'is_checkout' && $key() && !is_order_received_page() || $key !== 'is_checkout' && $key() || $key === 'is_home' && is_front_page() ) {
                $lines[] = "dataLayer.push(".self::getEvent($value)->toJson().");";
                break;
            }
        }

        $clear = Config::session()->get("ClearMktr");

        if ($clear === null) {
            $clear = array();
        }

        foreach (self::observerGetEvents as $event=>$Name) {
            $eventData = Config::session()->get($event);
            if (!empty($eventData)) {
                foreach ($eventData as $key=>$value) {
                    $lines[] = "dataLayer.push(".self::getEvent($Name[1], $value)->toJson().");";
                    if (!$Name[0]) {
                        $clear[$event][$key] = $key;
                    }
                }

                if ($Name[0]) {
                    //Config::session()->set($event, array());
                    $loadJS[$event] = true;
                } /** @noinspection PhpStatementHasEmptyBodyInspection */ else {
                    // $clear[$event][$key] = "clear";
                    // Config::session()->set($event, array());
                }
            }
        }

        $baseURL = Config::getBaseURL();

        foreach ($loadJS as $k=>$v) {
            $lines[] = '(function(){ let add = document.createElement("script"); add.async = true; add.src = "'.esc_js($baseURL).'?mktr='.esc_js($k).'&mktr_time="+(new Date()).getTime(); let s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(add,s); })();';
        }

        if (!empty($clear)) {
            Config::session()->set("ClearMktr", $clear);

            $lines[] = '(function(){ let add = document.createElement("script"); add.async = true; add.src = "'.esc_js($baseURL).'?mktr=clearEvents&mktr_time="+(new Date()).getTime(); let s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(add,s); })();';
        }

        // $lines[] = 'setTimeout(window.mktr.debug, 1500);';
        $lines[] = " } else if(window.mktr.try <= 5) { window.mktr.try++; setTimeout(window.mktr.LoadEvents, 1500); } }; setTimeout(window.mktr.LoadEvents, 1500);";
        // console.log('eax','add', add); console.log('eax','remove',remove);
        if (self::$isWoodMart) {
        // if (isset( $_COOKIE['woodmart_wishlist_count'] )) {
            $wishList = Config::session()->get("woodmart_wishlist_products");
            if ($wishList === null) {
                $wishList = (isset($_COOKIE['woodmart_wishlist_products']) ? $_COOKIE['woodmart_wishlist_products'] : '{}');
                Config::session()->set("woodmart_wishlist_products", $wishList);
                Config::session()->set("woodmart_wishlist_count", $_COOKIE['woodmart_wishlist_count']);
            }

        $lines[] = "mktr.checkAdded = function(n, o) { return Object.keys(n).filter(i => !o[i]); }
mktr.jsonDecode = function(j = null) { try { return j !== null ? JSON.parse(j) : {}; } catch (error) { console.error('Error parsing JSON:', error); return {}; } }
mktr.cookie = function(name, cookieName = '',  decodedCookie = '', cookieArray = [], i = 0, cookie = null) {
    cookieName = name + '='; decodedCookie = decodeURIComponent(document.cookie); cookieArray = decodedCookie.split(';');
    for (i = 0; i < cookieArray.length; i++) {
        cookie = cookieArray[i]; while (cookie.charAt(0) == ' ') { cookie = cookie.substring(1); }
        if (cookie.indexOf(cookieName) == 0) { return cookie.substring(cookieName.length, cookie.length); }
    }
    return null;
}
mktr.storage = {
    _wishlist: ".stripslashes($wishList).",
    get wishlist() { return this._wishlist; },
    set wishlist(value) {
        let add = mktr.checkAdded(value, this._wishlist); let remove = mktr.checkAdded(this._wishlist, value);
        if (add.length !== 0 || remove.length !== 0) { window.mktr.LoadEventsFunc(); this._wishlist = value; }
    }
};

setInterval(function (c = null) {
    if (mktr.cookie('woodmart_wishlist_products') !== null) {
        mktr.storage.wishlist = mktr.jsonDecode(mktr.cookie('woodmart_wishlist_products'));
    }
}, 5000);";
        }

        $wh =  array(Config::space, implode(Config::space, $lines));
        $rep = array("%space%","%implode%");
        /** @noinspection BadExpressionStatementJS */
        /** @noinspection JSUnresolvedVariable */
        echo str_replace($rep, $wh, '<!-- Mktr Script Start -->%space%<script type="text/javascript">%space%%implode%%space%</script>%space%<!-- Mktr Script END -->');
    }

    public static function build()
    {
        foreach (self::$assets as $key=>$val) {
            self::$data[$key] = $val;
        }
    }

    public static function schemaValidate($array, $schema)
    {
        $newOut = [];

        foreach ($array as $key=>$val) {
            if (isset($schema[$key])) {
                if (is_array($val)) {
                    $newOut[$schema[$key]["@key"]] = self::schemaValidate($val, $schema[$key]["@schema"]);
                } else {
                    $newOut[$schema[$key]] = $val;
                }
            } elseif (is_array($val)) {
                $newOut[] = self::schemaValidate($val, $schema);
            }
        }

        return $newOut;
    }

    public static function getEvent($Name, $eventData = [])
    {
        if (empty(self::eventsName[$Name])) {
            return false;
        }

        self::$shName = self::eventsName[$Name];

        self::$data = array(
            "event" => $Name
        );

        self::$assets = array();

        switch (self::$shName) {
            case "Category":
                self::$assets['category'] = self::buildCategory();
                break;
            case "Product":
                if (Product::getId() !== null) {
                    self::$assets['product_id'] = Product::getId();
                } else {
                    return false;
                }
                break;
            case "saveOrder":
                Order::getById($eventData);
                self::$assets = Order::toArray();
                break;
            case "Search":
                self::$assets['search_term'] = get_search_query(true);
                break;
            default:
                self::$assets = $eventData;
        }

        self::$assets = self::schemaValidate(self::$assets, self::eventsSchema[self::$shName]);

        self::build();

        return self::init();
    }

    public static function buildCategory($categoryRegistry = null)
    {
        if ($categoryRegistry == null) {
            $categoryRegistry = Category::init();
        }

        $build = array($categoryRegistry->getName());

        while ($categoryRegistry->getParentId() > 0) {
            $categoryRegistry = Category::getById($categoryRegistry->getParentId());
            $build[] = $categoryRegistry->getName();
        }
        return implode("|", array_reverse($build));
    }

    public static function buildMultiCategory($List)
    {
        self::$bMultiCat = [];
        
        if(is_array($List)){
            foreach ($List as $value) {
                Category::getById($value->term_id);
                self::buildSingleCategory();
            }
        }
        if (empty(self::$bMultiCat)) {
            self::$bMultiCat[] = "Default Category";
        }
        return implode("|", array_reverse(self::$bMultiCat));
    }

    public static function buildSingleCategory()
    {
        self::$bMultiCat[] = Category::getName();

        while (Category::getParentId() > 0) {
            Category::getById(Category::getParentId());

            self::$bMultiCat[] = Category::getName();
        }
    }

    public function toJson()
    {
        return Valid::toJson(self::$data);
    }
}
