<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      theMarketer
 * @license     https://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker;

class Defer
{
    private static $tasks = array();
    private static $hooked = false;
    private static $closed = false;

    public static function add($callback)
    {
        if (!is_callable($callback)) {
            return;
        }

        self::$tasks[] = $callback;

        if (!self::$hooked) {
            self::$hooked = true;
            add_action('shutdown', array(__CLASS__, 'run'), PHP_INT_MAX);
        }
    }

    public static function api($name, $data, $log = null)
    {
        self::add(function () use ($name, $data, $log) {
            Api::send($name, $data);

            if ($log !== null) {
                Logs::debug($data, $log);
            }
        });
    }

    public static function run()
    {
        while (!empty(self::$tasks)) {
            $tasks = self::$tasks;
            self::$tasks = array();

            self::closeConnection();

            foreach ($tasks as $task) {
                try {
                    call_user_func($task);
                } catch (\Exception $e) {
                    Logs::debug($e->getMessage(), 'defer_error');
                } catch (\Throwable $e) {
                    Logs::debug($e->getMessage(), 'defer_error');
                }
            }
        }
    }

    private static function closeConnection()
    {
        if (self::$closed) {
            return;
        }

        self::$closed = true;

        @ignore_user_abort(true);

        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }
    }
}
