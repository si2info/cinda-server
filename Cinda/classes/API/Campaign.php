<?php

namespace cinda\API;

use \cinda\CPT\Campaign as CPT_Campaign;
use \cinda\API\ContributionList as API_ContributionList;
use \cinda\API\Volunteer as API_Volunteer;
use \cinda\API\VolunteerList;
use \cinda\CindaQuery as CindaQuery;
use WP_Query;

class Campaign{
	/**
	 * @var integer
	 */
	public $ID;

	/**
	 * @var string
	 */
	public $title;

	/**
	 * @var string (url)
	 */
	public $image;
	
	/**
	 * @var string (url)
	 */
	public $cover;

	/**
	 * @var date
	 */
	public $last_modified;

	/**
	 * @var string
	 */
	public $date_start = "";

	/**
	 * @var string
	 */
	public $date_end = "";

	/**
	 * @var string
	 */
	public $description = "";
	
	/**
	 * Full description
	 */
	public $description_extended;
	
	/**
	 * @var boolean
	 */
	public $is_subscribed = false;

	/**
	 * @var Lat,Long
	 */
	public $geoposition = "";

	/**
	 * @var Int: kms
	 */
	public $radium = 0;

	/**
	 * Header color
	 * @var string (Ex: #012345, #FEDCBA)
	 */
	public $color = "";

	/**
	 * Geographical scope
	 */
	public $scope = "";
	
	/**
	 * @var array
	 */
	protected $model = null;
	
	/**
	 * @var array
	 */
	protected $contributions = null;
	
	/**
	 * @var integer
	 */
	public $contributions_number = "";
	
	/**
	 * @var array (Volunteer)
	 */
	public $volunteers_top;
	
	/**
	 * @var integer
	 */
	public $volunteers_number;
	
	/**
	 * @var bool
	 */
	public $tracking = false;
	
	/**
	 * Number of suscriptors
	 * @var integer
	 */
	public $suscriptions = 0;
	
	/**
	 * Constructor
	 * @param integer $id
	 */
	function __construct($id){
		global $wpdb;
		$this->ID = intval($id);
		$row = $wpdb->get_row("SELECT post_title, post_content, post_modified FROM ". $wpdb->prefix ."posts WHERE ID = ". $this->ID ." AND post_type = '".CINDA_PREFIX."campaign'");
	
		if($row){
			// Title
			$this->title = $row->post_title;
			$this->last_modified = $row->post_modified;
			// The image
			$this->image = wp_get_attachment_url(  get_post_meta( $this->ID, CINDA_PREFIX.'logo_image', true ) );
			if( ! $this->image )
				$this->image = ''; // default: CINDA_URL."/assets/images/no_photo.png";
			
			$this->cover = wp_get_attachment_url(  get_post_meta( $this->ID, CINDA_PREFIX.'cover_image', true ) );
			if( ! $this->cover )
				$this->cover = '';
		
			// Description
			$description = get_post_field('post_excerpt', $this->ID);
			if($description instanceof WP_Error || empty($description) || $description == "")
				$this->description = wp_trim_words( strip_tags( $row->post_content, "<b><strong><a><i><em><p><br><ul><ol><li>" ), 55, '...');
			else
				$this->description = $description;
			
			$this->description_extended = apply_filters( 'the_content',  $row->post_content );
			$this->description_extended = str_replace( ']]>', ']]&gt;', $this->description_extended );
			$this->description_extended = strip_tags($this->description_extended, "<b><strong><a><i><em><p><br><ul><ol><li>" );
			
			$meta = get_post_meta($this->ID);
		
			if(isset($meta[CINDA_PREFIX.'start_date']))
				$this->date_start = $meta[CINDA_PREFIX.'start_date'][0];
		
			if(isset($meta[CINDA_PREFIX.'end_date']))
				$this->date_end = $meta[CINDA_PREFIX.'end_date'][0];
		
			if(isset($meta[CINDA_PREFIX.'color']))
				$this->color = $meta[CINDA_PREFIX.'color'][0];
			else
				$this->color = CINDA_DEFAULT_COLOR;
		
			if(isset($meta[CINDA_PREFIX.'scope']))
				$this->scope = $meta[CINDA_PREFIX.'scope'][0];
		
			if(isset($meta[ CINDA_PREFIX . 'geoposition']))
				$this->geoposition = $meta[ CINDA_PREFIX . 'geoposition'][0];
		
			if(isset($meta[ CINDA_PREFIX . 'radium']))
				$this->radium = intval($meta[ CINDA_PREFIX . 'radium'][0]);
			
			if(isset($meta[ CINDA_PREFIX . 'tracking']))
				$this->tracking = $meta[ CINDA_PREFIX . 'tracking'][0];
			
			if($this->tracking == "" || $this->tracking == "false")
				$this->tracking = false;
			else
				$this->tracking = true;
		
			$this->volunteers_number = VolunteerList::get_volunteerNumber( $this->ID );
			$this->volunteers_top = VolunteerList::get_volunteerList(array('campaign'=>$this->ID, 'number' => $this->get_numberTopVolunteers() ));
		
			$this->suscriptions = intval( $wpdb->get_var("SELECT COUNT( DISTINCT id_volunteer ) FROM ". CINDA_TABLE_SUSCRIPTIONS_NAME ." WHERE id_campaign = ". $this->ID ) );
			
		}
	
	}
	
	/**
	 * Return The ID
	 * @return integer
	 */
	function get_ID(){
		return $this->ID;
	}
	
	/**
	 * Establish if a Volunteer is sucribed or not
	 * @param boolean $value
	 */
	function set_subscribed($value = true){
		$this->is_subscribed = $value;
	}
	
	
	/**
	 * Set number of contributions for this campaign
	 */
	function set_contributions_number(){
		global $wpdb;
		$this->contributions_number = intval( $wpdb->get_var("SELECT COUNT(ID) FROM ".$wpdb->prefix."posts WHERE post_parent = ".$this->ID) );
	}
	
	/**
	 * Return top of volunteer for this campaign
	 * @return number
	 */
	function get_numberTopVolunteers(){
	
		$this->set_contributions_number();
	
		if($this->contributions_number <= 0)
			$number = 0;
		else if($this->contributions_number > 0 && $this->contributions_number <= 10)
			$number = 1;
		else if($this->contributions_number > 10 && $this->contributions_number <= 50)
			$number = 2;
		else if($this->contributions_number > 50 && $this->contributions_number <= 100)
			$number = 3;
		else
			$number = 4;
	
		return $number;
	}
	
	/**
	 * Set model of data from database
	 */
	function set_model_from_db(){
		$this->model = CindaQuery::get_model( $this->ID );
	}
	
	/**
	 * Return the model
	 */
	function get_model(){
		if($this->model == null)
			$this->set_model_from_db();
	
		return $this->model;
	}
	
	/**
	 * Set the Contributions
	 */
	function set_contributions(){
		$contributionList = new API_ContributionList( array('campaign' => $this->ID ) );
		$this->contributions = $contributionList->get_contributions();
	}
	
	/**
	 * Get the contributions post
	 * @return WP_Query results
	 */
	function get_contributions($format='object'){
		if($this->contributions == null)
			$this->set_contributions();
	
		if($format == 'serialized'){
			$contributions = array();
			foreach($this->contributions as $contribution){
				$contributions[] = $contribution->serialize(false);
			}
			return $contributions;
		}
		
		return $this->contributions;
	}
	
	/**
	 * Subscribe an volunteer to an campaign
	 *
	 * @print Results:
	 *  · 1: Success
	 * 	· 0: No data send
	 * 	· -1: No exists volunteer or is duplicated
	 * 	· -2: Already exists
	 * 	· -3: Problem in suscription process (Database ERROR)
	 */
	public static function campaign_suscribe(){
		global $wp;
		global $wpdb;
	
		if(!empty($_POST) && isset($_POST['token'])){
			$token = $_POST['token'];
			$id_volunteer = API_Volunteer::get_volunter_id($token);
			$id_campaign = $wp->query_vars['cid'];
	
			if( $id_volunteer ){
	
				$is_suscribed = ( count( $wpdb->get_results("SELECT id_campaign FROM ". CINDA_TABLE_SUSCRIPTIONS_NAME ." WHERE id_volunteer = ". $id_volunteer ." AND id_campaign = ". $id_campaign ."") ) == 1 ) ? 1 : 0;
	
				if(!$is_suscribed){
	
					$result = $wpdb->insert(CINDA_TABLE_SUSCRIPTIONS_NAME, array(
							'id_volunteer' => $id_volunteer,
							'id_campaign' => $id_campaign
					));
	
					if($result)
						echo 1;
					else
						echo -3;
	
				}else{
					echo -2;
				}
	
			}else{
				echo -1;
			}
	
		}else{
			echo 0;
		}
	}
	
	/**
	 * Unsubscribe an volunteer from an campaign
	 *
	 * @print Results:
	 * 	· 1: Success
	 * 	· 0: No data send
	 * 	· -1: No exists volunteer or is duplicated
	 * 	· -2: Volunteer not subscribed
	 * 	· -3: Problem in unsuscription process (Database ERROR)
	 */
	public static function campaign_unsuscribe(){
		global $wp;
		global $wpdb;
	
		if(!empty($_POST) && isset($_POST['token'])){
			$token = $_POST['token'];
			$id_volunteer = API_Volunteer::get_volunter_id($token);
			$id_campaign = $wp->query_vars['cid'];
	
			if( $id_volunteer ){
	
				$is_suscribed = ( count( $wpdb->get_results("SELECT id_campaign FROM ". CINDA_TABLE_SUSCRIPTIONS_NAME ." WHERE id_volunteer = ". $id_volunteer ." AND id_campaign = ". $id_campaign ."","ARRAY_A") ) == 1 ) ? 1 : 0;
	
				if($is_suscribed){
	
					$result = $wpdb->delete(CINDA_TABLE_SUSCRIPTIONS_NAME, array(
							'id_volunteer' => $id_volunteer,
							'id_campaign' => $id_campaign
					), array('%d','%d'));
	
					if($result)
						echo 1;
					else
						echo -3;
	
				}else{
					echo -2;
				}
	
			}else{
				echo -1;
			}
	
		}else{
			echo 0;
		}
	}
	
	/**
	 * Function for API request
	 * return JSON encode of the Campaign Object
	 */
	public static function campaign_info(){
		global $wp;
	
		$campaign = new self( $wp->query_vars['cid'] );
	
		// Check if Volunteer is sucribed
		if( isset( $_POST['token'] ) ){
			if($volunteer = API_Volunteer::get_volunter_by_token( $_POST['token'] ) ){
				$suscriptions = $volunteer->get_suscriptions();
				if(in_array($campaign->ID, $suscriptions))
					$campaign->set_subscribed();
			}
		}
	
		echo json_encode( $campaign );
	}
	
	/**
	 * Imprime en JSON el modelo datos
	 * (Campos del formulario)
	 */
	public static function campaign_model(){
		global $wp;
		$campaign = new self( $wp->query_vars['cid'] );
		if( 0 < count( $campaign->get_model() ))
			echo json_encode($campaign->get_model());
		else
			echo 0;
	}
	
	/**
	 * Check if campaign exists
	 * @param int $id ID of campaign to check
	 */
	public static function campaign_exists($id){
		global $wpdb;
		
		if( $wpdb->get_var("SELECT COUNT(ID) AS num FROM ".$wpdb->prefix."posts WHERE ID = ".$id." AND post_type = '".CINDA_PREFIX."campaign'; ") == 1)
			return true;
		return false;
	}
}