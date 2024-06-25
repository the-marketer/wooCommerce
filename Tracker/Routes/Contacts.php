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

class Contacts
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
    private static function get_customers($args = null) {
		global $wpdb;
		$customer_lookup_table = $wpdb->prefix . 'wc_customer_lookup';
        $page = $args['page'];
        $limit = $args['limit'];

        $offset = (($page - 1) * $limit);
        
		$customer = $wpdb->get_results($wpdb->prepare( "SELECT * FROM {$customer_lookup_table} LIMIT " . $limit . " OFFSET " . $offset ), ARRAY_A);

		return $customer;
	}

    public static function execute1()
    {   
        $page = Valid::getParam('page');
        $limit = Valid::getParam('limit', 100);
        $args = array(
            'fields' => 'ID',
            'paged' => 1,
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
            $data = \WC_Data_Store::load( 'customer' )->query( $args );
            //$data = new \WC_Customer($args);
			var_dump($data);
            die();
            $pages = $stop ? 0 : count($data);
            foreach ($data as $v) {
                
                // $customer = new \WC_Customer( $v['customer_id'] );
                if (!empty($v['email'])) {
                    $get[] = [ 
                        'first_name' => $v['first_name'],
                        'last_name' => $v['last_name'],
                        'email' => $v['email']
                    ];
                }
            }
			
            if ($stop) {
                $pages = 0;
            }
            
            $args['page']++;
        } while (0 < $pages);
        return $get;
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
			//var_dump($data);
            //die();
            foreach ($data as $v) {
                // $customer = new \WC_Customer( $v['customer_id'] );
                if (!empty($v['email'])) {
                    $get[] = [ 
                        'first_name' => $v['first_name'],
                        'last_name' => $v['last_name'],
                        'email' => $v['email']
                    ];
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
