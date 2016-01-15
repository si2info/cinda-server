<?php
namespace cinda\API;
use WP_Query;
use Box;
class Volunteer{
	
	private $suscriptions = array();
	private $id = null;
	public $email = "";
	public $nickname = "";
	public $device_id = "";
	public $avatar = "";
	public $contributions = 0;
	
	/**
	 * Construct
	 * @param int $id 
	 * @param string $nickname
	 * @param string $email
	 * @param string $device_id
	 */
	function __construct($id, $nickname=null, $email=null, $device_id=null){
		global $wpdb;
		
		if($id != null && is_numeric($id)){
			
			$this->id = intval( $id );
			$meta_values = get_post_meta( $id );
			
			if(isset($meta_values[CINDA_PREFIX.'email']))
				$this->email = $meta_values[CINDA_PREFIX.'email'][0];
			if(isset($meta_values[CINDA_PREFIX.'nickname']))
				$this->nickname = $meta_values[CINDA_PREFIX.'nickname'][0];
			if(isset($meta_values[CINDA_PREFIX.'device_id']))
				$this->device_id = $meta_values[CINDA_PREFIX.'device_id'][0];
			if(isset($meta_values[CINDA_PREFIX.'avatar_url']))
				$this->avatar = $meta_values[CINDA_PREFIX.'avatar_url'][0];
			
			$this->contributions = $wpdb->get_var("SELECT COUNT(p.ID) FROM ".$wpdb->prefix."posts AS p INNER JOIN ".$wpdb->prefix."postmeta AS m ON p.ID = m.post_id WHERE m.meta_key = '".CINDA_PREFIX."author_id' AND m.meta_value = ".$this->id.";");
			
		}
		
		else{
			$this->nickname = $nickname;
			$this->email = $email;
			$this->device_id = $device_id;
		}
	}
	
	/**
	 * Set suscriptions from DataBase
	 */
	private function set_suscriptions(){
		global $wpdb;
		$this->suscriptions = $wpdb->get_col("SELECT id_campaign FROM ". CINDA_TABLE_SUSCRIPTIONS_NAME ." WHERE id_volunteer = ". $this->id );
	}
	
	/**
	 * Get suscriptions
	 * @return array: Ids of campaigns where volunteer is subscribed
	 */
	public function get_suscriptions(){
		if(empty($this->suscriptions))
			$this->set_suscriptions();
		
		return $this->suscriptions;
	}
	
	/**
	 * Return volunteer id
	 * @return int
	 */
	public function get_id(){
		return $this->id;
	}
	
	/**
	 * Set number of contributions for this volunteer
	 */
	public function set_contributions($num_contributions){
		$this->contributions = intval( $num_contributions );
	}
	
	/**
	 * Register or login an volunteer
	 * Need $_POST values: 'name', 'surname', 'email', 'device-id'
	 */
	public static function register_volunteer(){
		global $wpdb;
		global $post;
		
		// If data send by POST
		if(isset($_POST) && !empty($_POST)){
			
			// If email not is empty
			if(isset($_POST['email']) && !empty($_POST['email'])){
				// Generate Token
				$token = sha1( $_POST['email'] . date('YmdGis') );

				// if user exists
				if( $user_id = self::volunteer_exists( $_POST['email'] ) ){
					$res = $wpdb->get_results( 'SELECT token FROM '.  CINDA_TABLE_TOKENS_NAME .' WHERE id_volunteer = '.$user_id, 'ARRAY_A' );
					
					// If only exists one result
					if( 1 == count($res) ){
						$token = $res[0]['token'];
					}
					
					// If exists zero or more than one result
					else{
						// Delete duplicated tokens
						if( 1 < count($res) )
							$wpdb->delete( CINDA_TABLE_TOKENS_NAME, array('id_volunteer' => $user_id), array( '%d' ) );
						
						// Insert new token
						$wpdb->insert(CINDA_TABLE_TOKENS_NAME, array(
							'id_volunteer' => $user_id,
							'token' => $token
						));
					}
						
				// if user don't exists
				}else{
					
					// Create a volunteer
					$volunter = new self(null, $_POST['nickname'], $_POST['email'], $_POST['device_id']);
					// Save volunteer in DB
					if($volunter->save()){
						
						$user_id = $volunter->id;
						
						// insert new token
						$wpdb->insert(CINDA_TABLE_TOKENS_NAME, array( 
							'id_volunteer' => $user_id,
							'token' => $token
						));
						
					}else{
						// Volunter don't create
						die(json_encode(0));
					}
				}
				
				// Show response
				die( json_encode( array($user_id, $token ) ) ); 
				
			// Email not send
			}else{
				// No login (ERROR)
				die(json_encode(0));
			}

		// NO se han enviado datos por POST
		}else{
			die(json_encode(0));
		}

	}
	
	/**
	 * Return information about a volunteer.
	 */
	public static function get_volunteer(){
		global $wp;
		// TEMPORAL
		$vid = $wp->query_vars['vid'];
		if(!$vid)
			$vid = 335;
		
		$volunteer = new self($vid);
		
		echo json_encode( $volunteer );
		die();
	}
	
	/**
	 * Insert or update volunter info into database
	 * @return int 1: succes|0: error
	 */
	function save(){
		
		// Register user
		$post = array(
			'post_type' 	=> CINDA_PREFIX."volunteer",
			'post_title'    => $this->nickname . " (". $this->email .")",
			'post_status'   => 'publish',
			'post_author'   => 1
		);
		
		if( $this->id != NULL ){
			$post['ID'] = $this->id;
			if( !wp_update_post( $post ) )
				return 0; // not update post
		}else{
			$this->id = wp_insert_post($post);
		}
		
		// Update metas
		if($this->id){
			// Register fields (meta values)
			// NAME
			update_post_meta($this->id, CINDA_PREFIX."nickname", $this->nickname);
			// EMAIL
			update_post_meta($this->id, CINDA_PREFIX."email", $this->email);
			// DEVICE ID
			update_post_meta($this->id, CINDA_PREFIX."device_id", $this->device_id);
			// Avatar
			$avatar = $this->set_avatar();

			update_post_meta($this->id, CINDA_PREFIX."avatar_url", $avatar );
			update_post_meta($this->id, CINDA_PREFIX."avatar_date", date('r') );
			
			return $this->id;
			
		}
		
		return 0;
	}
	
	/**
	 * Generate default profile image and call get_gravatar function
	 * @param int $size Size on pixels
	 */
	function set_avatar($size = 150){
		// Sanitize nickname and select the first character
		$words = explode(" ", $this->nickname);
		$char = "";
		foreach ($words as $word){
			$char .= substr( str_replace("_", "",  sanitize_string( $word ) ), 0, 1);
		}
		$name = md5( strtolower( trim( $this->email ) ) );
		$format = 'png';
		$filename = $name .".". $format;
	
		$image_url =  wp_upload_dir()['baseurl'] . "/avatars/" . generate_avatar($char, $name, 150, $format);
		
		$gravatar_url = get_gravatar($name, $size, $image_url);
		
		$image_final = wp_upload_dir()['basedir'] . "/avatars/" . $filename;
		
		if( file_put_contents( $image_final, file_get_contents($gravatar_url) ) )
			return $image_url;		
		else
			return false;
	}	
	
	/**
	 * Return a volunteer
	 * @param string $token
	 * @return \cinda\API\Volunteer|false
	 */
	public static function get_volunter_by_token($token){
		global $wpdb;

		if($id = self::get_volunter_id($token)){
			return new Volunteer( $id );
		}else{
			return false;
		}
		
	}
	
	/**
	 * Search if user exists
	 * @param string $email
	 * @return ID of volunteer or false (0)
	 */
	public static function volunteer_exists($email){
		// Select user from database
		$user = new WP_Query(
			array(
				"post_type"=>CINDA_PREFIX."volunteer",
				"meta_key"=>CINDA_PREFIX."email",
				"meta_value"=> $email
			)
		);
	
		if($user->post_count == 1)
			return $user->posts[0]->ID;
		else
			return 0;
	}
	
	/**
	 * Update meta_value of Endpoint for a volunteer (For notifications)
	 */
	public static function update_endpoint(){
		
		if(!isset($_POST['token']) || empty($_POST['token']))
			die( json_encode( 100 ) );
		
		if(!isset($_POST['endpoint']) || empty($_POST['endpoint']))
			die( json_encode( 101 ) );
		
		$token = $_POST['token'];
		$endpoint = $_POST['endpoint'];
		$id_usuario = self::get_volunter_by_token($token);
		
		if($id_usuario){
			if(update_post_meta($id_usuario->get_id(), CINDA_PREFIX."endpoint", $endpoint))
				die( json_encode( 1 ) );
			else
				die( json_encode( 102 ) );
		}else
			die( json_encode( 103 ) );

	}
	
	
	/**
	 * Return an ID of volunteer if exists
	 * @param string $token
	 * @return number|0
	 */
	static function get_volunter_id($token){
		global $wpdb;
	
		$id = $wpdb->get_var("SELECT id_volunteer AS id FROM ". CINDA_TABLE_TOKENS_NAME ." WHERE token = '". $token ."';");
	
		if($id)
			return $id;
		else
			return 0;
	
	}
	
}

