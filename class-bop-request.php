<?php 

//Reject if accessed directly
defined( 'ABSPATH' ) || die( 'Our survey says: ... X.' );

/**
 * 
 * 
 */
class Bop_Request{
	
	/**
	 * 
	 * 
	 */
	protected $_db;
	
	/**
	 * 
	 * 
	 */
	public $id;
	
	/**
	 * 
	 * 
	 */
	public $request_id;
	
	/**
	 * 
	 * 
	 */
	public $parent_class = 'post';
	
	/**
	 * 
	 * 
	 */
	public $parent_id = 0;
	
	/**
	 * 
	 * 
	 */
	public $created = '0000-00-00 00:00:00';
	
	/**
	 * 
	 * 
	 */
	public $created_gmt = '0000-00-00 00:00:00';
	
	/**
	 * 
	 * 
	 */
	public $edited = '0000-00-00 00:00:00';
	
	/**
	 * 
	 * 
	 */
	public $edited_gmt = '0000-00-00 00:00:00';
	
	/**
	 * 
	 * 
	 */
	public $content = '';
	
	/**
	 * 
	 * 
	 */
	public $karma = 0;
	
	/**
	 * 
	 * 
	 */
	public $status = 'pending';
	
	/**
	 * 
	 * 
	 */
	public $type = 'custom';
	
	/**
	 * 
	 * 
	 */
	public $author_id = 0;
	
	/**
	 * 
	 * 
	 */
	public $requestee_ids = array();
	
	/**
	 * 
	 * 
	 */
	public $comment_ids = array();
	
	/**
	 * 
	 * 
	 */
	public $users_karma = array();
	
	/**
	 * 
	 * 
	 */
	protected $_is_edited;
	
	/**
	 * 
	 * 
	 */
	protected $_got_all_users_karma = false;
	
	/**
	 * 
	 * 
	 */
	public function __construct( $id, $cache = true ){
		global $wpdb;
		$this->_db = $wpdb;
		
		$this->load( $id );
	}
	
	/**
	 * 
	 * 
	 */
	public function __destruct(){
		return false;
	}
	
	/**
	 * 
	 * 
	 */
	public function load( $id, $cache = true ){
		
		if( is_numeric( $id ) ){
		
			if( $r = wp_cache_get( $id, 'bop_requests' ) ){
				return $r;
			}
			
			$this->id = $id;
			
			$r = $this->_db->get_row( $this->_db->prepare( "
				SELECT *
				FROM
				{$this->_db->bop_requests}
				WHERE ID = %d
				LIMIT 1
			", $id ) );
			
			if( ! $r ){
				return false;
			}
			
		}elseif( is_object( $id ) && isset( $id->request_id ) ){
			$r = $id;
			$this->id = $id->request_id;
		}else{
			return;
		}
		
		foreach( get_object_vars( $r ) as $key => $value )
            $this->$key = $value;
		
		if( $cache ){
			wp_cache_add( $this->id, $this, 'bop_requests' );
		}
	}
	
	
	//GETTERS
	
	/**
	 * 
	 * 
	 */
	public function get_user_karma( $user_id = null ){
		if( is_null( $user_id ) ){
			$user_id = get_current_user_id();
		}
		
		if( isset( $this->users_karma[$user_id] ) ){
			return $k;
		}
		
		$k = $this->_db->get_var( $this->_db->prepare( "
			SELECT value
			FROM {$this->_db->bop_requests_user_karma}
			WHERE request_id = %d AND user_id = %d
			LIMIT 1
		", $this->id, $user_id ) );
		
		if( is_null( $k ) )
			$k = 0;
		
		$this->users_karma[$user_id] = $k;
		
		return $k;
	}
	
	/**
	 * 
	 * 
	 */
	public function get_all_users_karma(){
		if( $this->_got_all_users_karma )
			return $this->users_karma;
		
		$ukarr = $this->_db->get_results( $this->_db->prepare( "
			SELECT user_id, value
			FROM {$this->_db->bop_requests_user_karma}
			WHERE request_id = %d
		", $this->id ) );
		
		if( ! $ukarr )
			return array();
		
		for( $i = 0; $i < count( $ukarr ); $i++ ){
			$this->users_karma[$ukarr[$i]['user_id']] = $ukarr[$i]['value'];
		}
		
		$this->_got_all_users_karma = true;
		
		return $this->users_karma;
	}
	
	/**
	 * 
	 * 
	 */
	public function get_comment_ids(){
		
		if( empty( $this->comment_ids ) ){
			
			$this->comment_ids = $this->_db->get_col( $this->_db->prepare( "
				SELECT comment_id
				FROM {$this->_db->bop_requests_comments}
				WHERE request_id = %d
			", $this->id ) );
		}
		
		return $this->comment_ids;
	}
	
	/**
	 * 
	 * 
	 */
	public function get_requestee_ids(){
		
		if( empty( $this->requestee_ids ) ){
			
			$this->requestee_ids = $this->_db->get_col( $this->_db->prepare( "
				SELECT user_id
				FROM {$this->_db->bop_requests_requestees}
				WHERE request_id = %d
			", $this->id ) );
		}
		
		return $this->requestee_ids;
	}
	
	/**
	 * 
	 * 
	 */
	public function get_meta( $key, $single = false ){
		return get_metadata( 'bop_requests', $this->id, $key, $single );
	}
	
	/**
	 * 
	 * 
	 */
	public function has_been_edited(){
		if( is_null( $this->_is_edited ) ){
			$leeway = apply_filters( 'create_to_edit_time_window.bop_requests', 300, $this->id );
			$this->_is_edited = strtotime( $this->created_gmt ) + $leeway < strtotime( $this->edited_gmt );
		}
		
		return $this->_is_edited;
	}
	
	//ADDERS
	
	/**
	 * 
	 * 
	 */
	public function add_comment( $commentdata ){
		$commentdata['comment_post_ID'] = get_option( 'bop_requests_master_post_id' );
		
		$id = wp_new_comment( $commentdata );
		
		if( ! $id )
			return false;
		
		$this->_db->insert( $this->_db->bop_requests_comments, array( 'request_id'=>$this->id, 'comment_id'=>$id ), array( '%d', '%d' ) );
		
		return $id;
	}
	
	/**
	 * 
	 * 
	 */
	public function add_requestee_ids( $new_ids ){
		return $this->update_requestee_ids( $new_ids, true );
	}
	
	/**
	 * 
	 * 
	 */
	public function add_meta( $key, $value, $unique = false ){
		return add_metadata( 'bop_requests', $this->id, $key, $value, $unique );
	}
	
	
	//UPDATERS
	
	/**
	 * 
	 * 
	 */
	public function update_requestee_ids( $new_ids, $append = false ){
		$curr_ids = $this->get_requestee_ids();
		
		$add_ids = array_diff( $new_ids, $curr_ids );
		
		if( ! $append ){
			$del_ids = array_diff( $curr_ids, $new_ids );
			$this->remove_requestees( $del_ids );
		}
		
		$count = 0;
		if( ! empty( $add_ids ) ){
			
			$insert_rows
			for( $i = 0; $i < count( $add_ids ); $i++ ){
				$insert_rows[] = $this->_db->prepare( "( %d, %d )", $this->id, $add_ids[$i] );
			}
			
			$count = $this->_db->query( "
				INSERT INTO {$wpdb->bop_requests_requestees} (request_id, user_id)
				VALUES " . implode( ",\n\t", $insert_rows ) . "
			" );
		}
		
		return $count;
	}
	
	/**
	 * 
	 * 
	 */
	public function update_user_karma( $user_id, $value ){
		$bounds = bop_requests_karma_bounds( $user_id, $this->id );
		
		//check valid karma level
		if( $value < $bounds[0] ){
			$value = $bounds[0];
		}elseif( $value > $bounds[1] ){
			$value = $bounds[1];
		}
		
		if( $this->get_users_karma( $user_id ) != $value )
			return false;
		
		$success = $this->_db->update( $this->_db->bop_requests, array( 'value'=>$value ), array( 'request_id'=>$this->id, 'user_id'=>$user_id ), array( '%d' ), array( '%d', '%d' ) );
		
		if( $success ){
			$this->users_karma[$user_id] = $value;
			$this->update_karma();
		}
		
		return $success;
	}
	
	/**
	 * 
	 * 
	 */
	public function update_meta( $key, $value, $prev_value = '' ){
		
		return update_metadata( 'bop_requests', $this->id, $key, $value, $prev_value );
	}
	
	/**
	 * 
	 * 
	 */
	public function edit_content( $new_content ){
		
		$time = current_time( 'mysql' );
		$time_gmt = current_time( 'mysql', 1 ) );
		
		$success = $this->_db->update( $this->_db->bop_requests, array( 'content'=>$new_content, 'edited'=>$time, 'edited_gmt'=>$time_gmt, array( 'request_id'=>$this->id ), array( '%s', '%s', '%s' ), array( '%d' ) );
		
		if( $success ){
			$this->content = $new_content;
			$this->edited = $time;
			$this->edited_gmt = $time_gmt;
			$this->_is_edited = null;
		}
		
		return $success;
	}
	
	/**
	 * 
	 * 
	 */
	public function change_status( $new_status, $prev_status = null ){
		
		$where = array( 'request_id'=>$this->id );
		$where_format = array( '%d' );
		
		if( ! is_null( $prev_status ) ){
			$where['status'] = $prev_status;
			$where_format[] = '%s';
		}
		
		if( in_array( $new_status, array_keys( bop_requests_statuses( $this->type ) ) ) )
			return false;
		
		$success = $this->_db->update( $this->_db->bop_requests, array( 'status'=>$new_status ), $where, array( '%s' ), $where_format );
		
		if( $success ){
			$this->status = $new_status;
		}
		
		return $success;
	}
	
	/**
	 * 
	 * 
	 */
	public function update_karma(){
		
		$current_karma = $this->_db->get_var( $this->_db->prepare( "
			SELECT SUM(value)
			FROM {$wpdb->bop_requests_user_karma}
			WHERE request_id = %d
			GROUP BY request_id
		", $this->id ) );
		
		$success = $this->_db->update( $this->_db->bop_requests, array( 'karma'=>$current_karma ), array( 'request_id'=>$this->id ), array( '%s' ), array( '%d' ) );
		
		if( $success ){
			$this->karma = $current_karma;
		}
		
		return $success;
	}
	
	
	//DELETERS
	
	/**
	 * 
	 * 
	 */
	public function remove_requestee( $user_id ){
		return $this->remove_requestees( array( $user_id ) );
	}
	
	/**
	 * 
	 * 
	 */
	public function remove_requestees( $user_ids ){
		if( empty( $user_ids ) )
			return false;
		
		$success = $this->_db->query( $this->_db->prepare( "
			DELETE FROM {$this->_db->bop_requests_requestees}
			WHERE user_id IN (" . implode( ",", array_fill( 0, count( $user_ids ), "%d" ) ) . ")
		", $user_ids ) );
		
		if( $success ){
			$this->clean_cache( 'requestees' );
		}
		
		return $success;
	}
	
	/**
	 * 
	 * 
	 */
	public function delete_comment( $comment_id, $force_delete = false ){
		$this->get_comment_ids();
		if( ! in_array( $comment_id, $this->comment_ids ) )
			return false;
		
		$success = wp_delete_comment( $comment_id, $force_delete );
		
		if( $success ){			
			if( $force_delete )
				$this->_db->delete( $this->_db->bop_requests_comments, array( 'comment_id'=>$comment_id ), array( '%d' ) );
				
			$this->clean_cache( 'comments' );
		}
		
		return $success;
	}
	
	/**
	 * 
	 * 
	 */
	public function delete_meta( $key, $value = null ){
		$delete_all = false;
		return delete_metadata( 'bop_requests', $this->id, $key, $value, $delete_all );
	}
	
	/**
	 * 
	 * 
	 */
	public function clean_cache( $name = null ){
		
		if( is_null( $name ) ){
			wp_cache_delete( $this->id, 'bop_requests' );
		}
		
		if( $name = 'karma' ){
			$this->_got_all_users_karma = false;
			$this->users_karma = array();
			return true;
		}
		
		if( $name = 'requestees' ){
			$this->requestee_ids = array();
			return true;
		}
		
		if( $name = 'comments' ){
			$this->comment_ids = array();
			return true;
		}
		
		if( $name = 'meta' ){
			update_meta_cache( 'bop_requests', $this->id );
			return true;
		}
		
		return false;
	}
	
	
	/**
	 * 
	 * 
	 */
	public function get_template( $type ){
		$path = '';
		switch( $type ){
			case 'add':
				$path = 'templates/add-request.html';
			break;
			case 'view':
				$path = 'templates/view-request.html';
			break;
			case 'edit':
				$path = 'templates/edit-request.html';
			break;
			case 'deleted':
				$path = 'templates/deleted-request.html';
			break;
			case 'add-comment':
				$path = 'templates/add-request-comment.html';
			break;
			case 'view-comment':
				$path = 'templates/view-request-comment.html';
			break;
			case 'edit-comment':
				$path = 'templates/edit-request-comment.html';
			break;
			case 'deleted-comment':
				$path = 'templates/deleted-request-comment.html';
			break;
		}
		
		$path = bop_requests_plugin_path( $path );
		
		$this->template = apply_filters( 'get_request_template.bop_requests', $path, $type, $this );
		return $this->template;
	}
	
}
