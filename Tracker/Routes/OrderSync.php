<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      theMarketer
 * @license     https://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker\Routes;

/**
 * Cron endpoint for order delivery: /mktr/api/OrderSync/?key=<REST key>
 * Kept apart from the Cron route, which rebuilds the feed and is too heavy to run often.
 */
class OrderSync
{
    private static $init = null;

    private static $map = array();

    public static function init()
    {
        if (self::$init == null) {
            self::$init = new self();
        }
        return self::$init;
    }

    public static function get($f = 'fileName')
    {
        if (isset(self::$map[$f])) {
            return self::$map[$f];
        }
        return null;
    }

    public static function execute()
    {
        return array(
            'sent' => \Mktr\Tracker\Model\OrderSync::retry(),
            'pending' => \Mktr\Tracker\Model\OrderSync::pendingCount(),
        );
    }
}
