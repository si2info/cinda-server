<?php
use cinda\Cinda;
use cinda\API\Campaign;

add_image_size( 'cinda-thumbnail', 1024, 1024 );
add_image_size( 'cinda-image', 2048, 2048 );

// Add Multilingual Support
add_action( 'init', 'cinda_load_textdomain' );
function cinda_load_textdomain() {
	$domain = 'Cinda';
	$locale = apply_filters( 'plugin_locale', get_locale(), $domain );
	load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '/' . $domain . '-' . $locale . '.mo' );
	load_plugin_textdomain( $domain, FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
}

// Delete an input for the Model of Campaign
add_action( 'wp_ajax_cinda_field_delete', 'cinda_field_delete' );
function cinda_field_delete(){
	
	if(isset($_POST['id']) && isset($_POST['id_campaign'])){
		$id = $_POST['id'];
		$id_campaign = $_POST['id_campaign'];
		
		global $wpdb;
		
		echo $wpdb->delete(CINDA_TABLE_MODEL_NAME, array('id'=>$id,'id_campaign'=>$id_campaign));
		
	}else{
		echo 0;		
	}
	
	die();
	
}


/**
 * AJAX Function
 * Print HTML table for a new field line in model of campaign table
 */ 
add_action( 'wp_ajax_cinda_new_field', 'cinda_new_field' );
function cinda_new_field(){
	echo include( CINDA_DIR . 'assets/views/campaign/new_field.php');
	die();
}

/**
 * Return a formatted key
 */
add_action( 'wp_ajax_cinda_sanitize_fieldname', 'cinda_sanitize_fieldname' );
function cinda_sanitize_fieldname(){
	echo sanitize_key( $_POST['text'] );
	die();
}

/**
 * Delete a contribution
 */
add_action( 'wp_ajax_cinda_contribution_delete', 'cinda_contribution_delete' );
function cinda_contribution_delete(){
	global $wpdb;
	if( isset($_POST['ID']) ){
		
		$id = $_POST['ID'];
		$is_contribution = $wpdb->get_var("SELECT COUNT(ID) as num FROM ".$wpdb->prefix."posts WHERE ID=".$id." AND post_type = 'cinda_contribution' ");
		
		if($is_contribution){
			$wpdb->delete($wpdb->prefix."posts", array('ID'=>$id), array('%d'));
			$wpdb->delete($wpdb->prefix."postmeta", array('post_id'=>$id), array('%d'));
			
			echo 1;
		}else{
			echo 0;
		}
	}else{
		echo 0;
	}
	die();
}

/**
 * Get either a Gravatar URL or complete image tag for a specified email address.
 *
 * @param string $email The email address
 * @param string $s Size in pixels, defaults to 80px [ 1 - 2048 ]
 * @param string $d Default imageset to use [ 404 | mm | identicon | monsterid | wavatar ]
 * @param string $r Maximum rating (inclusive) [ g | pg | r | x ]
 * @param string $f ('y' | 'n') forze reload
 * @param boole $img True to return a complete IMG tag False for just the URL
 * @param array $atts Optional, additional key/value attributes to include in the IMG tag
 * @return String containing either just a URL or a complete image tag
 * @source http://gravatar.com/site/implement/images/php/
 */
function get_gravatar( $name, $s = 150, $d = 'mm', $r = 'g', $img = false, $atts = array() ) {
	
	$url = 'http://www.gravatar.com/avatar/';
	$url .= $name;
	$url .= "?s=$s&d=$d&r=$r";

	if ( $img ) {
		$url = '<img src="' . $url . '"';
		foreach ( $atts as $key => $val )
			$url .= ' ' . $key . '="' . $val . '"';
		$url .= ' />';
	}
	
	return $url;
}

/**
 * Create an avatar with initials
 * @param string $char Character that be print in image
 * @param string $name Name of image
 * @param number $s Size in pixels
 * @param string $format Output format
 */
function generate_avatar($char, $name, $s = 150, $format = "png"){
	
	require_once CINDA_DIR . 'vendors/GDText/Box.php';
	require_once CINDA_DIR . 'vendors/GDText/Color.php';
	
	$char = strtoupper($char);
	if(2 < strlen($char))
		$char = substr($char,0,2);
	$filename = $name . "." . $format;
	
	if(!file_exists(wp_upload_dir()['basedir'] . "/avatars/"))
		mkdir(wp_upload_dir()['basedir'] . "/avatars/", 0775);
	
	$private_uri = wp_upload_dir()['basedir'] . "/avatars/" . $filename;
	
	// Create image
	$img = @imagecreatetruecolor($s, $s);
	
	// Background color
	$red = (int)(rand(128,256));
	$green = (int)(rand(128,256));
	$blue = (int)(rand(128,256));
	// Create color
	$color = imagecolorallocate($img, $red, $green, $blue);
	
	// Asign color to background
	imagefill($img, 0, 0, $color);
	
	// Text Box (using GDText)
	$font_family = CINDA_DIR . 'assets/fonts/Oswald-Bold.ttf';
	$font_size = (int)($s / 2);
	$textbox = new GDText\Box($img);
	$textbox->setFontSize( $font_size );
	$textbox->setFontFace( $font_family );
	$textbox->setFontColor(new GDText\Color(250, 250, 250) ); // black
	$textbox->setBox(
		0,  // distance from left edge
		0,  // distance from top edge
		imagesx($img), // textbox width, equal to image width
		imagesy($img)  // textbox height, equal to image height
	);
	
	// now we have to align the text horizontally and vertically inside the textbox
	// the texbox covers whole image, so text will be centered relatively to it
	$textbox->setTextAlign('center', 'center');
	// it accepts multiline text
	$textbox->draw( $char );

	// Save image
	if("png" === $format){
		if(imagepng($img, $private_uri)){
			return $filename;
		}
	}else if("jpg" === $format){
		if(imagejpeg($img, $private_uri, 90)){
			return $filename;
		}
	}
	
	imagedestroy($img);
	
	return false;
	
}

/**
 * Replace acents and simbols
 * @param unknown $string
 * 
 */
function sanitize_string($string){
	
	$pattern = array(
		'/"|&|<|>| |¡|¢|£|¤/' => '_',
		'/¥|¦|§|¨|©|«|¬|­|®|¯/' => '_',
		'/±|&sup2;|&sup3;|´|µ|¶|·|÷/' => '_',
		'/°|&sup1;|»|&frac14;|&frac12;|&frac34;|¿/' => '_',
		'/à|á|â|ã|ä|å|æ|ª/' => 'a',
		'/À|Á|Â|Ã|Ä|Å|Æ/' => 'A',
		'/è|é|ê|ë|ð/' => 'e',
		'/È|É|Ê|Ë|Ð/' => 'E',
		'/ì|í|î|ï/' => 'i',
		'/Ì|Í|Î|Ï/' => 'I',
		'/ò|ó|ô|õ|ö|ø|º/' => 'o',
		'/Ò|Ó|Ô|Õ|Ö|Ø/' => 'O',
		'/ù|ú|û|ü/' => 'u',
		'/Ù|Ú|Û|Ü/' => 'U',
		'/ç/' => 'c',
		'/Ç/' => 'C',
		'/ý|ÿ/' => 'y',
		'/Ý|Ÿ/' => 'Y',
		'/ñ/' => 'n',
		'/Ñ/' => 'N',
		'/þ/' => 't',
		'/Þ/' => 'T',
		'/ß/' => 's',
	);
	
	return preg_replace( array_keys($pattern), array_values($pattern), $string);
}

/**
 * Get Configuration page url
 */
function cinda_options_URL(){
	return get_admin_url(null, 'admin.php?page='.CINDA_PREFIX.'options'); //  "/wp-admin/admin.php?page=".CINDA_PREFIX."options";
}

/**
 * Get Information page url
 */
function cinda_welcome_URL(){
	return get_admin_url(null, 'admin.php?page='.CINDA_PREFIX.'welcome'); // "/wp-admin/admin.php?page=".CINDA_PREFIX."welcome";
}


/* ADD CUSTOM TEMPLATE */
add_filter('single_template', 'cinda_custom_single_templates');
function cinda_custom_single_templates($single) {
	global $wp_query, $post, $CINDA;

	/* CAMPAIGN */
	if ($post->post_type == CINDA_PREFIX . "campaign"){
		$filename = 'page-campaign.php';
		// Theme template for Campaign in /cinda/templates/
		if(file_exists($CINDA->theme_uri() . "/cinda/" . $filename)){
			return $CINDA->theme_uri() . "/cinda/" . $filename;
		}
		// Plugin template for Campaign
		if(file_exists($CINDA->theme_uri() . "/" . $filename)){
			return $CINDA->theme_uri() . "/" . $filename;
			
		}
		// Plugin template for Campaign
		elseif(file_exists($CINDA->plugin_uri() . "/templates/" . $filename)){
			return $CINDA->plugin_uri() . "/templates/" . $filename;
		}
	}

	return $single;
}

add_filter( 'archive_template', 'cinda_custom_archive_templates' ) ;
function cinda_custom_archive_templates( $archive_template ) {
	global $post;

	if ( $post->post_type == CINDA_PREFIX . "campaign" ) {
		$filename = 'archive-campaign.php';
		return $CINDA->plugin_uri() . "/templates/" . $filename;
	}
}



add_action( 'get_header', 'cinda_header_hook' );
function cinda_header_hook( $name ) {
	
	if($name == 'cinda'){
		
		wp_enqueue_style( 'cinda-global', CINDA_URL . 'templates/css/global.css', false, "1.0", false );
		
		if(file_exists(get_template_directory_uri() . '/cinda/templates/css/global.css'))
			wp_enqueue_style( 'cinda-global-custom-theme', get_template_directory_uri() . '/cinda/templates/css/global.css', false, "1.0", false );
		
		if(is_single() && CINDA_PREFIX . 'campaign' == get_post_type()){
			
			global $campaign;
			
			$campaign = new Campaign( get_the_ID() );
			$campaign->set_contributions();
		}
		
	}
	
	return;
	
}
