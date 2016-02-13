<?php 

/**
 * 
 * 
 */
class Bop_Request{
	
	public $id;
	
	public $type;
	
	public $content;
	
	public $created;
	
	public $created_gmt;
	
	public $edited;
	
	public $edited_gmt;
	
	public $is_edited;
	
	public $karma;
	
	public $author_id;
	
	public $post_id;
	
	public $status;
	
	public $individual_requestees;
	
	public $role_requestees;
	
	public $replies;
	
	public function __construct( $id = null ){
		if( ! is_null( $id ) )
			$this->load( $id );
	}
	
	public function load( $id ){
		$r = bop_get_request( $id, 'raw' );
		$this->id = $r->comment_ID;
		$this->type = get_comment_meta( $id, 'request_type' );
		$this->content = $r->comment_content;
		$this->created = $r->comment_date;
		$this->created_gmt = $r->comment_date_gmt;
		$this->edited = get_comment_meta( $id, '_edited_datetime' );
		$this->edited_gmt = get_comment_meta( $id, '_edited_datetime_gmt' );
		$this->is_edited = ( strtotime( $this->created_gmt ) < ( strtotime( $this->edited_gmt ) + 600 ) );
		$this->karma = $r->comment_karma;
		$this->author_id = $r->user_id;
		$this->post_id = $r->comment_post_ID;
		$this->status = $r->comment_approved;
		$this->individual_requestees = get_comment_meta( $id, 'requestee_user_id' );
		$this->role_requestees = get_comment_meta( $id, 'requestee_role' );
	}
	
	public function get_user_karma( $user_id=null ){
		$user_id = is_null( $user_id ) ? get_current_user_id() : $user_id;
		return get_comment_meta( $this->id, 'karma_from_' . $user_id, true );
	}
	
	public function array_for_db(){
		$arr = array( 'comment_ID'=>$this->id, 'comment_content'=>$this->content, 'comment_date'=>$this->created, 'comment_karma'=>$this->karma, 'user_id'=>$this->author_id, 'comment_post_ID'=>$this->post_id, 'comment_approved'=>$this->status, 'meta'=>array( 'request_type'=>$this->type ) );
	}
	
	public function get_replies(){
		$this->replies = bop_get_request_replies( array( 'request_id'=>$this->id ) );
		return $this->replies;
	}
	
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
			case 'add-reply':
				$path = 'templates/add-request-reply.html';
			break;
			case 'view-reply':
				$path = 'templates/view-request-reply.html';
			break;
			case 'edit-reply':
				$path = 'templates/edit-request-reply.html';
			break;
			case 'deleted-reply':
				$path = 'templates/deleted-request-reply.html';
			break;
		}
		
		$path = bop_requests_plugin_path( $path );
		
		$this->template = apply_filters( 'get_request_template.bop_requests', $path, $type, $this );
		return $this->template;
	}
	
}
