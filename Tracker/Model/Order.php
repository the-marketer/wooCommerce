<?php
/**
 * @copyright   © EAX LEX SRL. All rights reserved.
 **/

namespace Mktr\Tracker\Model;

use Automattic\WooCommerce\Admin\Overrides\OrderRefund;
use Mktr\Tracker\Config;
use WC_Order;

/**
 * @method static getId()
 * @method static getFirstName()
 * @method static getLastName()
 * @method static getAddress1()
 * @method static getAddress2()
 * @method static getParentId()
 * @method static getDateAt()
 */
class Order
{
    private static $init = null;
    public static $asset = null;

    private static $data = array();

    private static $refund = 0;
    /* "status" */
    private static $valueNames = array(
        'getId' => 'get_id',
        'getParentId' => 'get_parent_id',
        'getStatus' => 'get_status',
        // 'getRefunds' => 'get_refund_amount',
        'getDateAt'=> 'get_date_created',
        'getFirstName' => 'get_billing_first_name',
        'getLastName' => 'get_billing_last_name',
        'getEmail' => 'get_billing_email',
        'getPhone' => 'get_billing_phone',
        'getState' => 'get_billing_state',
        'getCity' => 'get_billing_city',
        'getAddress1' => 'get_billing_address_1',
        'getAddress2' => 'get_billing_address_2',
        'getDiscount' => 'get_discount_total',
        'getShipping' => 'get_shipping_total',
        'getTotal' => 'get_total',
        'getTax' => 'get_total_tax'
    );

    private static $selfValue = array(
        "number" => "getId",
        "email_address" => "getEmail",
        "phone" => "getPhone",
        "firstname" => "getFirstName",
        "lastname" => "getLastName",
        "city" => "getCity",
        "county" => "getState",
        "address" => "getAddress",
        "discount_value" => "getDiscount",
        "discount_code" => "getDiscountCode",
        "shipping" => "getShipping",
        "tax" => "getTax",
        "total_value" => "getTotal",
        "products" => "getProducts",
    );

    private static $extraValue = array(
        "order_no" => "getId",
        "order_status" => "getStatus",
        "refund_value" => "getRefund",
        "created_at" => "getDate",
        "email_address" => "getEmail",
        "phone" => "getPhone",
        "firstname" => "getFirstName",
        "lastname" => "getLastName",
        "city" => "getCity",
        "county" => "getState",
        "address" => "getAddress",
        "discount_value" => "getDiscount",
        "discount_code" => "getDiscountCode",
        "shipping" => "getShipping",
        "tax" => "getTax",
        "total_value" => "getTotal",
        "products" => "getProductsData",
    );

    public static function init()
    {
        if (self::$init == null) {
            self::$init = new self();
        }
        return self::$init;
    }

    public static function __callStatic($name, $arguments)
    {
        return self::getValue($name);
    }

    public function __call($name, $arguments)
    {
        return self::getValue($name);
    }

    public static function getValue($name)
    {
        if (self::$asset == null){
            self::getById();
        }

        if (isset(self::$valueNames[$name]))
        {
            // ->get_refund_amount()
            $v = self::$valueNames[$name];
            return self::$asset->{$v}('view');
        }
        return null;
    }

    public static function setRefund($val)
    {
        self::$refund = $val;
    }

    public static function getRefund()
    {
        return self::$refund;
    }

    public static function getDate()
    {
        return date(Config::$dateFormat, self::getDateAt()->getTimestamp());
    }

    public static function getAddress(){
        return self::getAddress1() . ' ' . self::getAddress2();
    }

    public static function getDiscountCode()
    {
        if (self::$asset->get_used_coupons())
        {
            $coupons = [];

            foreach (self::$asset->get_used_coupons() as $coupon)
            {
                $coupons[] = $coupon;
            }

            return implode(', ', $coupons);
        }
        return '';
    }

    public static function getProductsData()
    {
        $products = array();
        foreach (self::$asset->get_items() AS $itemId => $itemData)
        {
            $o = $itemData->get_data();
            Product::getById($o['product_id']);
            $products[] = array(
                "product_id" => $o['product_id'],
                "name" => Product::getName(),
                "url" => Product::getUrl(),
                "main_image" => Product::getImage(),
                "category" => Product::getCat(),
                "brand" => Product::getBrand(),
                "price" => Product::getRegularPrice(),
                "sale_price" => round($o['total'] + (isset($o['subtotal_tax']) ? $o['subtotal_tax'] : 0)),
                "quantity" => $o['quantity'],
                "variation_id" => Product::getId(),
                "variation_sku" => Product::getSku()
            );
        }
        return $products;
    }

    public static function getProducts()
    {
        $products = array();
        foreach (self::$asset->get_items() AS $itemId => $itemData)
        {
            $o = $itemData->get_data();
            Product::getById($o['product_id']);
            $products[] = array(
                "product_id" => $o['product_id'],
                "price" => round($o['total'] + (isset($o['subtotal_tax']) ? $o['subtotal_tax'] : 0)),
                "quantity" => $o['quantity'],
                "variation_sku" => Product::getSku()
            );
        }
        return $products;
    }

    public static function getById($id = null)
    {
        if ($id == null)
        {
            $id = get_the_ID();
        }
        // 73 72
        self::$asset = new WC_Order($id);
        self::$refund = 0;
        return self::init();
    }

    public static function toArray()
    {
        $data = array();
        foreach (self::$selfValue as $key=>$value) {
            $data[$key] = self::$value();
        }

        return $data;
    }

    public static function toExtraArray()
    {

        $data = array();
        foreach (self::$extraValue as $key=>$value)
        {
            $data[$key] = self::$value();
        }

        return $data;
    }
}