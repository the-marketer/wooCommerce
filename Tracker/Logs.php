<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      Alexandru Buzica (EAX LEX S.R.L.) <b.alex@eax.ro>
 * @license     https://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker;

class Logs
{
    private static $init = null;
    private static $data;
    public function __construct() {
        FileSystem::setWorkDirectory();

        $data = FileSystem::rFile('logs.json');
        if ($data !== '') {
            self::$data = json_decode($data, true);
        } else {
            self::$data = [];
        }
    }

    public static function debug($data, $name = 'log') {
        if (MKTR_DEBUG) {
            $d = self::init();
            $d->addTo($name, [ $data, Api::getInfo(), time()]);
            $d->save();
        }
    }

    public static function init() {
        if (self::$init == null) {
            self::$init = new self();
        }
        return self::$init;
    }

    public function __get($name) {
        if (!isset(self::$data[$name])) {
            self::$data[$name] = null;
        }

        return self::$data[$name];
    }

    public function __set($name, $value) {
        self::$data[$name] = $value;
    }

    public static function getData() {
        return self::$data;
    }

    public static function addTo($name, $value, $key = null) {
        if ($key === null) {
            self::$data[$name][] = $value;
        } else {
            self::$data[$name][$key] = $value;
        }
    }

    public static function del($name) {
        unset(self::$data[$name]);
    }

    public static function save() {
        FileSystem::writeFile('logs.json', Valid::toJson(self::$data));
    }
}
