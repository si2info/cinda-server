<?php
namespace cinda;

use \cinda\CindaQuery;
use \cinda\API\API;
use \cinda\CPT\Campaign as CPT_Campaign;
use \cinda\CPT\Contribution as CPT_Contribution;
use \cinda\CPT\Volunteer as CPT_Volunteer;
use \cinda\CPT\Dictionary as CPT_Dictionary;
use \cinda\API\Tracking;
use \cinda\API\Campaign as API_Campaign;
use \cinda\API\Volunteer as API_Volunteer;
use \cinda\CindaShortcodes;

class Cinda{
	
	private $theme_uri;
	private $plugin_uri;
	
	/**
	 * Constuctor
	 */
	function __construct(){
		
		$this->load_classes();
		
		add_action( 'admin_enqueue_scripts', array($this,'admin_resources') );
		add_action( 'admin_menu', array($this,'admin_menu') );
		add_action('admin_init', array($this,'send_push') );
		
		// Create CPTs
		$CampaignCPT = new CPT_Campaign();
		$VolunteerCPT = new CPT_Volunteer();
		$ContributionCPT = new CPT_Contribution();
		$DictionaryCPT = new CPT_Dictionary();
		
		
		// Initialize API
		$CindaAPI = new API();
		// Initialize CSV-Export
		$CindaExport = new CindaCSV();
		// Initialize Shortcodes
		$CindaShortcode = new CindaShortcodes();
		
		$this->theme_uri = get_template_directory();
		$this->plugin_uri = CINDA_DIR;
	}
	
	function theme_uri(){
		return $this->theme_uri;
	}
	
	function plugin_uri(){
		return $this->plugin_uri;
	}
	
	/**
	 * Instalation of plugin
	 */
	static function install(){
		// Create SQL Tables
		CindaQuery::create_tables();
		// Register the DB version for futures modifications
		add_option( 'CINDA_DATABASE_VERSION', CINDA_DATABASE_VERSION );
		// Redirect to Option page.
		wp_redirect( cinda_options_URL() );
	}
	
	
	/**
	 * Generate Admin menu
	 */
	function admin_menu() {
		// @source: https://codex.wordpress.org/Function_Reference/add_menu_page
		add_menu_page( 
			'CINDA: Volunteers Networks', 
			__('CINDA: Volunteers Networks','Cinda'), 
			'manage_options', 
			CINDA_PREFIX."menu", 
			array($this,'welcome_page'), 
			CINDA_URL.'/assets/images/icon.png',
			3 
		);
		// @source: https://codex.wordpress.org/Function_Reference/add_submenu_page
		add_submenu_page(
			CINDA_PREFIX."menu",
			__('Configuration','Cinda'),
			__('Configuration','Cinda'),
			'manage_options',
			CINDA_PREFIX."options",
			array($this,'options_page')
		);
		// @source: https://codex.wordpress.org/Function_Reference/add_submenu_page
		add_submenu_page(
			CINDA_PREFIX."menu", 
			__('CSV Export','Cinda'),
			__('CSV Export','Cinda'),
			'manage_options',
			'export',
			array($this, 'export_page')
		);
		// @source: https://codex.wordpress.org/Function_Reference/add_submenu_page
		add_submenu_page(
			CINDA_PREFIX."menu",
			__('Notifications','Cinda'),
			__('Notifications','Cinda'),
			'manage_options',
			'push',
			array($this, 'push_page')
		);
		// @source: https://codex.wordpress.org/Function_Reference/add_submenu_page
		add_submenu_page(
			null,
			__('Tracking','Cinda'),
			__('Tracking','Cinda'),
			'manage_options',
			CINDA_PREFIX . 'tracking',
			array($this, 'tracking_page')
		);
	}
	
	/**
	 * Load CSV Export view
	 */
	function export_page(){
		include( CINDA_DIR . 'assets/views/cinda/page_export.php' );
	}
	
	/**
	 * Load options view
	 */
	function options_page(){
		include( CINDA_DIR . 'assets/views/cinda/page_options.php' );
	}
	
	/**
	 * Load welcome view
	 */
	function welcome_page(){
		include( CINDA_DIR . 'assets/views/cinda/page_welcome.php' );
	}
	
	/**
	 * Load CSV Export view
	 */
	function push_page(){
		global $wpdb;
		$campaigns = $wpdb->get_results("SELECT p.ID AS id, p.post_title AS title FROM ".$wpdb->prefix."posts AS p WHERE p.post_type = '".CINDA_PREFIX."campaign' AND p.post_status = \"publish\";", ARRAY_A);
		include( CINDA_DIR . 'assets/views/cinda/page_push.php' );
	}
	
	/**
	 * Load welcome view
	 */
	function tracking_page(){
		
		if(Tracking::exists($_GET['id']))
			$tracking = new Tracking($_GET['id']);
		else 
			return;
		
		$campaign_name = (new API_Campaign( $tracking->get_idCampaign() ))->title;
		$author_name = (new API_Volunteer( $tracking->get_idVolunteer() ))->nickname;
		
		include( CINDA_DIR . 'assets/views/cinda/page_tracking.php' );
	}
	
	/**
	 * Send the Push
	 */
	function send_push(){
		
		if( !is_user_logged_in() || !current_user_can('manage_options') || !isset( $_POST[CINDA_PREFIX.'push_action'] ) ) 
			return 0;

		if( "parse" == $_POST[CINDA_PREFIX.'push_action']){
			
			require_once( CINDA_DIR . 'vendors/Parse/push.php' );
			
			$data = array();
			$where = array();
			$devices = array();
			
			if(isset($_POST[CINDA_PREFIX."push_send_to_cid"]) && !empty($_POST[CINDA_PREFIX."push_send_to_cid"])){
				global $wpdb;
				$devices = $wpdb->get_col( $wpdb->prepare("SELECT m.meta_value FROM ".$wpdb->prefix."post_meta WHERE m.meta_key = '".CINDA_PREFIX."endpoint' AND m.post_id IN (SELECT id_volunteer FROM ".CINDA_TABLE_SUSCRIPTIONS_NAME." WHERE id_campaign = ".$_POST[CINDA_PREFIX."push_send_to_cid"].")"));
			
				if(!is_array($devices))
					$devices = array();
			}
			
			if(isset($_POST[CINDA_PREFIX."push_title"]))
				$data['alert'] = $_POST[CINDA_PREFIX."push_title"];
				
			if(isset($_POST["pushmessage"]))
				$data["content"] = $_POST["pushmessage"];
			
			if(isset($_POST[CINDA_PREFIX."push_active_campaign"]) && isset($_POST[CINDA_PREFIX."push_cid"]))
				$data['cid'] = $_POST[CINDA_PREFIX."push_cid"];
			
			$result = sendPush($data, $devices);
			
		}

	}
	
	/**
	 * Register styles and javascript for the plugin
	 */
	function admin_resources() {
		// Font Awesome
		wp_register_style( 'font-awesome','https://maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css', false, '1.0.0' );
		wp_enqueue_style( 'font-awesome' );
		// jQuery Ui
		wp_enqueue_script('jquery-ui',plugins_url( 'Cinda/assets/js/jquery-ui.min.js' ));
		// jQuery SwitchButton
		wp_register_script('jQuery-switchButton',plugins_url( 'Cinda/assets/js/jquery.switchButton.js' ));
		wp_enqueue_script('jQuery-switchButton');
		wp_register_style('jQuery-switchButton',plugins_url( 'Cinda/assets/css/jquery.switchButton.css' ), false, '1.0.0' );
		wp_enqueue_style( 'jQuery-switchButton' );
		// Select2
		wp_register_script('select2-js',plugins_url( 'Cinda/assets/js/select2.full.min.js' ));
		wp_enqueue_script('select2-js');
		wp_register_style('select2-css',plugins_url( 'Cinda/assets/css/select2.min.css' ), false, '1.0.0' );
		wp_enqueue_style( 'select2-css' );
		// Custom javascript
		wp_enqueue_script('cinda_scripts',plugins_url( 'Cinda/assets/js/custom.js' ), null, null, true);
		// Api Google Maps
		wp_register_script("api-google-map-v3", "https://maps.googleapis.com/maps/api/js?v=3.exp&signed_in=true");
		wp_enqueue_script("api-google-map-v3");
		// WP Color Picker
		wp_enqueue_style( 'wp-color-picker');
		wp_enqueue_script( 'wp-color-picker');
		// Custom CSS
		wp_register_style( CINDA_PREFIX . 'admin_css',plugins_url( 'Cinda/assets/css/admin-style.css' ), false, '1.0.0' );
		wp_enqueue_style( CINDA_PREFIX . 'admin_css' );
	}
	
	/**
	 * Load all classes required for the functioning of the plugin
	 */
	function load_classes(){
		// Load Cinda Class
		require_once( CINDA_DIR . 'classes/CindaQuery.php' );
		require_once( CINDA_DIR . 'classes/CindaCSV.php' );
		require_once( CINDA_DIR . 'classes/CindaShortcodes.php' );
		// Load CPT Classes
		require_once( CINDA_DIR . 'classes/CPT/Campaign.php' );
		require_once( CINDA_DIR . 'classes/CPT/Dictionary.php' );
		require_once( CINDA_DIR . 'classes/CPT/Contribution.php' );
		require_once( CINDA_DIR . 'classes/CPT/Volunteer.php' );
		// Load API Classes
		require_once( CINDA_DIR . 'classes/API/API.php' );
		require_once( CINDA_DIR . 'classes/API/Campaign.php' );
		require_once( CINDA_DIR . 'classes/API/CampaignsList.php' );
		require_once( CINDA_DIR . 'classes/API/Contribution.php' );
		require_once( CINDA_DIR . 'classes/API/ContributionList.php' );
		require_once( CINDA_DIR . 'classes/API/Dictionary.php' );
		require_once( CINDA_DIR . 'classes/API/Opendata.php' );
		require_once( CINDA_DIR . 'classes/API/RealTime.php' );
		require_once( CINDA_DIR . 'classes/API/Tracking.php' );
		require_once( CINDA_DIR . 'classes/API/Volunteer.php' );
		require_once( CINDA_DIR . 'classes/API/VolunteerList.php' );
	}
	
	/**
	 * Save global options
	 * @return 1 true | 0 false
	 */
	function save_options(){
		
		if( !empty($_POST) ){
			// SERVER NAME
			if( isset($_POST[CINDA_PREFIX.'server_name']) && !empty($_POST[CINDA_PREFIX.'server_name']))
				update_option(CINDA_PREFIX.'server_name',$_POST[CINDA_PREFIX.'server_name']);
			// SERVER DESCRIPTION
			if( isset($_POST[CINDA_PREFIX.'server_description']) && !empty($_POST[CINDA_PREFIX.'server_description']))
				update_option(CINDA_PREFIX.'server_description',$_POST[CINDA_PREFIX.'server_description']);
			// SERVER URL
			if( isset($_POST[CINDA_PREFIX.'server_url']) && !empty($_POST[CINDA_PREFIX.'server_url']))
				update_option(CINDA_PREFIX.'server_url',$_POST[CINDA_PREFIX.'server_url']);
			// GOOGLE MAPS API
			if( isset($_POST[CINDA_PREFIX.'gmap_API']) && !empty($_POST[CINDA_PREFIX.'gmap_API']))
				update_option(CINDA_PREFIX.'gmap_API',$_POST[CINDA_PREFIX.'gmap_API']);
			// Options of notifications
			// PARSE URL
			if( isset($_POST[CINDA_PREFIX.'notification_parse_url']) && !empty($_POST[CINDA_PREFIX.'notification_parse_url']))
				update_option(CINDA_PREFIX.'notification_parse_url',$_POST[CINDA_PREFIX.'notification_parse_url']);
			// PARSE APP ID
			if( isset($_POST[CINDA_PREFIX.'notification_parse_app_id']) && !empty($_POST[CINDA_PREFIX.'notification_parse_app_id']))
				update_option(CINDA_PREFIX.'notification_parse_app_id',$_POST[CINDA_PREFIX.'notification_parse_app_id']);
			// PARSE APP KEY
			if( isset($_POST[CINDA_PREFIX.'notification_parse_app_key']) && !empty($_POST[CINDA_PREFIX.'notification_parse_app_key']))
				update_option(CINDA_PREFIX.'notification_parse_app_key',$_POST[CINDA_PREFIX.'notification_parse_app_key']);
			// PARSE CLIENT KEY
			if( isset($_POST[CINDA_PREFIX.'notification_parse_client_key']) && !empty($_POST[CINDA_PREFIX.'notification_parse_client_key']))
				update_option(CINDA_PREFIX.'notification_parse_client_key',$_POST[CINDA_PREFIX.'notification_parse_client_key']);

			return 1;
		}
		
		return 0;
		
	}
	
	/**
	 * Return global options of plugin
	 * (Server name, description, url...)
	 */
	function get_options(){
		$option = array();
		
		$option['server_name'] = get_option(CINDA_PREFIX.'server_name');
		$option['server_description'] = get_option(CINDA_PREFIX.'server_description');
		$option['server_url'] = get_option(CINDA_PREFIX.'server_url');
		$option['google_map_API'] = get_option(CINDA_PREFIX.'gmap_API');
		
		return $option;
	}
}