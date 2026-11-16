<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      Alexandru Buzica (EAX LEX S.R.L.) <b.alex@eax.ro>
 * @license     https://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker\Routes;

use Mktr\Tracker\Config;
use Mktr\Tracker\Events;
use Mktr\Tracker\Session;

/**
 * Cache compatible mode endpoint. The page script calls it only when something may be
 * waiting (hint cookie, changed cart hash, cart / wishlist actions, cart and checkout
 * pages), never on a plain page view. One request returns every queued dataLayer event
 * and runs the order / email delivery the legacy script did with separate requests.
 */
class syncEvents
{
    const ACTION = 'syncEvents';

    /** Answered on wp_loaded: the cart is loaded by then, and the main query and theme never run. */
    public static function maybeHandle()
    {
        if (!isset($_GET[Config::$name]) || sanitize_text_field(wp_unslash($_GET[Config::$name])) !== self::ACTION) {
            return;
        }

        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        nocache_headers();

        wp_send_json(self::execute());
    }

    public static function execute()
    {
        /* Plugin notices would corrupt the JSON, and this answer reaches every visitor. */
        ob_start();

        Session::$syncHint = false;
        $events = array();

        try {
            $events = loadEvents::execute(false);

            $pending = array();
            foreach (array('saveOrder', 'setEmail') as $event) {
                $eventData = Config::session()->get($event);
                if (empty($eventData)) {
                    continue;
                }
                $pending[$event] = true;
                foreach ($eventData as $value) {
                    $ev = Events::getEvent(Events::observerGetEvents[$event][1], $value);
                    if ($ev !== false) {
                        $events[] = $ev->toArray();
                    }
                }
            }

            /* The same delivery the legacy script triggered via ?mktr=saveOrder / ?mktr=setEmail;
               each one clears what it delivered from the session. */
            if (isset($pending['saveOrder'])) {
                saveOrder::execute();
            }
            if (isset($pending['setEmail'])) {
                setEmail::execute();
            }
        } catch (\Throwable $e) {
            if (defined('MKTR_DEBUG') && MKTR_DEBUG) {
                \Mktr\Tracker\Logs::debug(array($e->getMessage(), $e->getTraceAsString()), 'sync_events_error');
            }
        }

        Session::clearSyncHint();
        Session::save();

        ob_end_clean();

        return array_values($events);
    }
}
