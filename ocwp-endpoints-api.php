<?php
/*
Plugin Name: OCWP Endpoint API
Plugin URI: http://ocwp.org
Description: This plugin creates a custom post type and utilizes the WordPress Endpoint API to add new functionality
Version: 1.0
Author: Pixel Jar
Author URI: http://pixeljar.net

Concepts taken from the following sources:
- https://gist.github.com/2891111/c72000f243420bc37941121756acae4f6b45349e

Ideas Taken from:
- http://make.wordpress.org/plugins/2012/06/07/rewrite-endpoints-api/
- Pages 417-421 in Professional WordPress Plugin Development
*/

// SET UP PATH CONSTANTS
define( 'OCWP_EP',					'ocwp-endpoints-api' );
define( 'OCWP_EP_VERSION',			'1.0' );
define( 'OCWP_EP_URL',				plugin_dir_url( __FILE__ ) );
define( 'OCWP_EP_ABS',				plugin_dir_path( __FILE__ ) );
define( 'OCWP_EP_REL',				basename( dirname( __FILE__ ) ) );
define( 'OCWP_EP_LANG',				OCWP_EP_ABS.'i18n/' );
define( 'OCWP_EP_LIB',				OCWP_EP_ABS.'lib/' );
define( 'OCWP_EP_MB',				OCWP_EP_LIB.'custom-metaboxes/' );
define( 'OCWP_EP_CSS',				OCWP_EP_URL.'css/' );
define( 'OCWP_EP_JS',				OCWP_EP_URL.'js/' );
define( 'OCWP_EP_ADMIN_OPTIONS',	'ocwp-endpoints-api-options' );

/*
 * CPT Archive To Nav Menu Library
 *
 * @see https://github.com/klangley/cpt-archive-to-nav
/**/
if( ! class_exists( 'CPT_Archive_To_Nav' ) )
	require_once( OCWP_EP_LIB.'cpt-archive-to-nav.php' );

function ocwp_init() {

	/*
	 * Register a custom post type
	 *
	 * Supplied is a "reasonable" list of defaults
	 * @see register_post_type for full list of options for register_post_type
	 * @see add_post_type_support for full descriptions of 'supports' options
	 * @see get_post_type_capabilities for full list of available fine grained capabilities that are supported
	 * @see http://queryposts.com/function/register_post_type/
	/**/
	register_post_type(
		'ocwp-member',
		array(
			'labels' => array(
				'name' => __('OCWP Member'),
				'singular_name' => __('Member')
			),
			'description' => __('This is one of our own.'),
			'public' => true,
			'show_ui' => true,
			'hierarchical' => false,
			'has_archive' => true,
			'supports' => array(
				'title',
				'editor',
				'comments',
				'revisions',
				'trackbacks',
				'author',
				'excerpt',
				'page-attributes',
				'thumbnail',
				'custom-fields'
			),
			'capability_type' => 'post',
		)
	);

	/*
	 * Include Custom Metaboxes Library
	 *
	 * @see https://github.com/jaredatch/Custom-Metaboxes-and-Fields-for-WordPress
	/**/
	if( ! class_exists( 'cmb_Meta_Box' ) )
		require_once( OCWP_EP_MB.'init.php' );

	add_rewrite_endpoint( 'go-blog', EP_PERMALINK );
}
add_action( 'init', 'ocwp_init' );

/*
 * Define the metabox and field configurations.
 *
 * @param  array $meta_boxes
 * @return array
/**/
function ocwp_member_cpt_metaboxes( $meta_boxes ) {

	// Start with an underscore to hide fields from custom fields list
	$prefix = '_';

	$meta_boxes[] = array(
		'id'         => 'ocwp-member-meta',
		'title'      => 'OCWP Memeber Meta',
		'pages'      => array( 'ocwp-member', ), // Post type
		'context'    => 'normal',
		'priority'   => 'high',
		'show_names' => true, // Show field names on the left
		'fields'     => array(
			array(
				'name' => 'Blog URL',
				'desc' => 'Enter the URL for your blog here',
				'id'   => $prefix . 'blog_link',
				'type' => 'text',
			),
		),
	);

	return $meta_boxes;
}
add_filter( 'cmb_meta_boxes', 'ocwp_member_cpt_metaboxes' );

/*
 * Catch the new endpoint
 *
/**/
function ocwp_template_redirect() {
        global $wp_query;

        // if this is not a request for json or it's not a singular object then bail
        if ( ! isset( $wp_query->query_vars['go-blog'] ) || ! is_singular() || ! get_post_meta( get_queried_object_id(), '_blog_link', true ) )
                return;

        // output some JSON (normally you might include a template file here)
        ocwp_do_blog_redirect();
        exit;
}
add_action( 'template_redirect', 'ocwp_template_redirect' );

	/*
	 * redirect to the member's blog
	/**/
	function ocwp_do_blog_redirect() {

		global $wpdb;
		
		// timestamp for midnight today
		$meta_key = '_clicks-'.mktime( 0,0,0, date("n"), date("j"), date("Y") );

		// track that sucka!
		if( get_post_meta( get_queried_object_id(), $meta_key, true ) ) :
			$add_view = $wpdb->query(
				$wpdb->prepare( "
					UPDATE		{$wpdb->prefix}postmeta
					SET			meta_value=meta_value+1
					WHERE		meta_key=%s
					AND			post_id=%s
				", $meta_key, get_queried_object_id() )
			);
		else :
			update_post_meta( get_queried_object_id(), $meta_key, '1' );
		endif;

		// redirect using a 301 (permanent) redirect
		wp_redirect( get_post_meta( get_queried_object_id(), '_blog_link', true ), 301 );
		exit;
	}

	function ocwp_blog_link( $content ) {
		$blog_link = get_post_meta( get_queried_object_id(), '_blog_link', true );
		if( is_single() && get_post_type() == 'ocwp-member' && $blog_link ) :
			return $content.'<p>Visit my <a href="go-blog">blog</a>.</p>';
		endif;

		return $content;
	}
	add_filter( 'the_content', 'ocwp_blog_link' );

function ocwp_endpoints_activate() {
        // ensure our endpoint is added before flushing rewrite rules
        ocwp_init();
        // flush rewrite rules - only do this on activation as anything more frequent is bad!
        flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'ocwp_endpoints_activate' );

function ocwp_endpoints_deactivate() {
        // flush rules on deactivate as well so they're not left hanging around uselessly
        flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'ocwp_endpoints_deactivate' );
