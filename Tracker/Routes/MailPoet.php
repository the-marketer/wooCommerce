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

use Mktr\Tracker\Model\Order;
use Mktr\Tracker\Config;
use Mktr\Tracker\Valid;

class MailPoet
{
    private static $init = null;

    private static $map = array(
        "fileName" => "contacts",
        "secondName" => "contact"
    );

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

    public static function get_customers($args) {
        $page = $args['page'];
        $limit = $args['limit'];
        $offset = (($page - 1) * $limit);
        $plug = 'mailpoet/mailpoet.php';
        $active    = \is_plugin_active($plug);
        if (!$active) {
            return [];
        }
        if (\MailPoet\Settings\SettingsController::getInstance()->get('woocommerce.optin_on_checkout.enabled') == 1) {
            try {
                $ids = Config::getMailPoetId();
                if (is_array ($ids)) {
                    $data = \MailPoet\Models\Subscriber::tableAlias('subscribers')
                    ->select('subscribers.*')
                    ->join( MP_SUBSCRIBER_SEGMENT_TABLE, 'relation.subscriber_id = subscribers.id', 'relation' )
                    ->whereIn('relation.segment_id', Config::getMailPoetId())
                    ->offset($offset)
                    ->limit($limit)
                    ->findMany();
                } else {
                    $data = \MailPoet\Models\Subscriber::tableAlias('subscribers')
                    ->select('subscribers.*')
                    ->join( MP_SUBSCRIBER_SEGMENT_TABLE, 'relation.subscriber_id = subscribers.id', 'relation' )
                    ->where('relation.segment_id', Config::getMailPoetId())
                    ->offset($offset)
                    ->limit($limit)
                    ->findMany();
                }
            } catch (\Exception $e){
                $data = false;
            }

            return $data;
            if ( $data !== false) {
                return $data;
            }
        }
        return \MailPoet\Models\Subscriber::offset($offset)
        ->limit($limit)
        ->findMany();
    }

    public static function execute()
    {   
        $page = Valid::getParam('page');
        $limit = Valid::getParam('limit', 100);
        
        $args = array(
            'fields' => 'customer_id',
            'page' => 1,
            'limit' => $limit
        );

        $stop = false;

        if (!empty($page)) {
            $stop = true;
            $args['page'] = $page;
        }

        $get = array();
        $toSkip = array();
        do {
            $data = self::get_customers($args);
            $pages = $stop ? 0 : count($data);

            foreach ($data as $v) {
                if ($v !== false) {
                    $status = $v->status;
                    if ($status === \MailPoet\Models\Subscriber::STATUS_SUBSCRIBED) {
                        $get[] = [ 
                            'first_name' => $v->first_name,
                            'last_name' => $v->last_name,
                            'email' => $v->email
                        ];
                    }
                }
            }
			
            if ($stop) {
                $pages = 0;
            }
            
            $args['page']++;
        } while (0 < $pages);
        return $get;
    }
}
