<?php
namespace cinda\API;

use \cinda\CindaQuery as CindaQuery;
use \cinda\API\Volunteer as API_Volunteer;
use \cinda\API\Campaign as API_Campaign;

class Contribution{
	
	public $ID;
	
	public $id_campaign;
	public $campaign_name;
	public $campaign_image;
	
	/**
	 * Contains the data model (Fields, type of data, etc...)
	 * @var array $model
	 */
	private $model;
	
	/**
	 * Contains the values for the data model fields
	 * @var array $data
	 */
	public $data = array();
	
	/**
	 * Constructor
	 * @param unknown $ID
	 */
	function __construct($ID){
		
		//if(self::contribution_exists($ID)){
			// Check if exists Token
			if(isset($_POST['token']))
				$id_volunteer = API_Volunteer::get_volunter_id( $_POST['token'] );
			// Auxiliar variables
			$post = get_post( $ID );
			// ATTENTION: All start with prefix 'cinda_'
			$meta_values = get_post_meta($ID);
			// Campaign ID
			$this->id_campaign = $post->post_parent;
			
			$campaign = new API_Campaign( $this->id_campaign );
			$this->campaign_name = $campaign->title;
			$this->campaign_image = $campaign->image;
			
			// Data Model
			$this->model = CindaQuery::get_model( $this->id_campaign );
			// Data Values
			// ATTENTION: These fields do not need the prefix 'cinda_'
			$this->ID = intval( $ID );
			$this->data['create_date'] = $post->post_date;
			// SET AUTHOR ID
			if(isset($meta_values[CINDA_PREFIX.'author_id'][0])){
				$this->data['author_id'] = intval( $meta_values[CINDA_PREFIX.'author_id'][0] );
			}else{
				$this->data['author_id'] = 0;
			}
			// SET AUTHOR NAME
			if($this->data['author_id']){
				$volunteer = new API_Volunteer($this->data['author_id']);
				$this->data['author_name'] = $volunteer->nickname;
				$this->data['author_image'] = $volunteer->avatar;
			}else{
				$this->data['author_name'] = __('unknown','Cinda');
			}
			// SET TRACKING IDecho $meta_values[CINDA_PREFIX.'tracking'][0];
			if( ( current_user_can('manage_options') || ( isset($id_volunteer) && $this->data['author_id'] == $id_volunteer) ) && isset($meta_values[CINDA_PREFIX.'tracking'][0]) && $meta_values[CINDA_PREFIX.'tracking'][0] != "false" ){
				$this->data['tracking'] = $meta_values[CINDA_PREFIX.'tracking'][0];
			}
			
			if( !isset($this->data['tracking']) || !$this->data['tracking'] )
				$this->data['tracking'] = 0;
			
			// If data model not is empty
			if(0 < count( $this->model ) ){
				// Go over model fields
				foreach ( $this->model as $field ){
					// If exists meta_value add to $data array
					if( isset( $meta_values[ CINDA_PREFIX . $field->field_name ] ) ){
						if ( $field->field_type == 'image' || $field->field_type == 'file' ){
							$this->data[ $field->field_name ] = wp_get_attachment_image_src( $meta_values[ CINDA_PREFIX . $field->field_name ][0], 'cinda-image' )[0];
							$this->data[ $field->field_name . "_thumbnail" ] = wp_get_attachment_image_src( $meta_values[ CINDA_PREFIX . $field->field_name ][0], 'cinda-thumbnail' )[0];
						}else
							$this->data[ $field->field_name ] = $meta_values[ CINDA_PREFIX . $field->field_name ][0];
					}else{
						$this->data[ $field->field_name ] = "";
					}
				}
			}
		//}
	
	}
	
	/**
	 * Get any property if this exists
	 * @param unknown
	 */
	public function __get($name) {
		if(isset($this->data[$name]))
			return $this->data[$name];
	}
	
	/**
	 * Set any property
	 * @param string $name
	 * @param string $value
	 */
	public function __set($name, $value) {
		$this->data[$name] = $value;
	}	
	
	/**
	 * Return data values
	 * @return array
	 */
	function get_data(){
		return $this->data;
	}
	
	/**
	 * Set model of data from database
	 */
	function set_model_from_db($id_campaign){
		$this->model = CindaQuery::get_model($id_campaign);
	}
	
	/**
	 * Return the data model
	 */
	function get_model(){
		if($this->model == null)
			$this->set_model_from_db( $this->id_campaign );
	
		return $this->model;
	}
	
	/**
	 * Print contribution based on an ID
	 */
	public static function get_contribution(){
		global $wp;
		$contribution = new self( $wp->query_vars['cid'] );
		die( json_encode( $contribution->serialize() ) );
	}
	
	/**
	 * Serialize object
	 * @param string $complete true for an contribution | false for contribution list
	 * @return array
	 */
	function serialize($complete=true){
		// Seralization complete
		if($complete){
			return $this->data; // TMP
		}
		// Serialization partial
		else{
			// If data model not is empty
			if(0 < count( $model = $this->get_model() ) ){
				$field_name = false;
				$field_name_extra = false;
				$image = false;
					
				// Go over model fields
				foreach ( $model as $field ){
						
					// If field_type is textarea, will establish this field as candidate to 'description' of contribution
					if(!$field_name && $field->field_type == 'textarea'){
						$field_name = $field->field_name;
					}
					// Else, if field_type is 'text', will establish this field as second candidate to 'description' of contribution.
					else if(!$field_name_extra && $field->field_type == 'text'){
						$field_name_extra = $field->field_name;
					}
						
					// If not exists image, and this field_type is image, will establish as image of contribution
					if( !$image && $field->field_type == 'image'){
						$image = wp_get_attachment_url( $this->data[$field->field_name] );
					}
					
				}
					
				// Description
				if( $field_name && $field_name != ""){
					$description = $this->data[$field_name];
				}else if( $field_name_extra && $field_name_extra != "" ){
					$description = $this->data[$field_name_extra];
				}else{
					$description = __('Description not available','Cinda');
				}
					
				// If not find an image, will establish the avatar of author as image of contribution
				if(!$image){
					$image = (new Volunteer( $this->data['author_id'] ))->avatar;
				}
			}else{
				$description = __('Description not available','Cinda');
			}
			// Serialize contribution
			return array(
				'id' => $this->ID,
				'id_campaign' => $this->id_campaign,
				'campaign_name' => $this->campaign_name,
				'campaign_image' => $this->campaign_image,
				'create_date' => $this->data['create_date'],
				'author_id' => $this->data['author_id'],
				'author_name'=> $this->data['author_name'],
				'description' => $description,
				'image' => $image,
				'tracking' => $this->data['tracking'],
			);
		}
	}
	
	/**
	 * Save contribution in DataBase
	 */
	public static function save(){
	
		global $wp;
		global $wpdb;
		$errors = array();
	
		// Check if exists data in $_POST and $_POST['token']
		if( !empty($_POST) && isset($_POST['token']) ){

			// Check if volunteer exists
			if( $volunteer = API_Volunteer::get_volunter_by_token( $_POST['token'] ) ){
				
				$id_campaign = $wp->query_vars['cid'];
	
				// Check if campaign exists
				if( API_Campaign::campaign_exists( $id_campaign ) ){
					
					$campaign = new API_Campaign( $id_campaign );
					$model = $campaign->get_model();
					$post = array(
						'post_title' =>$volunteer->nickname ." ".__('for','Cinda')." ". $campaign->title ." ".__('on','Cinda')." ".date('Y-m-d H:i:s'),
						'post_type' => CINDA_PREFIX . 'contribution',
						'post_parent' => $campaign->get_ID(),
						'post_status' => 'publish'
					);
					// Create POST
					$id_contribution = wp_insert_post( $post );
	
					// Add metakey author ID
					update_post_meta($id_contribution, CINDA_PREFIX.'author_id', $volunteer->get_ID() );
				
					if(isset($_POST['tracking'])){
						update_post_meta($id_contribution, CINDA_PREFIX.'tracking', $_POST['tracking'] );
					}
					
					// Add all metakeys
					foreach($model as $field){
						// Upload image
						if( $field->field_type == 'image' || $field->field_type == 'file'){
	
							// Load functions if not exists
							if ( ! function_exists( 'wp_handle_upload' ) )
								require_once( ABSPATH . 'wp-admin/includes/file.php' );
							if ( ! function_exists( 'wp_generate_attachment_metadata' ) )
								require_once( ABSPATH . 'wp-admin/includes/image.php' );
	
							// Check if file exists
							if( isset($_FILES[ $field->field_name ]) && !empty($_FILES[ $field->field_name ]) ){
								$file = $_FILES[ $field->field_name ];
								// Move file to Uploads folder
								$movefile = wp_handle_upload( $file, array( 'test_form' => false ) );
								// Success
								if ( $movefile && !isset( $movefile['error'] ) ) {
									$filetype = $movefile['type'];
									$filename = $movefile['file'];
									$upload_dir = wp_upload_dir();
									$attachment = array(
										'guid' => $upload_dir['url'] . '/' . basename( $filename ),
										'post_mime_type' => $filetype,
										'post_title' => preg_replace('/\.[^.]+$/', '', basename( $filename ) ),
										'post_content' => '',
										'post_status' => 'inherit'
									);
									$attachment_id = wp_insert_attachment( $attachment, $filename);
									$attachment_data = wp_generate_attachment_metadata( $attachment_id, $filename );
									wp_update_attachment_metadata( $attachment_id,  $attachment_data );
									update_post_meta( $id_contribution, CINDA_PREFIX.$field->field_name, $attachment_id );
								}
	
								// ERROR moving file
								else {
									$errors[] = __( $movefile['error'] ,'Cinda');
								}
									
							// ERROR empty image
							}else{
								if($field->field_required)
									$errors[] = __( 'Empty image or image not sent','Cinda');
							}

						}
	
						// Save meta key and value (POST META) only si exists
						else if( isset( $_POST[ $field->field_name ] )){
							update_post_meta($id_contribution, CINDA_PREFIX.$field->field_name, $_POST[ $field->field_name ]);
						}
	
					} // END Foreach
	
					// 404 Campaign
				}else{
					$errors[] = "Campaign not found";
				}
					
				// 404 Volunteer
			}else{
				$errors[] = "Volunteer not found";
			}
	
	
		}else{
	
			if( empty($_POST) )
				$errors[] = "No data send";
	
			if( !isset($_POST['token']) )
				$errors[] = "No token send";
	
		}
	
		// Returns values
		if( empty($errors) )
			die( json_encode(1) );
		else
			die( json_encode(0) );
	}
	
	
	public static function contribution_exists($id){
		global $wpdb;
		
		if(!$id)
			return false;
		
		if( $wpdb->get_var("SELECT COUNT(ID) AS num FROM ".$wpdb->prefix."posts WHERE ID = ".$id." AND post_type = '".CINDA_PREFIX."contribution'; "))
			return true;
		return false;
	}
}