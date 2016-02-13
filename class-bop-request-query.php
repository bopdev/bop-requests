<?php 

class Bop_Request_Query{
	
	public $query_args;
	
	protected $_collection;
	
	public function __construct( $query = null ){
		if( is_null( $query ) )
			return;
		$this->query( $query );
	}
	
	public function query( $query ){
		
	}
	
	public function get_requests(){
		
	}
	
	public function get_comments(){}
	
}
