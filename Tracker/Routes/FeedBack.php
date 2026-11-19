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

use Mktr\Tracker\Valid;

class FeedBack
{
    private static $init = null;
    private static $map = array();

    public static function get($f = 'fileName')
    {
        if (isset(self::$map[$f])) {
            return self::$map[$f];
        }
        return null;
    }

    public static function init()
    {
        if (self::$init == null) {
            self::$init = new self();
        }
        return self::$init;
    }

    public static function execute()
    {
        Valid::setParam('mime-type', 'json');
        if (isset($_POST['rating']) || isset($_POST['message'])) {
            // Sent by assets/deactivation.js from the admin, which gets the nonce through wp_localize_script.
            $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
            if (!wp_verify_nonce($nonce, 'mktr_feedback')) {
                return array('status' => 'error');
            }

            $body = array(
                'rating' => null,
                'message' => null,
                'status' => 1,
                'platform' => \Mktr\Tracker\Run::platform(),
                't' => time()
            );

            if (isset($_POST['message'])) {
                $body['message'] = sanitize_text_field(wp_unslash($_POST['message']));
            }
            
            if (isset($_POST['rating'])) {
                $body['rating'] = absint(wp_unslash($_POST['rating']));
                \Mktr\Tracker\Config::setValue("rated", 1);
            }
            
            $d = \wp_remote_post('https://connector.themarketer.com/feedback/add',
                array(
                    'method'      => 'POST',
                    'timeout'     => 5,
                    'user-agent'  => 'mktr:' . \get_bloginfo( 'url' ),
                    'body' => $body
                )
            );

            return array('status' => 'succes');
        } else {
            // Settings switches called by theMarketer, authenticated with the REST key instead of a nonce.
            $lems = Valid::getParam('LEMS');
            $mailpoet_segment = Valid::getParam('MAILPOET');

            if (($lems !== null || $mailpoet_segment !== null) && !self::validRestKey()) {
                return array('status' => 'error');
            }

            if ($lems !== null) {
                \Mktr\Tracker\Config::setValue("lems", (int) $lems);
            }
            if ($mailpoet_segment !== null) {
                \Mktr\Tracker\Config::setValue("mailpoet_segment", (int) $mailpoet_segment);
            }

            if ($lems !== null || $mailpoet_segment !== null) {
                return array('status' => 'succes');
            }
        }
        return array('status' => 'error');
    }

    private static function validRestKey()
    {
        $restKey = (string) \Mktr\Tracker\Config::getRestKey();
        $key = Valid::getParam('key');

        return $restKey !== '' && is_string($key) && hash_equals($restKey, $key);
    }
}
