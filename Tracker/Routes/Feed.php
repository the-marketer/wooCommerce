<?php
/**
 * @copyright   © EAX LEX SRL. All rights reserved.
 **/

namespace Mktr\Tracker\Routes;

use Mktr\Tracker\Model\Product;
use Mktr\Tracker\Valid;

class Feed
{
    private static $init = null;

    private static $map = array(
        "fileName" => "products",
        "secondName" => "product"
    );

    public static function init()
    {
        if (self::$init == null) {
            self::$init = new self();
        }
        return self::$init;
    }

    public static function get($f = 'fileName'){
        if (isset(self::$map[$f]))
        {
            return self::$map[$f];
        }
        return null;
    }

    public static function execute()
    {
        $args = array(
            'order'   => 'ASC',
            'orderby' => 'ID',
            'return' => 'ids',
            'limit'   => 200,
            'paginate' => true,
            'paged' => 1,
        );

        $get = array();
        $toSkip = array();
        do {
            $products = wc_get_products($args);
            $pages = $products->max_num_pages;

            foreach ($products->products as $val)
            {
                Product::getById($val);

                $oo = array(
                    'id' => Product::getId(),
                    'sku' => Product::getSku(),
                    'name' => ['@cdata' => Product::getName()],
                    'description' => ['@cdata' => Product::getDescription()],
                    'url' => Product::getUrl(),
                    'main_image' => Product::getImage(),
                    'category' => [ '@cdata' => Product::getCat() ],
                    'brand' => ['@cdata' => Product::getBrand()],
                    'acquisition_price' => Product::getAcquisitionPrice(),
                    'price' => Valid::digit2(Product::getRegularPrice()),
                    'sale_price' => Valid::digit2(Product::getPrice()),
                    'sale_price_start_date' => Valid::correctDate(Product::getSpecialFromDate()),
                    'sale_price_end_date' => Valid::correctDate(Product::getSpecialToDate()),
                    'availability' => Product::getAvailability(),
                    'stock' => Product::getStock(),
                    'media_gallery' => Product::getImages(),
                    'variations' => array(
                        'variation' => Product::getVariation()
                    ),
                    'created_at' => Valid::correctDate(Product::getCreatedAt()),
                );

                foreach ($oo as $key =>$val1) {
                    if ($key == 'variations') {
                        if (empty($val1['variation'])) {
                            unset($oo[$key]);
                        }
                    } elseif ($key == 'media_gallery') {
                        if (empty($val1['image'])) {
                            unset($oo[$key]);
                        }
                    } else {
                        if (empty($val1) && $val1 != 0 || $val1 === null) {
                            unset($oo[$key]);
                        }
                    }
                }
                $get[] = $oo;

            }
            $args['paged']++;

        } while ($args['paged'] <= $pages);

        return $get;
    }
}