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

use Mktr\Tracker\Api;
use Mktr\Tracker\Observer;
use Mktr\Tracker\Valid;
use Mktr\Tracker\Config;
use Mktr\Tracker\Model\Order;

class saveOrder
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
        if (!function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        Valid::setParam('mime-type', 'js');

        $Order = Config::session()->get('saveOrder');
        $allGood = true;
        //$installed_plugins = get_plugins();
        $plug = 'mailpoet/mailpoet.php';

        // $installed = array_key_exists($plug , $installed_plugins ) || in_array($plug, $installed_plugins, true );
        $active    = is_plugin_active($plug);

        if (!empty($Order)) {
            foreach ($Order as $sOrder1)
            {
                Order::getById($sOrder1);

                $sOrder = Order::toArray();

                Api::send("save_order", $sOrder);

                if (Api::getStatus() != 200) {
                    $allGood = false;
                }

                if ($active && !empty($sOrder['email_address']))
                {
                    $val = Observer::getEmail($sOrder['email_address']);

                    $info = array(
                        "email" => $val['email_address']
                    );

                    $status = \MailPoet\Models\Subscriber::getWooCommerceSegmentSubscriber($val['email_address'])->status;

                    if ($status === \MailPoet\Models\Subscriber::STATUS_SUBSCRIBED)
                    {
                        $name = array();

                        if (!empty($val['firstname']))
                        {
                            $name[] = $val['firstname'];
                        }

                        if (!empty($val['lastname']))
                        {
                            $name[] = $val['lastname'];
                        }

                        if (empty($name))
                        {
                            $info["name"] = explode("@", $val['email_address'])[0];
                        } else {
                            $info["name"] = implode(" ", $name);
                        }
                        $user = get_user_by('email', $val['email_address']);
                        $phone = get_user_meta($user->ID, 'billing_phone', true);

                        if (!empty($phone)) {
                            $info["phone"] = $phone;
                        }

                        Api::send("add_subscriber", $info);
                    }

                    if (Api::getStatus() != 200) {
                        $allGood = false;
                    }
                }
            }

            if ($allGood)
            {
                Config::session()->set('saveOrder', array());
            }
        }
        return 'console.log('.(int) $allGood.','.json_encode(Api::getInfo(), true).');';
    }
}