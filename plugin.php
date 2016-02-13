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
} );


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
}, 1, 1 );


//CRUD functions

//CREATE

/*
 * 
 * 
 */
function bop_add_post_request( $post_id, $requestees, $content='', $type='custom', $author_id=null, $meta=array() ){
	if( ! is_numeric( $post_id ) || empty( $requestees ) )
		return false;
	
	$author_id = is_null( $author_id ) ? get_current_user_id() : $author_id;
	$meta['request_type'] = $type;
	$meta['karma_from_' . $author_id] = 1;
	
	$commentdata = array( 'comment_type'=>'request', 'comment_post_ID'=>$post_id, 'comment_approved'=>'hold', 'comment_karma'=>1, 'comment_content'=>$content, 'user_id'=>$author_id, 'comment_meta'=>$meta );
	-
	$commentdata = apply_filters( 'add_post_request.bop_requests', $commentdata );
	
	$r_id = wp_insert_comment( $commentdata );
	
	if( $r_id ){
		bop_update_post_request_requestees( $r_id, $requestees );
	}
}

/*
 * 
 * 
 */
function bop_add_request_reply( $parent_id, $content='', $author_id=null ){
	if( ! is_numeric( $parent_id ) || empty( $content ) )
		return false;
	
	$author_id = is_null( $author_id ) ? get_current_user_id() : $author_id;
	$parent = get_comment( $parent_id );
	
	$commentdata = array( 'comment_type'=>'request_reply', 'comment_post_ID'=>$parent->post_id, 'comment_approved'=>'approve', 'comment_content'=>$content, 'user_id'=>$author_id );
	
	$commentdata = apply_filters( 'add_request_reply.bop_requests', $commentdata );
	
	return wp_insert_comment( $commentdata );
}


//READ

/*
 * 
 * 
 */
function bop_get_request( $id, $format = 'Object' ){
	$comment = get_comment( $id );
	if( $comment->type != 'request' )
		return;
	
	switch( $format ){
		case 'Object':
			$comment = Bop_Request( $id );
		break;
		case 'raw':
			return $comment;
		break;
		default:
	}
	return apply_filters( 'get_request.bop_requests', $comment, $format );
}

/*
 * 
 * 
 */
function bop_get_requests( $query ){
	$query['type'] = 'request';
	
	$query = apply_filters( 'pre_get_requests.bop_requests', $query );
	
	return apply_filters( 'the_requests.bop_requests', WP_Comment_Query( $query ) );
}

/*
 * 
 * 
 */
function bop_get_request_replies( $query ){
	$query['type'] = 'request_reply';
	if( isset( $query['request_id'] ) ){
		$query['parent'] = $query['request_id'];
	}
	
	$query = apply_filters( 'pre_get_request_replies.bop_requests', $query );
	
	return apply_filters( 'the_request_replies.bop_requests', WP_Comment_Query( $query ) );
}

/*
 * 
 * 
 */
function _bop_requests_comment_query_clauses( $pieces, &$q ){
	global $wpdb;
	
	//Requestees
	$requestees = array();
	if( isset( $this->query_vars['requestee'] ) ){
		$requestees = array( $this->query_vars['requestee'] );
	}elseif( isset( $this->query_vars['requestees'] ) ){
		$requestees = (array)$this->query_vars['requestees'];
	}
	
	if( ! empty( $requestees ) ){
		$requestees_user_ids = array();
		$requestees_roles = array();
		foreach( $requestees as $r ){
			if( is_numeric( $r ) ){
				$requestees_user_ids[] = $r;
			}elseif( is_string( $r ) ){
				$requestees_roles[] = $r;
			}
		}
		$join = array(); 
		$join[] = " INNER JOIN {$wpdb->commentmeta} AS bopreq_cm ON (bopreq_cm.comment_id = comment_ID) ";
		$join[] = "	INNER JOIN {$wpdb->users} AS bopreq_u ON (bopreq_u.user_id = bopre_cm.meta_value) ";
		
		$where = array( 'relation'=>" OR " );
		
		if( ! empty ( $requestees_user_ids ) ){
			$where[] = $wpdb->prepare( " ( bopreq_u.user_id IN ( " . implode( ",", array_fill( 0, count( $requestees_user_ids ), "%d" ) ) . " ) ) ", $requestees_user_ids );
		}
		
		if( ! empty( $requestees_roles ) ){
			$join[] = " INNER JOIN {$wpdb->usermeta} AS bopreq_um ON (bopreq_u.ID = bopreq_um.user_id) ";
			$where[] = ($where ? $where . " OR " : "") . $wpdb->prepare( " ( bopreq_um.meta_key = 'requestee_role' AND bopreq_um.meta_value IN ( " . implode( ",", array_fill( 0, count( $requestees_roles ), "%s" ) ) . " ) ) ", $requestees_roles );
		}
		
		$new_clauses = array( 'join'=>$join, 'where'=>$where );
		
		/*
		 * 
		 * 
		 */
		$new_clauses = apply_filters( 'requestee_user_ids_where.bop_requests', $new_clauses, $requestee_user_ids, $requestee_roles, $pieces );
		
		$pieces['where'] .= " AND ( " . implode( $new_clauses['where']['relation'], $new_clauses['where'] ) . " ) ";
		$pieces['join'] .= " " . implode( " ", $new_clauses['join'] ) . " ";
	}
	
	return $pieces;
}
add_filter( 'comment_clauses', '_bop_requests_comment_query_clauses', 5, 2 );

/*
 * 
 * 
 */
function bop_request_statuses( $context=null ){
	$statuses = get_comment_statuses();
	return apply_filters( 'request_statuses.bop_requests', $statuses, $context );
}

/*
 * 
 * 
 */
function bop_get_users_allowed_request_karma( $user_id ){
	return apply_filters( 'get_users_allowed_request_karma.bop_requests', 1, $user_id );
}


//UPDATE

/*
 * 
 * 
 */
function bop_edit_request( $requestarr ){
	if( is_a( $requestarr, 'Bop_Request' ) ){
		$requestarr = $requestarr->array_for_db();
	}
	$requestarr = array_intersect_key( $requestarr, array( 'comment_ID'=>'', 'comment_post_ID'=>'', 'comment_karma'=>'', 'comment_content'=>'', 'user_id'=>'' ) );
	wp_update_comment( $requestarr );
}

/*
 * 
 * 
 */
function bop_update_post_request_requestees( $id, $requestees ){
	$old_user_ids = (array)get_comment_meta( $id, 'requestee_user_id' );
	$old_roles = (array)get_comment_meta( $id, 'requestee_role' );
	foreach( $requestees as $r ){
		if( is_numeric( $r ) ){
			$new_user_ids = $r;
		}elseif( is_string( $r ) ){
			$new_roles = $r;
		}
	}
	
	do_action( 'pre_update_requestees.bop_requests', $id, $new_user_ids, $new_user_roles, $old_user_ids, $old_user_roles );
	
	$del_ids = array_diff( $old_user_ids, $new_user_ids );
	$add_ids = array_diff( $new_user_ids, $old_user_ids );
	$del_roles = array_diff( $old_roles, $new_roles );
	$add_roles = array_diff( $new_roles, $old_roles );
	
	foreach( $del_ids as $del_id ){
		$add_id = array_pop( $add_ids );
		if( $add_id ){
			update_comment_meta( $id, 'requestee_user_id', $add_id, $del_id );
		}else{
			delete_comment_meta( $id, 'requestee_user_id', $del_id );
		}
	}
	foreach( $add_ids as $add_id ){
		add_comment_meta( $id, 'requestee_user_id', $add_id );
	}
	
	foreach( $del_roles as $del_role ){
		$add_role = array_pop( $add_roles );
		if( $add_role ){
			update_comment_meta( $id, 'requestee_user_id', $add_role, $del_role );
		}else{
			delete_comment_meta( $id, 'requestee_user_id', $del_role );
		}
	}
	foreach( $add_roles as $add_role ){
		add_comment_meta( $id, 'requestee_role', $add_role );
	}
	
	do_action( 'post_update_requestees.bop_requests', $id, $new_user_ids, $new_user_roles, $old_user_ids, $old_user_roles );
}

/*
 * 
 * 
 */
function bop_change_request_status( $comment_id, $status, $wp_error=false ){
	global $wpdb;
	
	if( ! in_array( $status, array_keys( bop_request_statuses( 'change_request_status' ) ) ) ){
		if ( $wp_error )
            return new WP_Error( 'update_error', __( 'Could not update request status - invalid status', 'bop_requests' ), array( $status, array_keys( bop_request_statuses( 'change_request_status' ) ) ) );
        else
            return false;
	}
	
	do_action( 'pre_request_status_change.bop_requests', $comment_id, $status );
	
	$comment_old = clone get_comment( $comment_id );
 
    if ( !$wpdb->update( $wpdb->comments, array( 'comment_approved' => $status ), array( 'comment_ID' => $comment_old->comment_ID ) ) ) {
        if ( $wp_error )
            return new WP_Error( 'db_update_error', __( 'Could not update request status - db error', 'bop_requests' ), $wpdb->last_error);
        else
            return false;
    }
 
    clean_comment_cache( $comment_old->comment_ID );
	
	do_action( 'request_status_changed.bop_requests', $comment_id, $status );
	
	return true;
}

/*
 * 
 * 
 */
function bop_change_request_karma( $comment_id, $val_change, $user_id=null, $wp_error=false ){
	global $wpdb;
	
	if( $val_change == 0 )
		return false;
	
	$user_id = is_null( $user_id ) ? get_current_user_id() : $user_id;
	
	$given_karma = get_comment_meta( $comment_id, 'karma_from_' . $user_id, true );
	
	$allowed_karma = bop_get_users_allowed_request_karma( $user_id );
	if( is_numeric( $allowed_karma ) ){
		$pos_only = apply_filters( 'karma_only_positive.bop_requests', true );
		$allowed_karma = array( ( ! $pos_only ) * $allowed_karma, $allowed_karma );
	}
	
	$now_given_karma = $given_karma + $val_change;
	
	if( $now_given_karma > $allowed_karma[0] && $now_given_karma < $allowed_karma[1] ){
		if ( $wp_error )
			return new WP_Error( 'update_error', __( 'Could not update request karma - no further karma to attribute', 'bop_requests' ), array( $now_given_karma, $allowed_karma ) );
		else
			return false;
	}
	
	do_action( 'pre_request_karma_change.bop_requests', $comment_id, $val_change );
	
	$comment_old = clone get_comment( $comment_id );
	
	$new_karma = $comment_old->comment_karma + $val_change;
	
	if ( !$wpdb->update( $wpdb->comments, array( 'comment_karma' => $new_karma ), array( 'comment_ID' => $comment_old->comment_ID ) ) ) {
		if ( $wp_error )
			return new WP_Error( 'db_update_error', __( 'Could not update request karma - db error', 'bop_requests' ), $wpdb->last_error);
		else
			return false;
	}
	
	update_comment_meta( $comment_old->comment_ID, 'karma_from_' . $user_id, $now_given_karma );
 
	clean_comment_cache( $comment_old->comment_ID );
	
	do_action( 'request_karma_changed.bop_requests', $comment_id, $new_karma, $comment_old->comment_karma );
	
	return true;
}

/*
 * 
 * 
 */
function _bop_requests_comment_edited( $id, $datetime = '' ){
	
	if( empty ( $datetime ) ){else{
		$datetime = current_time( 'mysql' );
	}
	update_comment_meta( $id, '_edited_datetime', $datetime );
	update_comment_meta( $id, '_edited_datetime_gmt', get_gmt_from_date( $datetime ) );
}
add_action( 'edit_comment', '_bop_requests_comment_edited', 10, 1 );
add_action( 'post_update_requestees.bop_requests', '_bop_requests_comment_edited', 10, 1 );


//DELETE

/*
 * 
 * 
 */
function bop_delete_request( $id, $with_wiping=false, $wp_error=false ){
	if( ! $with_wiping )
		return bop_change_request_status( $id, 'trash', $wp_error );
	
	if ( ! $wpdb->delete( $wpdb->comments, array( 'comment_ID' => $id, 'comment_type' => 'request' ), array( '%d', '%s' ) ) ){
		if ( $wp_error )
			return new WP_Error( 'db_delete_error', __( 'Could not delete request - db error', 'bop_requests' ), $wpdb->last_error);
		else
			return false;
	}
	
	clean_comment_cache( $id );
	
	return true;
}
