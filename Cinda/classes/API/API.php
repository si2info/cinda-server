<?php

namespace cinda\API;

use \cinda\API\Campaign;
use \cinda\API\CampaignsList;
use \cinda\API\Dictionary;
use \cinda\API\Volunteer;
use \cinda\API\RealTime;
use \cinda\API\Tracking;

/**
 * Class CindaAPI
 * Description: Manage all endpoint of plugin.
 * Based in Pugs_API_Endopoint of Brian Fegter
 * @link http://coderrr.com/create-an-api-endpoint-in-wordpress/
 */
class API{
	
	private $prefix = 'cindaAPI/'; //'API/';
	
	protected $routing = array();

	/**
	 * Construct
	 */
	function __construct(){
		$this->set_routing();	
		add_filter('query_vars', array($this, 'add_query_vars'), 0);
		add_action('parse_request', array($this, 'sniff_request'), 0);
		add_action('init', array($this, 'add_endpoints'), 0);
	}
	
	/** Add public query vars
	 *	@param array $vars List of current public query vars
	 *	@return array $vars
	 */
	public function add_query_vars($vars){
		$vars[] = '__cindaAPI';
		$vars[] = 'format';
		$vars[] = 'action';
		$vars[] = 'class';
		$vars[] = 'cid'; // Campaign ID
		$vars[] = 'vid'; // Volunteer ID
		$vars[] = 'uid'; // User ID (Volunteer): Generally an email acount
		$vars[] = 'did'; // Dictionary ID
		$vars[] = 'tid'; // Tracking ID
		return $vars;
	}
	
	/** 
	 * Add API Endpoints
	 * Foreach hover all routes of $routing for create all rewrite rules.
	 */
	function add_endpoints(){
		if( 0 < count( $this->routing ) ){
			foreach ( $this->routing as $route ){
				add_rewrite_rule( $route['pattern'], $route['redirect'], $route['pos'] );
			}
		}
		
		// TEMP
		flush_rewrite_rules();
	}
	
	/**
	* Sniff
	* This is where we hijack all API requests
	* If $_GET['__cindaAPI'] is set, we kill WP and serve up pug bomb awesomeness
	* @return die if API request
	*/
	function sniff_request(){
		global $wp;
		if( isset($wp->query_vars['__cindaAPI']) && $wp->query_vars['__cindaAPI'] != ""){
			$this->handle_request();
			exit;
		}
	}
	
	/**
	 * Handle_request
	 * Check if class & action exists and execute it
	 */
	function handle_request(){
		global $wp;
		$class = '\cinda\API\\' . $wp->query_vars['class'];
		$action = $wp->query_vars['action'];
		$format = $wp->query_vars['format'];
		if( class_exists( $class, false ) ){
			if( is_callable( $class, $action ) ){
				
				if($format === 'json'){
					header('Content-type:application/json;charset=utf-8');
					$class::$action();
				}elseif($format === 'csv'){
					header("Content-type: text/csv");
					header("Content-Disposition: attachment; filename=export".date('Y-m-d_H-i').".csv");
					header("Pragma: no-cache");
					header("Expires: 0");
					$class::$action();
				}
				
				
			}else{
				die(json_encode(0));
			}
		}else{
			die(json_encode(0));
		}
	}
	
	/** 
	 * Return the routing array;
	 * @return array:
	 */
	function get_routing(){
		return $this->routing;
	}
	
	
	/**
	 * Set array of accepted rewrite rules (URLs)
	 */
	private function set_routing(){
		$this->routing = array(
			// Server data
			array(
				'pattern' => '^' . $this->prefix . 'server/info/?$',
				'redirect' => 'index.php?__cindaAPI=1&format=json&class=API&action=get_server_data',
				'pos'=>'top'
			),
			// Listado de Camapañas
			array(
				'pattern' => '^' . $this->prefix . 'campaigns/list/?$',
				'redirect' => 'index.php?__cindaAPI=1&format=json&class=CampaignsList&action=campaigns_list',
				'pos'=>'top'
			),
			// Campaign info
			array(
				'pattern' => '^' . $this->prefix . 'campaign/([0-9]+)/?$',
				'redirect' => 'index.php?__cindaAPI=1&format=json&class=Campaign&action=campaign_info&cid=$matches[1]',
				'pos'=>'top'
			),
			// Campaign model
			array(
				'pattern' => '^' . $this->prefix . 'campaign/([0-9]+)/model/?$',
				'redirect' => 'index.php?__cindaAPI=1&format=json&class=Campaign&action=campaign_model&cid=$matches[1]',
				'pos'=>'top'
			),
			// Campaign contributions
			array(
				'pattern' => '^' . $this->prefix . 'campaign/([0-9]+)/listData/?$',
				'redirect' => 'index.php?__cindaAPI=1&format=json&class=ContributionList&action=listData&cid=$matches[1]',
				'pos'=>'top'
			),
			// Send contribution
			array(
				'pattern' => '^' . $this->prefix . 'campaign/([0-9]+)/sendData/?$',
				'redirect' => 'index.php?__cindaAPI=1&format=json&class=Contribution&action=save&cid=$matches[1]',
				'pos'=>'top'
			),
			// Volunteers suscribed to a campaign
			array(
				'pattern' => '^' . $this->prefix . 'campaign/([0-9]+)/listVolunteers/?$',
				'redirect' => 'index.php?__cindaAPI=1&format=json&class=VolunteerList&action=volunteers_list&cid=$matches[1]',
				'pos'=>'top'
			),
			// Top volunteer suscribed to a campaign
			array(
				'pattern' => '^' . $this->prefix . 'topVolunteers/?$',
				'redirect' => 'index.php?__cindaAPI=1&format=json&class=VolunteerList&action=volunteers_list',
				'pos'=>'top'
			),
			// Volunteer suscription
			array(
				'pattern' => '^' . $this->prefix . 'campaign/([0-9]+)/suscribe/?$',
				'redirect' => 'index.php?__cindaAPI=1&format=json&class=Campaign&action=campaign_suscribe&cid=$matches[1]',
				'pos'=>'top'
			),
			// Volunteer unsuscription
			array(
				'pattern' => '^' . $this->prefix . 'campaign/([0-9]+)/unsuscribe/?$',
				'redirect' => 'index.php?__cindaAPI=1&format=json&class=Campaign&action=campaign_unsuscribe&cid=$matches[1]',
				'pos'=>'top'
			),
			// Volunteer register
			array(
				'pattern' => '^' . $this->prefix . 'volunteer/register/?$',
				'redirect' => 'index.php?__cindaAPI=1&format=json&class=Volunteer&action=register_volunteer',
				'pos'=>'top'
			),
			// Actualize volunteer endpoint
			array(
				'pattern' => '^' . $this->prefix . 'volunteer/update-endpoint/?$',
				'redirect' => 'index.php?__cindaAPI=1&format=json&class=Volunteer&action=update_endpoint',
				'pos'=>'top'
			),
			// Volunteer profile
			array(
				'pattern' => '^' . $this->prefix . 'volunteer/([0-9]+)/?$',
				'redirect' => 'index.php?__cindaAPI=1&format=json&class=Volunteer&action=get_volunteer&vid=$matches[1]',
				'pos'=>'top'
			),
			// Volunteer contributions
			array(
				'pattern' => '^' . $this->prefix . 'volunteer/([0-9]+)/listData/?$',
				'redirect' => 'index.php?__cindaAPI=1&format=json&class=ContributionList&action=listData&vid=$matches[1]',
				'pos'=>'top'
			),
			// Contribution data
			array(
				'pattern' => '^' . $this->prefix . 'contribution/([0-9]+)/?$',
				'redirect' => 'index.php?__cindaAPI=1&format=json&class=Contribution&action=get_contribution&cid=$matches[1]',
				'pos'=>'top'
			),
			// Contributions
			array(
				'pattern' => '^' . $this->prefix . 'realtime/contributions/?$',
				'redirect' => 'index.php?__cindaAPI=1&format=json&class=RealTime&action=get_contributions',
				'pos'=>'top'
			),
			// Nearby activity
			array(
				'pattern' => '^' . $this->prefix . 'realtime/nearby-activity/?$',
				'redirect' => 'index.php?__cindaAPI=1&format=json&class=RealTime&action=get_nearbyActivity',
				'pos'=>'top'
			),
			// Watchface
			array(
				'pattern' => '^' . $this->prefix . 'realtime/watchface/?$',
				'redirect' => 'index.php?__cindaAPI=1&format=json&class=RealTime&action=get_watchfaceData&vid=$matches[1]',
				'pos'=>'top'
			),
			// Get Dictionary by ID
			array(
				'pattern' => '^' . $this->prefix . 'dictionary/([0-9]+)/?$',
				'redirect' => 'index.php?__cindaAPI=1&format=json&class=Dictionary&action=get_dictionary&did=$matches[1]',
				'pos'=>'top'
			),
			// Get Tracking by ID
			array(
				'pattern' => '^' . $this->prefix . 'trackings/?$',
				'redirect' => 'index.php?__cindaAPI=1&format=json&class=Tracking&action=get_all_trackings',
				'pos'=>'top'
			),
			// Get Tracking by ID
			array(
				'pattern' => '^' . $this->prefix . 'tracking/([0-9]+)/?$',
				'redirect' => 'index.php?__cindaAPI=1&format=json&class=Tracking&action=get&tid=$matches[1]',
				'pos'=>'top'
			),
			// Send Tracking
			array(
				'pattern' => '^' . $this->prefix . 'tracking/send/?$',
				'redirect' => 'index.php?__cindaAPI=1&format=json&class=Tracking&action=save',
				'pos'=>'top'
			),
			// OpenData Campaign
			array(
				'pattern' => '^' . $this->prefix . 'opendata/campaigns/?$',
				'redirect' => 'index.php?__cindaAPI=1&format=json&class=Opendata&action=get_campaigns',
				'pos'=>'top'
			),
			// OpenData Campaign
			array(
				'pattern' => '^' . $this->prefix . 'opendata/contributions/?$',
				'redirect' => 'index.php?__cindaAPI=1&format=json&class=Opendata&action=get_contributions',
				'pos'=>'top'
			),
		);
	}
	
	/**
	 * Return info about this API Service
	 */
	function get_server_data(){
		$data = array();
		$data['name'] = get_option(CINDA_PREFIX.'server_name');
		$data['description'] = get_option(CINDA_PREFIX.'server_description');
		$data['url'] = get_option(CINDA_PREFIX.'server_url');
		$data['gmaps']['api'] = get_option(CINDA_PREFIX.'gmap_API');
		$data['parse']['api'] = get_option(CINDA_PREFIX.'notification_parse_app_id');
		$data['parse']['key'] = get_option(CINDA_PREFIX.'notification_parse_client_key');
		die( json_encode($data) );
	}
}