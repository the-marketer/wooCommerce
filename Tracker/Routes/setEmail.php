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

use Mktr\Tracker\Api;
use Mktr\Tracker\Valid;
use Mktr\Tracker\Config;

class setEmail
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

        $em = Config::session()->get('setEmail');

        $allGood = true;
        //$installed_plugins = get_plugins();
        $plug = 'mailpoet/mailpoet.php';

        // $installed = array_key_exists($plug , $installed_plugins ) || in_array($plug, $installed_plugins, true );
        $active    = is_plugin_active($plug);
        // $installed &&
        $check = array();
        if ($active) {
            $check = Config::session()->get('emailSend');
            $time = time();

            if ($check === null) { $check = []; }

            foreach ($em as $val) {
                
                if ($val['email_address'] === null) {
                    continue;
                }

                if ($check !== null && isset($check[$val['email_address']])) {
                    if (($time - $check[$val['email_address']]) <= 60) {
                        \Mktr\Tracker\Logs::debug($val['email_address'], 'emailSendBlockSetEmail'); 
                        continue;
                    }
                }
                $info = array( "email" => $val['email_address'] );
                // $status = \MailPoet\Models\Subscriber::findOne($val['email_address'])->status;
                $gSub = Config::getSubscriber($val['email_address']);

                if ($gSub !== false) {
                    $status = $gSub->status;
                } else {
                    $status = "NotFound";
                }

                if ($status === \MailPoet\Models\Subscriber::STATUS_SUBSCRIBED)
                {
                    $name = array();

                    if (!empty($val['firstname'])) {
                        $name[] = $val['firstname'];
                    }

                    if (!empty($val['lastname'])) {
                        $name[] = $val['lastname'];
                    }

                    if (empty($name)) {
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
                    \Mktr\Tracker\Logs::debug($info, 'set_email_add_subscriber');
                } else {
                    Api::send("remove_subscriber", $info);
                    \Mktr\Tracker\Logs::debug($info, 'set_email_remove_subscriber');
                }

                $check[$s->email] = $time;

                if (Api::getStatus() != 200) {
                    $allGood = false;
                }
            }
        }

        Config::session()->set('emailSend', $check);

        if ($allGood)
        {
            Config::session()->set('setPhone', array());
            Config::session()->set('setEmail', array());
        }
        
        /* return 'console.log('.(int)$allGood . json_encode($em, true).');'; */
		// return 'console.log('.(int)$allGood .');';
        return json_encode([(int) $allGood, Api::getInfo(), $em ], true);
    }
}