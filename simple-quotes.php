<?php
/*
Plugin Name: Simple Quotes
Plugin URI: http://plugins.findingsimple.com
Description: Build a library of Quotes.
Version: 1.0
Author: Finding Simple ( Jason Conroy & Brent Shepherd)
Author URI: http://findingsimple.com
License: GPL2
*/
/*
Copyright 2012  Finding Simple  (email : plugins@findingsimple.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if ( ! class_exists( 'Simple_Quotes' ) ) :

/**
 * So that themes and other plugins can customise the text domain, the Simple_Quotes
 * should not be initialized until after the plugins_loaded and after_setup_theme hooks.
 * However, it also needs to run early on the init hook.
 *
 * @author Jason Conroy <jason@findingsimple.com>
 * @package Simple Quotes
 * @since 1.0
 */
function initialize_quotes(){
	Simple_Quotes::init();
}
add_action( 'init', 'initialize_quotes', -1 );

/**
 * Plugin Main Class.
 *
 * @package Simple Quotes
 * @author Jason Conroy <jason@findingsimple.com>
 * @since 1.0
 */
class Simple_Quotes {

	static $text_domain;

	static $post_type_name;

	static $admin_screen_id;
	
	/**
	 * Initialise
	 */
	public static function init() {
		global $wp_version;

		self::$text_domain = apply_filters( 'simple_quotes_text_domain', 'Simple_Quotes' );

		self::$post_type_name = apply_filters( 'simple_quotes_post_type_name', 'quote' );

		self::$admin_screen_id = apply_filters( 'simple_quotes_admin_screen_id', 'quote' );

		add_action( 'init', array( __CLASS__, 'register' ) );
		
		add_filter( 'post_updated_messages', array( __CLASS__, 'updated_messages' ) );
		
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		
		add_action( 'save_post', array( __CLASS__, 'save_meta' ), 10, 1 );
		
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_styles_and_scripts' ) );
		
		add_shortcode( 'quote', array( __CLASS__, 'shortcode_quote') );
		
	}

	/**
	 * Register the post type
	 */
	public static function register() {
		
		$labels = array(
			'name' => _x('Quotes', 'post type general name', self::$text_domain ),
			'singular_name' => _x('Quote', 'post type singular name', self::$text_domain ),
			'add_new' => _x('Add New', 'quote', self::$text_domain ),
			'add_new_item' => __('Add New Quote', self::$text_domain ),
			'edit_item' => __('Edit Quote', self::$text_domain ),
			'new_item' => __('New Quote', self::$text_domain ),
			'view_item' => __('View Quote', self::$text_domain ),
			'search_items' => __('Search Quotes', self::$text_domain ),
			'not_found' =>  __('No quotes found', self::$text_domain ),
			'not_found_in_trash' => __('No quotes found in Trash', self::$text_domain ),
			'parent_item_colon' => ''
		);
		$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true, 
			'query_var' => true,
			'rewrite' => array( 'slug' => 'quote', 'with_front' => false ),
			'capability_type' => 'post',
			'hierarchical' => false,
			'menu_position' => null,
			'taxonomies' => array(''),
			'supports' => array('title', 'editor', 'thumbnail', 'custom-fields')
		); 

		register_post_type( 'quote' , $args );
	}

	/**
	 * Filter the "post updated" messages
	 *
	 * @param array $messages
	 * @return array
	 */
	public static function updated_messages( $messages ) {
		global $post;

		$messages['quote'] = array(
			0 => '', // Unused. Messages start at index 1.
			1 => sprintf( __('Quote updated. <a href="%s">View quote</a>', self::$text_domain ), esc_url( get_permalink($post->ID) ) ),
			2 => __('Custom field updated.', self::$text_domain ),
			3 => __('Custom field deleted.', self::$text_domain ),
			4 => __('Quote updated.', self::$text_domain ),
			/* translators: %s: date and time of the revision */
			5 => isset($_GET['revision']) ? sprintf( __('Quote restored to revision from %s', self::$text_domain ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6 => sprintf( __('Quote published. <a href="%s">View quote</a>', self::$text_domain ), esc_url( get_permalink($post->ID) ) ),
			7 => __('Quote saved.', self::$text_domain ),
			8 => sprintf( __('Quote submitted. <a target="_blank" href="%s">Preview bio</a>', self::$text_domain ), esc_url( add_query_arg( 'preview', 'true', get_permalink($post->ID) ) ) ),
			9 => sprintf( __('Quote scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview quotel</a>', self::$text_domain ),
			  // translators: Publish box date format, see http://php.net/date
			  date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ), esc_url( get_permalink($post->ID) ) ),
			10 => sprintf( __('Quote draft updated. <a target="_blank" href="%s">Preview quote</a>', self::$text_domain ), esc_url( add_query_arg( 'preview', 'true', get_permalink($post->ID) ) ) ),
		);

		return $messages;
	}

	/**
	 * Enqueues the necessary scripts and styles for the plugins
	 *
	 * @since 1.0
	 */
	public static function enqueue_admin_styles_and_scripts() {
				
		if ( is_admin() ) {
	
			wp_register_style( 'simple-quotes', self::get_url( '/css/simple-quotes-admin.css', __FILE__ ) , false, '1.0' );
			wp_enqueue_style( 'simple-quotes' );
		
		}
		
	}
	
	/**
	 * Add the citation meta box
	 *
	 * @wp-action add_meta_boxes
	 */
	public static function add_meta_box() {
		add_meta_box( 'quote-citation', __( 'Citation', self::$text_domain  ), array( __CLASS__, 'do_meta_box' ), 'quote', 'normal', 'high' );
	}

	/**
	 * Output the citation meta box HTML
	 *
	 * @param WP_Post $object Current post object
	 * @param array $box Metabox information
	 */
	public static function do_meta_box( $object, $box ) {
		wp_nonce_field( basename( __FILE__ ), 'quote-citation' );
?>

		<p>
			<label for="quote-citation-source-name"><?php _e( 'Source Name:', self::$text_domain ); ?></label>
			<br />
			<input type="text" name="quote-citation-source-name" id="quote-citation-source-name"
				value="<?php echo esc_attr( get_post_meta( $object->ID, 'quote-citation-source-name', true ) ); ?>"
				size="30" tabindex="30" style="width: 99%;" />
		</p>
		
		<p>
			<label for="quote-citation-source-url"><?php _e( 'Source URL:', self::$text_domain ); ?></label>
			<br />
			<input type="url" name="quote-citation-source-url" id="quote-citation-source-url"
				value="<?php echo esc_attr( get_post_meta( $object->ID, 'quote-citation-source-url', true ) ); ?>"
				size="30" tabindex="30" style="width: 99%;" />
		</p>

<?php
	}

	/**
	 * Save the citation metadata
	 *
	 * @wp-action save_post
	 * @param int $post_id The ID of the current post being saved.
	 */
	public static function save_meta( $post_id ) {
		$prefix = hybrid_get_prefix();

		/* Verify the nonce before proceeding. */
		if ( !isset( $_POST['quote-citation'] ) || !wp_verify_nonce( $_POST['quote-citation'], basename( __FILE__ ) ) )
			return $post_id;

		$meta = array(
			'quote-citation-source-name',
			'quote-citation-source-url'
		);

		foreach ( $meta as $meta_key ) {
			$new_meta_value = $_POST[$meta_key];

			/* Get the meta value of the custom field key. */
			$meta_value = get_post_meta( $post_id, $meta_key, true );

			/* If there is no new meta value but an old value exists, delete it. */
			if ( '' == $new_meta_value && $meta_value )
				delete_post_meta( $post_id, $meta_key, $meta_value );

			/* If a new meta value was added and there was no previous value, add it. */
			elseif ( $new_meta_value && '' == $meta_value )
				add_post_meta( $post_id, $meta_key, $new_meta_value, true );

			/* If the new meta value does not match the old value, update it. */
			elseif ( $new_meta_value && $new_meta_value != $meta_value )
				update_post_meta( $post_id, $meta_key, $new_meta_value );
		}
	}

	/**
	 * Method overloading
	 *
	 * Provides a "the_*" for the "get_*" methods. If the corresponding method
	 * does not exist, triggers an error.
	 *
	 * @param string $name Method name
	 * @param array $args Arguments to pass to method
	 */
	public static function __callStatic($name, $args) {
		$get_method = 'get_' . substr($name, 4);
		if (substr($name, 0, 4) === 'the_' && method_exists(__CLASS__, $get_method)) {
			echo call_user_func_array(array(__CLASS__, $get_method), $args);
			return;
		}

		// No luck finding the method, do the same as normal PHP calls
		$trace = debug_backtrace();
		$file = $trace[0]['file'];
		$line = $trace[0]['line'];
		trigger_error('Call to undefined method ' . __CLASS__ . '::' . $name . "() in $file on line $line", E_USER_ERROR);
	}

	/**#@+
	 * @internal Template tag for use in templates
	 */
	/**
	 * Get the testimonial source's name
	 *
	 * @param int $post_ID Post ID. Defaults to the current post's ID
	 */
	public static function get_source( $post_ID = 0 ) {
	
		if ( absint($post_ID) === 0 )
			$post_ID = $GLOBALS['post']->ID;

		return get_post_meta($post_ID, 'quote-citation-source-name', true);
		
	}

	/**
	 * Get the quote source's URL
	 *
	 * @param int $post_ID Post ID. Defaults to the current post's ID
	 */
	public static function get_source_url($post_ID = 0) {
	
		if ( absint($post_ID) === 0 )
			$post_ID = $GLOBALS['post']->ID;

		return get_post_meta($post_ID, 'quote-citation-source-url', true);
		
	}
	
	/**
	 * Get a link to the quote source
	 *
	 * Either returns the source name, or if the source URL has been set,
	 * returns a HTML link to the source.
	 *
	 * @param int $post_ID Post ID. Defaults to the current post's ID
	 */
	public static function get_source_link( $post_ID = 0 ) {
	
		$source = self::get_source( $post_ID );

		if ( empty( $source ) )
			return '';

		$url = self::get_source_url($post_ID);
		
		if ( !empty( $url ) )
			return sprintf('<a href="%1$s" title="%2$s">%2$s</a>', $url , $source );

		return $source;
		
	}
	/**#@-*/

	/**
	 * Build qtip shortcode.
	 *
	 * @since 1.0
	 *	
	 * Required arguments:
	 *  - link_text 
	 *  - link_title
	 *  - link_url
	 *  - tooltip_text
	 *
	 * If required arguments are missin there is no output
	 *
	 * @since 1.0
	 * @author Jason Conroy <jason@findingsimple.com>
	 * @package Simple Quotes
	 *
	 */
	 
	public static function shortcode_quote( $atts, $content = null ) {
	
		extract( shortcode_atts( 
			array(	'id' => ''
			) , $atts)
		);
		
		$content = '';
	
		return self::quotes_remove_wpautop($content);
	
	}

	/**
	 * Replaces WP autop formatting 
	 *
	 * @since 1.0
	 * @author Jason Conroy <jason@findingsimple.com>
	 * @package Simple Quotes
	 */
	public static function quotes_remove_wpautop($content) { 
		$content = do_shortcode( shortcode_unautop( $content ) ); 
		$content = preg_replace( '#^<\/p>|^<br \/>|<p>$#', '', $content);
		return $content;
	}
	
	/**
	 * Helper function to get the URL of a given file. 
	 * 
	 * As this plugin may be used as both a stand-alone plugin and as a submodule of 
	 * a theme, the standard WP API functions, like plugins_url() can not be used. 
	 *
	 * @since 1.0
	 * @return array $post_name => $post_content
	 */
	public static function get_url( $file ) {

		// Get the path of this file after the WP content directory
		$post_content_path = substr( dirname( __FILE__ ), strpos( __FILE__, basename( WP_CONTENT_DIR ) ) + strlen( basename( WP_CONTENT_DIR ) ) );

		// Return a content URL for this path & the specified file
		return content_url( $post_content_path . $file );
	}	

}

endif;