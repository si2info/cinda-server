<?php

namespace cinda\CPT;

use cinda\API\Volunteer as API_Volunteer;

class Volunteer{

	public $args = array();
	public static $name = "volunteer";
	private $fields = array(
		'name',
		'surname',
		'email',
		'device_id'	
	);
	
	/**
	 * Function construct
	*/
	 function __construct(){
		$this->args = array(
			'labels' => array(
				'name' => __( 'Volunteers' ,'Cinda'),
				'singular_name' => __( 'Volunteer' ,'Cinda'),
				'add_new' => __( 'New Volunteer','Cinda'),
				'add_new_item' => __( 'Add new Volunteer','Cinda')
			),
			'description' => "",
			'public' => true,
			'has_archive' => false,
			'show_ui' => true,
			'show_in_nav_menus' => false,
			'show_in_admin_bar' => false,
			'show_in_menu' => 'edit.php?post_type='. self::$name,
			'menu_position' => 8,
			'menu_icon' => 'dashicons-clipboard',
			'supports' => array(
				'title'
			),
			'capabilities' => array(
            	'create_posts' => false
	        )
		);
		// Actions
		add_action( 'init', array($this,'register') );
		add_action( 'admin_menu', array($this,'admin_menu') );
		//add_action( 'save_post', array($this,"save_meta"), 10, 3);
	}

	
	/**
	 * Register the custom post type
	 */
	public function register(){
		// Register post type
		register_post_type(CINDA_PREFIX . self::$name, $this->args );

		// Generate metabox (Formulario)
		add_action( 'add_meta_boxes', array($this,'metaboxes') );

	}
	
	/**
	 * Create metabox
	 */
	public function metaboxes(){
		add_meta_box(CINDA_PREFIX . self::$name . '_metabox', __('Profile Data','Cinda'), array($this,'create_form'), CINDA_PREFIX . self::$name, 'advanced', 'default');
	}

	/**
	 * Create form
	 */
	public function create_form(){
		
		$fields = get_post_meta( get_the_ID() );
		$volunteer = new API_Volunteer(get_the_ID());
		$volunteer->save();
		include(CINDA_DIR . 'assets/views/volunteer/profile_data_table.php');
	}

	
	/**
	 * Add submenu page
	 */
	function admin_menu() {
		add_submenu_page(
				CINDA_PREFIX."menu", //'edit.php?post_type='. self::$name, 	// Parent slug
				__('Volunteers','Cinda'), 						// Page title
				__('Volunteers','Cinda'),						// Menu title
				'manage_options',						// Capability
				'edit.php?post_type='.CINDA_PREFIX.self::$name								// Slug
		);
	}
	
	/**
	 * Save meta
	 * @param int $post_id
	 */
	function save_meta($post_id){
		global $post;
		if(self::$name == $post->post_type){
			// NAME
			if(isset($_POST[CINDA_PREFIX."nickname"]) && $_POST[CINDA_PREFIX."nickname"] != "")
				update_post_meta($post_id, CINDA_PREFIX."nickname", $_POST[CINDA_PREFIX."nickname"]);
			// EMAIL
			if(isset($_POST[CINDA_PREFIX."email"]) && $_POST[CINDA_PREFIX."email"] != "")
				update_post_meta($post_id, CINDA_PREFIX."email", $_POST[CINDA_PREFIX."email"]);
			// DEVICE ID
			if(isset($_POST[CINDA_PREFIX."device-id"]) && $_POST[CINDA_PREFIX."device-id"] != "")
				update_post_meta($post_id, CINDA_PREFIX."device-id", $_POST[CINDA_PREFIX."device-id"]);
		}
		
	}
}