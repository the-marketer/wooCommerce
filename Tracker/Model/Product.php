<?php
/**
 * @copyright   © EAX LEX SRL. All rights reserved.
 **/

namespace Mktr\Tracker\Model;

use Mktr\Tracker\Config;
use Mktr\Tracker\Events;

/**
 * @method static getId()
 * @method static getName()
 * @method static getParentId()
 * @method static getSku()
 * @method static getAvailableVariations()
 * @method static getUrl()
 * @method static getImg()
 * @method static getStockQuantity()
 * @method static getIsInStock()
 * @method static getMainImgId()
 * @method static getGalleryImageIds()
 * @method static getSalePrice()
 * @method static getSaleRegularPrice()
 * @method static getDescription()
 * @method static getSpecialFromDate()
 * @method static getSpecialToDate()
 * @method static getCreatedAt()
 * @method static getModifiedAt()
 */
class Product
{
    private static $init = null;
    private static $asset = null;
    private static $data = array();
    private static $tax = null;

    private static $valueNames = array(
        'getId' => 'get_id',
        'getName' => 'get_name',
        'getParentId' => 'get_parent_id',
        'getSku' => 'get_sku',
        'getAvailableVariations' => 'get_available_variations',
        'getUrl' => 'get_permalink',
        'getImg' => 'get_image',
        'getStockQuantity' => 'get_stock_quantity',
        'getIsInStock' => 'is_in_stock',
        'getMainImgId' =>'get_image_id',
        'getGalleryImageIds' => 'get_gallery_image_ids',
        'getSalePrice'=>'get_sale_price',
        'getSaleRegularPrice' => 'get_regular_price',
        'getDescription' => 'get_description',
        'getSpecialFromDate' =>'get_date_on_sale_from',
        'getSpecialToDate' => 'get_date_on_sale_to',
        'getCreatedAt' =>'get_date_created',
        'getModifiedAt' => 'get_date_modified'
    );

    private static $varNames = array(
        'getId' => 'get_id',
        'getName' => 'get_name',
        'getParentId' => 'get_parent_id',
        'getSku' => 'get_sku'
    );

    private static $selfValue = array(
        "product_id" => "getId",
        "name" => "getName",
        // "parent_id" => "getParentId",
        "sku" => "getSku",
        "variation" => "getVariation"
    );
    private static $AcquisitionPriceMeta = null;

    public static function init()
    {
        if (self::$init == null) {
            self::$init = new self();
        }
        return self::$init;
    }

    public static function checkTax()
    {
        if (self::$tax === null){ self::$tax = wc_tax_enabled(); }
        return self::$tax;
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

        if (isset(self::$data[$name]))
        {
            return self::$data[$name];
        }

        if (isset(self::$valueNames[$name]))
        {
            $v = self::$valueNames[$name];
            self::$data[$name] = self::$asset->{$v}();
            return self::$data[$name];
        }
        return null;
    }

    public static function getVarValue($name, $var = null)
    {
        if (self::$asset == null){
            self::getById();
        }

        if (isset(self::$data[$name]))
        {
            return self::$data[$name];
        }

        if (isset(self::$varNames[$name]))
        {
            $v = self::$varNames[$name];
            self::$data[$name] = $var->{$v}();
            return self::$data[$name];
        }
        return null;
    }

    public static function getById($id = null)
    {
        if ($id == null)
        {
            $id = get_the_ID();
        }
        self::$data = array();
        self::$asset = wc_get_product($id);
        return self::init();
    }

    public static function getCreate()
    {
        self::getCreatedAt() === null ? self::getModifiedAt() : self::getCreatedAt();
    }

    public static function getAcquisitionPrice()
    {
        $id = self::getId();

        $margin = 0;

        if (self::$AcquisitionPriceMeta === null)
        {
            $margin = get_post_meta($id, '_wc_cog_cost' );

            if (empty($margin))
            {
                $margin = get_post_meta($id, '_alg_wc_cog_cost' );
            } else {
                self::$AcquisitionPriceMeta = '_wc_cog_cost';
            }

            if(!empty($margin)) {
                self::$AcquisitionPriceMeta = '_alg_wc_cog_cost';
            } else {
                $margin = 0;
            }
        } else if (self::$AcquisitionPriceMeta !== null && self::$AcquisitionPriceMeta !== false) {
            $margin = get_post_meta($id, self::$AcquisitionPriceMeta);
        }

        if (empty($margin))
        {
            $margin = 0;
        }

        return $margin;
    }

    public static function getCat()
    {
        return Events::buildMultiCategory(get_the_terms(self::getId(), 'product_cat'));
    }

    public static function getBrand()
    {
        $b = '';
        foreach (Config::getBrandAttribute() as $v)
        {
            $b = self::$asset->get_attribute($v);
            if (empty($b))
            {
                $b = self::$asset->get_attribute('pa_'.$v);
                if (!empty($b))
                {
                    break;
                }
            } else {
                break;
            }
        }
        return empty($b) ? "N/A" : $b;
    }
    public static function getPrice($check = false)
    {
        $p = 0;
        if (self::$asset->is_type('variable')) {
            $v = self::getAvailableVariations();
            foreach ($v as $val)
            {
                if ($p > $val['display_price'] || $p == 0 && $val['display_price'] != 0) {
                    $p = $val['display_price'];
                }
            }
        } else {
            $p = self::getSalePrice();
        }

        if (self::checkTax()) {
            $p = wc_get_price_including_tax(self::$asset, array('price' => $p));
        }

        return $check === true || $p >= '0' ? $p : self::getRegularPrice(true);
    }

    public static function getRegularPrice($check = false)
    {
        $p = 0;
        if (self::$asset->is_type('variable')) {
            $v = self::getAvailableVariations();
            foreach ($v as $val)
            {
                if ($p < $val['display_regular_price']) {
                    $p = $val['display_regular_price'];
                }
            }
        } else {
            $p = self::getSaleRegularPrice();
        }

        if (self::checkTax()) {
            $p = wc_get_price_including_tax(self::$asset, array('price' => $p));
        }
        return $check === true || $p >= '0'  ? $p : self::getPrice(true);
    }

    public static function getImage()
    {
       return wp_get_attachment_url(self::getMainImgId());
    }

    public static function getImages()
    {
        $list = array(
            'image' => array()
        );

        foreach (self::getGalleryImageIds() as $id)
        {
            $list['image'][] = wp_get_attachment_url($id);
        }

        return $list;
    }
    public static function getStock()
    {
        $MasterQty = self::getStockQuantity();

        if ($MasterQty < 0) {
            $stock = Config::getDefaultStock();
        } else if (self::getIsInStock() && $MasterQty == 0) {
            $stock = 2;
        } else if (self::getIsInStock()){
            $stock = 1;
        } else {
            $stock = 0;
        }
        return $stock;
    }

    public static function getAvailability()
    {
        return (int) self::getIsInStock();
    }

    public static function getVariation()
    {
        $lis = array();

        if (self::$asset->is_type('variable'))
        {
            $v = self::getAvailableVariations();
            foreach ($v as $val)
            {
                if ($val['variation_is_visible']) {
                    $attribute = [
                        'color' => null,
                        'size' => null
                    ];
                    foreach (Config::getColorAttribute() as $v)
                    {
                        if (isset($val['attributes']['attribute_'.$v]))
                        {
                            $attribute['color'] = $val['attributes']['attribute_'.$v];
                            break;
                        } else if (isset($val['attributes']['attribute_pa_'.$v]))
                        {
                            $attribute['color'] = $val['attributes']['attribute_pa_'.$v];
                            break;
                        }
                    }

                    foreach (Config::getSizeAttribute() as $v)
                    {
                        if (isset($val['attributes']['attribute_'.$v]))
                        {
                            $attribute['size'] = $val['attributes']['attribute_'.$v];
                            break;
                        } else if (isset($val['attributes']['attribute_pa_'.$v]))
                        {
                            $attribute['size'] = $val['attributes']['attribute_pa_'.$v];
                            break;
                        }
                    }

                    $MasterQty = $val['stock_quantity'] === null ? 0 : $val['stock_quantity'];

                    if ($MasterQty < 0) {
                        $stock = Config::getDefaultStock();
                    } else if ($val['is_in_stock'] && $MasterQty == 0) {
                        $stock = 2;
                    } else if ($val['is_in_stock']){
                        $stock = 1;
                    } else {
                        $stock = 0;
                    }

                    $v = array(
                        'id' => $val['variation_id'],
                        'sku' => $val['sku'],
                        'acquisition_price' => self::getAcquisitionPrice(),
                        'price' => $val['display_regular_price'],
                        'sale_price' => $val['display_price'],
                        'availability' => (int) $val['is_in_stock'],
                        'stock' => $stock,
                        'size' => $attribute['size'],
                        'color' => $attribute['color']
                    );

                    if (empty($v['size'])) {
                        unset($v['size']);
                    }

                    if (empty($v['color'])) {
                        unset($v['color']);
                    }

                    $lis[] = $v;
                }
            }
        }

        return $lis;
    }

    public static function toArray()
    {
        $data = array();

        foreach (self::$selfValue as $key=>$value) {
            $data[$key] = self::$value();
        }

        return $data;
    }
}