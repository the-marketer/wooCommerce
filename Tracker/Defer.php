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

/**
 * Work that has to happen, but that the customer should not be made to wait for.
 *
 * Api::send is a blocking call with a 3 second timeout. Anything queued here runs on
 * shutdown, after fastcgi_finish_request has released the browser, so a slow or
 * unreachable API costs the shop nothing at checkout.
 */
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

    /** The common case: one API call, logged with the status it came back with. */
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
        /* A task is allowed to queue another one; keep draining until nothing is left. */
        while (!empty(self::$tasks)) {
            $tasks = self::$tasks;
            self::$tasks = array();

            self::closeConnection();

            foreach ($tasks as $task) {
                /* One failing task must not take the rest of the shutdown with it. */
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

    /**
     * Without fastcgi_finish_request (mod_php, some LiteSpeed setups) shutdown still
     * runs after the output has been sent, so the work happens either way.
     */
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
