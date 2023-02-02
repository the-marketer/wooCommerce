<?php
/**
 * @copyright   © EAX LEX SRL. All rights reserved.
 **/

namespace Mktr\Tracker;

use Mktr\Tracker\Model\Category;
use Mktr\Tracker\Model\Product;

class Events
{
    private static $init = null;
    private static $shName = null;
    private static $data = array();

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
        $lines[] = vsprintf(Config::loader, Config::getKey());

        $lines[] = 'window.MktrDebug = function () { if (typeof dataLayer != undefined) { for (let i of dataLayer) { console.log("Mktr","Google",i); } } };';
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

        foreach (self::actions as $key=>$value) {
            if ($key() || $key === 'is_home' && is_front_page()) {
                $lines[] = "dataLayer.push(".self::getEvent($value)->toJson().");";
                break;
            }
        }

        $clear = WC()->session->get("ClearMktr");

        if ($clear === null) {
            $clear = array();
        }

        foreach (self::observerGetEvents as $event=>$Name)
        {
            $eventData = WC()->session->get($event);
            if (!empty($eventData))
            {
                foreach ($eventData as $key=>$value)
                {
                    $lines[] = "dataLayer.push(".self::getEvent($Name[1], $value)->toJson().");";
                    if (!$Name[0]) {
                        $clear[$event][$key] = $key;
                    }
                }

                if ($Name[0]) {
                    //WC()->session->set($event, array());
                    $loadJS[$event] = true;
                } /** @noinspection PhpStatementHasEmptyBodyInspection */ else {
                    // $clear[$event][$key] = "clear";
                    // WC()->session->set($event, array());
                }
            }
        }

        $baseURL = Config::getBaseURL();

        foreach ($loadJS as $k=>$v)
        {
            $lines[] = '(function(){ let add = document.createElement("script"); add.async = true; add.src = "'.$baseURL.'mktr/api/'.$k.'/"; let s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(add,s); })();';
        }

        if (!empty($clear)) {
            WC()->session->set("ClearMktr", $clear);

            $lines[] = '(function(){ let add = document.createElement("script"); add.async = true; add.src = "'.$baseURL.'mktr/api/clearEvents/"; let s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(add,s); })();';
        }

        $lines[] = 'setTimeout(window.MktrDebug, 1000);';

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
            if (isset($schema[$key])){
                if (is_array($val)) {
                    $newOut[$schema[$key]["@key"]] = self::schemaValidate($val, $schema[$key]["@schema"]);
                } else {
                    $newOut[$schema[$key]] = $val;
                }
            } else if (is_array($val)){
                $newOut[] = self::schemaValidate($val, $schema);
            }
        }

        return $newOut;
    }

    public static function getEvent($Name, $eventData = [])
    {
        if (empty(self::eventsName[$Name]))
        {
            return false;
        }

        self::$shName = self::eventsName[$Name];

        self::$data = array(
            "event" => $Name
        );

        self::$assets = array();

        switch (self::$shName){
            case "Category":
                self::$assets['category'] = self::buildCategory();
                break;
            case "Product":
                self::$assets['product_id'] = Product::getId();
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
        if ($categoryRegistry == null)
        {
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

        foreach ($List as $value) {
            Category::getById($value->term_id);
            self::buildSingleCategory();
        }

        if (empty(self::$bMultiCat))
        {
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

    public function toJson(){
        return Valid::toJson(self::$data);
    }
}