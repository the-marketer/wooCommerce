<?php
/**
 * @copyright   © EAX LEX SRL. All rights reserved.
 **/

namespace Mktr\Tracker\Model;

/**
 * @method static getId()
 * @method static getName()
 * @method static getParentId()
 */

class Category
{
    private static $init = null;
    private static $asset = null;

    private static $valueNames = array(
        'getId' => 'term_id',
        'getName' => 'name',
        'getParentId' => 'parent'
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
            $v = self::$valueNames[$name];
            return self::$asset->{$v};
        }
        return null;

    }
    public static function getById($id = null)
    {
        if ($id == null)
        {
            $id = get_queried_object()->term_id;
        }

        self::$asset = get_term_by('id', $id, 'product_cat');
        return self::init();
    }

}