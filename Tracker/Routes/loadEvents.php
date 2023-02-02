<?php
/**
 * @copyright   © EAX LEX SRL. All rights reserved.
 **/

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

        foreach (Events::observerGetEvents as $event=>$Name)
        {
            if (!$Name[0]) {
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

        //$lines[] = "console.log(1);";
        return implode(PHP_EOL, $lines);
    }
}