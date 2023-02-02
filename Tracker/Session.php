<?php
/**
 * @copyright   © EAX LEX SRL. All rights reserved.
 **/

namespace Mktr\Tracker;

class Session
{
    private static $init = null;
    private static $_data = array();

    public static function init() {
        if (self::$init == null) {
            self::$init = new self();
        }
        return self::$init;
    }

    public function __get( $key ) {
        return self::get( $key );
    }

    public function __set( $key, $value ) {
        self::set( $key, $value );
    }

    public static function get( $key, $default = null ) {
        $key = sanitize_key( $key );
        return isset( self::$_data[ $key ] ) ? maybe_unserialize( self::$_data[ $key ] ) : $default;
    }

    public static function set($key, $value ) {
        if ( $value !== self::get($key) ) {
            self::$_data[$key] = maybe_serialize($value);
        }
    }
    public static function session()
    {
        return self::$_data;
    }
}
