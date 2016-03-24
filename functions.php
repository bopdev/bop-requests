<?php 

//Reject if accessed directly
defined( 'ABSPATH' ) || die( 'Our survey says: ... X.' );

/**
 * 
 * 
 */
function add_bop_request( $req ){
	global $wpdb;
	
	add_action( 'pre_add_request.bop_requests', $req );
	add_action( 'pre_save_request.bop_requests', $req );
	
	$ins_extras = array_intersect_key( $req, array( 'requestees'=>array(), 'meta'=>array() ) );
	
	$ins = array();
	$format = array();
	
	if( isset( $req['parent_class'] ) ){
		$ins['parent_class'] = $req['parent_class'];
		$format[] = '%s';
	}else{
		return false;
	}
	
	if( isset( $req['parent_id'] ) ){
		$ins['parent_id'] = $req['parent_id'];
		$format[] = '%d';
	}else{
		return false;
	}
	
	if( isset( $req['content'] ) ){
		$ins['content'] = $req['content'];
		$format[] = '%s';
	}else{
		return false;
	}
	
	if( isset( $req['status'] ) ){
		$ins['status'] = $req['status'];
	}else{
		$ins['status'] = 'pending';
	}
	$format[] = '%s';
	
	if( isset( $req['type'] ) ){
		$ins['type'] = $req['type'];
	}else{
		$ins['type'] = 'custom';
	}
	$format[] = '%s';
	
	if( isset( $req['author_id'] ) ){
		$ins['author_id'] = $req['author_id'];
	}else{
		$ins['author_id'] = get_current_user_id();
	}
	$format[] = '%d';
	
	$time = current_time( 'mysql' );
	$time_gmt = current_time( 'mysql', 1 ) );
	
	$ins['created'] = $time;
	$ins['created_gmt'] = $time_gmt;
	$ins['edited'] = $time;
	$ins['edited_gmt'] = $time_gmt;
	$format[] = '%s';
	$format[] = '%s';
	$format[] = '%s';
	$format[] = '%s';
	
	$id = $wpdb->insert( $wpdb->bop_requests, $ins, $format );
	
	if( ! $id )
		return false;
	
	$r = new Bop_Request( $id );	
	
	if( isset( $ins_extra['requestees'] ) ){
		$r->update_requestee_ids( $ins_extra['requestees'], true );
	}
	
	if( isset( $ins_extra['meta'] ) ){
		foreach( $ins_extra['meta'] as $mk=>$mv ){
			$r->add_meta( $mk, $mv );
		}
	}
	
	add_action( 'post_add_request.bop_requests', $r, $req );
	add_action( 'post_save_request.bop_requests', $r, $req );
	
	return $id;
}

/**
 * 
 * 
 */
function delete_bop_request( $id, $with_wiping = false ){
	global $wpdb;
	
	add_action( 'pre_delete_request.bop_requests', $id, $with_wiping );
	
	if( ! $with_wiping ){
		$r = new Bop_Request( $id );
		$worked = $r->change_status( 'trash' );
	}else{
		$worked = $wpdb->delete( $wpdb->bop_requests, array( 'request_id'=>$id ), array( '%d' ) );
	}
	
	add_action( 'post_delete_request.bop_requests', $worked, $id, $with_wiping );
		
	return $worked;
}

/**
 * 
 * 
 */
function bop_requests_statuses( $type = '', $context = '' ){
	$statuses = array(
		'pending'=>array( 
			'label'=>__( 'Pending' )
		),
		'fulfilled'=>array(
			'label'=>__( 'Fulfilled' )
		),
		'rejected'=>array(
			'label'=>__( 'Rejected' )
		),
		'trash'=>array(
			'label'=>__( 'Trash' )
		)
	);
	return apply_filters( 'request_statuses.bop_requests', $statuses, $type, $context );
}

/**
 * 
 * 
 */
function bop_requests_register_status( $name, $args = array(), $type = '', $context = '' ){
	add_filter( 'request_statuses.bop_requests', function( $statuses, $t, $c ) use ( $name, $args, $type, $context ){
		if( ( $type == '' || $type == $t ) && ( $contect == '' || $context == $c ) ){
			$statuses[$name] = $args;
		}
		return $statuses;
	}, 1, 1 );
}

/**
 * 
 * 
 */
function bop_requests_karma_bounds( $user_id = null, $request_id = null ){
	
	//Default: De desiderium nihil nisi bonum
	$bounds = apply_filters( 'karma_bounds.bop_requests', array( 0, 1 ), $user_id, $request_id );
	
	if( is_array( $bounds ) ){
		return $bounds;
	}elseif( is_numeric( $bounds ) && $bounds != 0 ){
		return array( -$bounds, $bounds );
	}
	return array( 0, 1 );
}
