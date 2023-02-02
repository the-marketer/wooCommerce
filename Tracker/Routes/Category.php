<?php
/**
 * @copyright   © EAX LEX SRL. All rights reserved.
 **/

namespace Mktr\Tracker\Routes;

class Category
{
    private static $init = null;

    private static $map = array(
        "fileName" => "categories",
        "secondName" => "category"
    );

    private static $cat = array();

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
            'taxonomy' => 'product_cat',
            'order' => 'DESC',
            'return' => 'ids'
        );

        $get = array();
        $cat = get_categories($args);

        foreach ($cat as $k=>$val)
        {
            $get[] = array(
                "name" => $val->name,
                "url" => get_term_link($val->slug, 'product_cat'),
                'id'=> $val->term_id,
                "hierarchy" => self::buildCategory($val),
                // "image_url" => $category->getImageUrl()
            );
        }



        return $get;
    }

    /** @noinspection PhpUndefinedMethodInspection */
    public static function buildCategory($categoryRegistry = null)
    {
        $build = array($categoryRegistry->name);

        while ($categoryRegistry->category_parent > 0) {
            $categoryRegistry = \Mktr\Tracker\Model\Category::getById($categoryRegistry->category_parent);
            $build[] = $categoryRegistry->getName();
        }

        return implode("|", array_reverse($build));
    }
    public static function build($category){

        $newList = array(
            "name" => $category->getName(),
            // "url" => self::$url. $category->getUrlPath().'.html',
            'id'=> $category->getId(),
            // "hierarchy" => self::hierarchy($category),
            "image_url" => $category->getImageUrl()
        );

        if (empty($newList["image_url"]))
        {
            unset($newList["image_url"]);
        }

        // self::$data[] = $newList;
    }
}