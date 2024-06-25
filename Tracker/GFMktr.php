<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      Alexandru Buzica (EAX LEX S.R.L.) <b.alex@eax.ro>
 * @license     https://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

 namespace Mktr\Tracker;

class GFMktr extends \GFFeedAddOn {

	private static $_instance = null;
	protected $_version = MKTR_VERSION;
	protected $_min_gravityforms_version = '2.0';
	protected $_slug = 'mktr';
	protected $_path = 'mktr/mktr.php';
	protected $_full_path = __FILE__;
	protected $_url = 'http://www.gravityforms.com';
	protected $_title = 'Gravity Forms TheMarketer Add-On';
	protected $_short_title = 'TheMarketer';
	protected $_enable_rg_autoupgrade = true;
	protected $_config = array('status', 'tracking_key','rest_key','customer_id', 'allow_export_gravity');
	// protected $_capabilities_settings_page = 'gravityforms_mktr';
	// protected $_capabilities_form_settings = 'gravityforms_mktr';
	// protected $_capabilities_uninstall = 'gravityforms_mktr_uninstall';
	// protected $_capabilities = array( 'gravityforms_mktr', 'gravityforms_mktr_uninstall' );
	protected $sett = null;
	protected $_new_custom_fields = array();
	protected $_async_feed_processing = true;

	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new GFMktr();
		}
		return self::$_instance;
	}

	public function init() {
		parent::init();
	}

	public function get_menu_icon() {
		return file_get_contents( MKTR_DIR . '/assets/logo-partial.svg' );
	}

	public function styles() {
		$styles = array( );
		return array_merge( parent::styles(), $styles );
	}

    public function feed_list_columns() {
		return array('test');
	}
	public function is_feed_list_page() {
		return true;
	}
    
	public function initialize_api() {
		$sett = $this->get_plugin_settings();
		foreach ($sett as $data => $val) {
			if (empty($val)) {
				return false;
			}
		}
		return true;
	}
    
    public function is_feed_edit_page() { 
        return false;
    }

	public function can_create_feed() {
		return false;
	}

	public function can_duplicate_feed( $id ) {
		return false;
	}

	public function plugin_settings_fields() {
		return array(
			array(
				'title'       => '',
				'description' => $this->plugin_settings_description(),
				'fields'      => array(
					array(
						'name'          => 'status',
						'type'          => 'toggle',
						'label'  => esc_html__( 'Enable theMarketer Tracker', 'gf_mktr' ),
						'toggle_label'  => esc_html__( 'Enable theMarketer Tracker', 'gf_mktr' ),
						'feedback_callback' => array( $this, 'initialize_api' )
					),
					array(
						'name'              => 'tracking_key',
						'label'             => esc_html__( 'API Tracking Key', 'gf_mktr' ),
						'type'              => 'text',
						'class'             => 'medium',
						'feedback_callback' => array( $this, 'initialize_api' )
					),
					array(
						'name'              => 'rest_key',
						'label'             => esc_html__( 'API Rest Key', 'gf_mktr' ),
						'type'              => 'text',
						'class'             => 'large',
						'feedback_callback' => array( $this, 'initialize_api' )
					),
					array(
						'name'              => 'customer_id',
						'label'             => esc_html__( 'API Customer ID', 'gf_mktr' ),
						'type'              => 'text',
						'class'             => 'large',
						'feedback_callback' => array( $this, 'initialize_api' )
					),
					array(
						'name'          => 'allow_export_gravity',
						'type'          => 'toggle',
						'label'         => esc_html__( 'Export Gravity Form entries', 'gf_mktr' ),
						'toggle_label'  => esc_html__( 'Export Gravity Form entries', 'gf_mktr' ),
						'feedback_callback' => array( $this, 'initialize_api' )
					),
					array(
						'name' => 'extra_settings',
						'type' => 'html',
						'html' => array( $this, 'extra_settings' ),
					),
					array(
						'type'     => 'save',
						'messages' => array(
							'success' => esc_html__( 'TheMarketer settings have been updated.', 'gf_mktr' )
						),
					)
				)
			),
		);

	}

	public function extra_settings() {
		return '<p>Click here for <a class="mktr-button" href="'. \admin_url( 'admin.php?page=' .'mktr_gravity') .'" >Extra Settings </a></p>';
	}

	public function plugin_settings_description() {
		$description = '<p>Please create an account or log in if you have one already.<br />'.
            '<a class="mktr-button" href="https://app.themarketer.com/register?utm_campaign=woo_plugin" target="_blank">Create account <span class="icon mktr-arrow-right"></span></a>'.
            '<div class="mktr-content-left">Need help? <a href="https://themarketer.com/integrations/woocommerce" target="_blank"> Visit our help article <span class="icon mktr-export-2"></span></a></div></p>';
		
		if ( ! $this->initialize_api() ) {
			$description .= '<p>Gravity Forms TheMarketer Add-On requires your Tracking KEY, REST KEY and Customer ID,'.
            'which can be found in the "Technical integration" on your account "Settings"</p>';
		}

		return $description;
	}

	public function configure_addon_message() {
		$settings_label = sprintf( esc_html__( '%s Settings', 'gravityforms' ), $this->get_short_title() );
		$settings_link  = sprintf( '<a href="%s">%s</a>', esc_url( $this->get_plugin_settings_url() ), $settings_label );

		if ( is_null( $this->initialize_api() ) ) {
			return sprintf( esc_html__( 'To get started, please configure your %s.', 'gravityforms' ), $settings_link );
		}

		return sprintf( esc_html__( 'Please make sure you have entered valid API credentials on the %s page.', 'gf_mktr' ), $settings_link );

	}


	public function feed_settings_fields() {
		return array(
			array(
				'title'  => '',
				'fields' => $fields
			)
		);
	}
	public function update_plugin_settings( $sett ) {
		foreach ($sett as $data => $val) {
			Config::setValue($data, $val);
			$this->sett[$data] = $val;
		}
	}
	
	public function get_plugin_settings() {
		if ($this->sett == null) {
			$this->sett = array();
			foreach ($this->_config as $val) {
				$this->sett[$val] = Config::getValue($val);
			}
		}
		return $this->sett;
	}

	private function get_saved_plugin_settings() {
		return $this->get_plugin_settings();
	}

	public function upgrade( $previous_version ) {
		
	}
}
