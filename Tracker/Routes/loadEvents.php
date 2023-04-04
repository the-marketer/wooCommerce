<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      Alexandru Buzica (EAX LEX S.R.L.) <b.alex@eax.ro>
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker\Routes;

use Mktr\Tracker\Events;
use Mktr\Tracker\Valid;

class loadEvents
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

        $lines = [];
		// var_dump(WC()->session);
        // $eventData1 = array();
        foreach (Events::observerGetEvents as $event=>$Name)
        {
            if (!$Name[0]) {
				// $eventData1[$event] = WC()->session->get($event);
                $eventData = WC()->session->get($event);
                if (!empty($eventData))
                {
                    foreach ($eventData as $value)
                    {
                        $lines[] = "dataLayer.push(".Events::getEvent($Name[1], $value)->toJson().");";
                    }
                }
                WC()->session->set($event, array());
            }
        }

        // $lines[] = "console.log(1);";
		// $lines[] = json_encode($eventData1);
        return implode(PHP_EOL, $lines);
    }
}