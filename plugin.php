<?php 

//Reject if accessed directly
defined( 'ABSPATH' ) || die( 'Our survey says: ... X.' );

//Plugin code

/*
 * Overview
 * 
 * Request is a comment type
 * - initial comment (the request)
 * - replies (commentary on the request)
 * - approved/rejected(/postponed/fulfilled)
 * - bumping (karma)
 * - requester (author)
 * - requestees (target users)
 * - post id
 * 
 * All requests will be limited to post types
 * Standard requests are those with a set comment and set response action.
 * Standard requests can possibly have set target users (by role, etc.), limited requesters, limited post types.
 * 
 * fns
 * - x get requests (and replies)
 *   - get for post
 *   - get for requester
 *   - get for requestee
 *   - order by karma, date, reply count, reply date
 * - x get request
 * - x add request
 * - x add request reply
 * - x delete request
 * - x edit request
 * - x approve/reject request
 * - x status whitelist
 * - x change karma
 * 
 * - view requests
 */


/**
 * Get a path relative to the root of this plugin or relative to a given file path
 * 
 * @since 0.1.0
 * 
 * @param string $path - The path from the relative root.
 * @param bool/string $relative_file - The alternative filepath to use as the relative root.
 * @return string - The filepath.
 */
function bop_requests_plugin_path( $path = '', $relative_file = false ){
	$path = implode( DIRECTORY_SEPARATOR, explode( '/', ltrim( $path, '/' ) ) );
	$return  = '';
	if( $relative_file === false ){
		$return = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . $path;
	}else{
		$return = dirname( $relative_file ) . DIRECTORY_SEPARATOR . $path;
	}
	return $return;
}


require_once( bop_requests_plugin_path( 'class-bop-request.php' ) );
require_once( bop_requests_plugin_path( 'class-bop-request-query.php' ) );
require_once( bop_requests_plugin_path( 'functions.php' ) );
require_once( bop_requests_plugin_path( 'ajax.php' ) );


//Add page

/**
 * WP:
 * Fires before the administration menu loads in the admin.
 *
 * @since 1.5.0
 *
 * @param string $context Empty context.
 * 
 * Bop:
 * @since 0.1.0 Used to register the admin page that lists requests.
 */
add_action( 'admin_menu', function(){
	add_submenu_page( null, __( 'Requests', 'bop-requests' ), __( 'Requests', 'bop-requests' ), 'edit_posts', 'bop-requests.php', function(){
		$path = apply_filters( 'request_page_template.bop_requests', bop_requests_plugin_path( 'templates/requests-page.php' ) );
		require_once( $path );
	} );
}, 10, 0 );


//Add admin scripts

/**
 * WP:
 * Enqueue scripts for all admin pages.
 *
 * @since 2.8.0
 *
 * @param string $hook_suffix The current admin page.
 * 
 * Bop:
 * @since 0.1.0 Used to send admin js and css.
 */
add_action( 'admin_enqueue_scripts', function(){
	wp_register_script( 'bop-requests-admin', plugins_url( bop_requests_plugin_path( 'assets/js/admin.js' ) ), array( 'jquery' ), '0.1.0', true );
	wp_enqueue_script( 'bop-requests-admin' );
	
	wp_register_style( 'bop-requests-admin', plugins_url( bop_requests_plugin_path( 'assets/css/admin.css' ) ), array(), '0.1.0', 'all' );
	wp_enqueue_style( 'bop-requests-admin' );
}, 10, 0 );
