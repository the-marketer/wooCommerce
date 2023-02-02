<?php
/**
 * @copyright   © EAX LEX SRL. All rights reserved.
 **/

namespace Mktr\Tracker\Routes;

use Mktr\Tracker\Valid;

class clearEvents
{
    private static $init = null;

    public static function init()
    {
        if (self::$init == null) {
            self::$init = new self();
        }
        return self::$init;
    }

    public static function execute()
    {
        Valid::setParam('mime-type', 'js');

        $eventData = WC()->session->get("ClearMktr");

        if (!empty($eventData)) {
            foreach ($eventData as $key => $value) {
                $eventData1 = WC()->session->get($key);

                foreach ($value as $value1) {
                    unset($eventData1[$value1]);
                }

                WC()->session->set($key, $eventData1);
            }
            WC()->session->set("ClearMktr", array());
        }

        return "console.log(2);";
    }
}
