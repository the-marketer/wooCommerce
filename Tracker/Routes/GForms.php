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

class GForms
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

    private static function getData($Form, $data) {
        $newData = [
            'email_address' => null,
            'lastname' => '',
            'firstname' => ''
        ];

        foreach ($Form['fields'] as $k => $v) {
            if (in_array($v->type, ['name'])) {
                foreach ($v->inputs as $kk => $vv) {
                    $rID = $vv['id'];
                    if (isset($data[$rID])) {
                        if (in_array(strtolower($vv['label']), ['last', 'suffix'])) {
                            $newData['lastname'] .= $data[$rID];
                        } else {
                            $newData['firstname'] .= $data[$rID];
                        }
                    }
                }
            } else if (in_array($v->type, ['email'])) {
                $rID = $v->inputs[0]['id'];
                if (isset($data[$rID])) {
                    $newData['email_address'] = $data[$rID];
                }
            }
        }
        return $newData;
    }

    public static function execute()
    {   
        $page = Valid::getParam('page');
        $limit = Valid::getParam('limit', 100);
        $stop = false;

        if (!empty($page)) {
            $stop = true;
        } else {
            $page = 1;
        }
        
        $get = array();
        $toSkip = array();

        $plug = 'gravityforms/gravityforms.php';

        $active = \is_plugin_active($plug);

        if ( ! class_exists( 'GFFormsModel' ) ) {
            require_once( ABSPATH . 'wp-content/plugins/gravityforms/forms_model.php' );
        }
        /*
        if ( ! class_exists( 'GFAPI' ) ) {
            require_once( ABSPATH . 'wp-content/plugins/gravityforms/includes/api.php' );
        }
        */
        if ( ! class_exists( 'GF_Query' ) ) {
            require_once( ABSPATH . 'wp-content/plugins/gravityforms/includes/query/class-gf-query.php' );
        }

        $table = \GFFormsModel::get_forms(1);
        
        foreach ($table as $kk => $vv) {
            $e = new \GF_Query($vv->id);
            $e->limit( $limit );
            $pg = $page;
            $ff = \GFFormsModel::get_form_meta( $vv->id );

            do {
                $e->page( $pg );
                $data = $e->get();
                $pages = $stop ? 0 : count($data);

                foreach ($data as $v) {
                    $newData = self::getData($ff, $v);
                    if (!empty($newData['email_address'])) {
                        $get[$newData['email_address']] = [ 
                            'first_name' => $newData['firstname'],
                            'last_name' => $newData['lastname'],
                            'email' => $newData['email_address']
                        ];
                    }
                }
                
                if ($stop) {
                    $pages = 0;
                }
                
                $pg++;
            } while (0 < $pages);   
        }

        $g = array();
        
        foreach($get as $v) { $g[] = $v; }

        $get = $g;
        
        return $get;
    }
}
