<?php 

class Bop_Request_Query{
	
	/**
     * Query vars set by the user
     *
     * @since 0.2.0
     * @access public
     * @var array
     */
    public $query;
 
    /**
     * Query vars, after parsing
     *
     * @since 0.2.0
     * @access public
     * @var array
     */
    public $query_vars = array();
    
    /**
     * Query vars, after parsing
     *
     * @since 0.2.0
     * @access public
     * @var array
     */
    public $query_vars_for_sql = array(
		'fields'=>'',
		'clauses'=>array(),
		'orderby'=>array(),
		'limit'=>-1,
		'offset'=>0
	);
    
    /**
     * default query vars.
     *
     * @since 0.2.0
     * @access public
     * @var array
     */
    public $default_query_vars = array(
		'id'=>'',
		'search'=>'',
		'clauses'=>array(),
		'fields'=>'',
		'orderby'=>array(),
		'limit'=>-1,
		'offset'=>0,
		'cache_each_item'=>true,
		'cache_query_results'=>false
    );
    
    /**
     * default clauses relation.
     *
     * @since 0.2.0
     * @access public
     * @var string
     */
    public $default_relation = 'AND';
    
    /**
     * default ORDER BY direction.
     *
     * @since 0.2.0
     * @access public
     * @var string
     */
    public $default_direction = 'DESC';
    
    /**
     * Table aliases.
     *
     * @since 0.2.0
     * @access public
     * @var array
     */
    public $table_aliases = array();
 
    /**
     * The database query.
     *
     * @since 0.2.0
     * @access public
     * @var string
     */
    public $sql;
 
    /**
     * List of items.
     *
     * @since 0.2.0
     * @access public
     * @var array
     */
    public $collection;
 
    /**
     * The amount of items for the current query.
     *
     * @since 0.2.0
     * @access public
     * @var int
     */
    public $count = 0;
 
    /**
     * The total amount of items for the current query (ignoring limit).
     *
     * @since 0.2.0
     * @access public
     * @var int
     */
    public $total_count = 0;
 
    /**
     * Index of the current item in the loop.
     *
     * @since 0.2.0
     * @access public
     * @var int
     */
    public $current_index = -1;
 
    /**
     * Whether the loop has started and the caller is in the loop.
     *
     * @since 0.2.0
     * @access public
     * @var bool
     */
    public $in_the_loop = false;
 
    /**
     * The current item.
     *
     * @since 0.2.0
     * @access public
     * @var Object | int
     */
    public $current_item;
    
    /**
     * Hash of the query - a key for storage (e.g., caching).
     *
     * @since 0.2.0
     * @access public
     * @var string
     */
    public $query_vars_hash;
	
	public function __construct( $query = null ){
		if( ! empty( $query ) )
			$this->query( $query );
	}
	
	public function reset(){
		unset( $this->query );
		$this->query_vars = array();
		unset( $this->tax_query );
		unset( $this->meta_query );
		unset( $this->date_query );
		unset( $this->sql );
		unset( $this->collection );
		$this->count = 0;
		$this->total_count = 0;
		$this->current_index = -1;
		$this->in_the_loop = false;
		unset( $this->current_item );
	}
	
	public function query( $q = array() ){
		$this->reset();
		$this->query = $q;
		$this->get_collection();
	}
	
	
	public function parse_query( $query = '' ){
		if ( ! empty( $query ) ) {
			$this->init();
			$this->query = $query;
		}
		
		$qvs = wp_parse_args( $this->query, $this->default_query_vars );
		
		//add id as clause if given
		if( ! empty( $qvs['id'] ) ){
			$prev_clauses = $qvs['clauses'];
			$qvs['clauses'] = array( 
				'relation'=>$this->default_relation,
				array(
					'key'=>'id',
					'value'=>$qvs['id'],
					'compare'=>( is_array( $qvs['id'] ) ? 'IN' : '=' )
				)
			);
			if( ! empty( $prev_clauses ) ){
				$qvs['clauses'][] = $prev_clauses;
			}
		}
		
		$qvs['clauses'] = $this->fill_clauses( $qvs['clauses'] );
		
		//standardise orderby
		foreach( (array)$qvs['orderby'] as $ob ){
			$ob = (array)$ob;
			$ob[1] = isset( $ob[1] ) ? ( strtoupper( $ob[1] ) !== $this->default_direction ? 'ASC' : 'DESC' ) : $this->default_direction;
		}
		
		$this->query_vars = $qvs;
		
		$this->query_vars_for_sql = array_intersect_key( $qvs, $this->query_vars_for_sql );
		
		$this->query_vars_hash = md5( serialize( $this->query_vars_for_sql ) );
		
	}
	
	/**
     * Determine whether this is a first-order clause.
     *
     * Checks to see if the current clause has a key and value field.
     * If so, it's first-order.
     *
     * @param  array $query Query clause.
     * @return bool True if this is a first-order clause.
     */
    protected function fill_clauses( $clauses ) {
        foreach( $clauses as $k=>$clause ){
			if( $k == 'relation' ){
				continue;
			}elseif( is_numeric( $k ) ){
				if( $this->is_first_order_clause( $clause ) ){
					$clauses[$k] = $this->fill_clause( $clause );
				}elseif( is_array( $clause ) ){
					$clauses[$k] = $this->fill_clauses( $clause );
				}else{
					unset( $clauses[$k] );
				}
			}elseif( is_string( $k ) ){
				if( in_array( $k, array( 'meta_query', 'date_query', 'tax_query' ) ) ){
					$clauses[$k] = $clause;
				}
			}else{
				unset( $clauses[$k] );
			}
		}
		if( ! empty( $clauses[0] ) ){
			$clauses['relation'] = isset( $clause['relation'] ) ? ( strtoupper( $clause['relation'] ) !== $this->default_relation ? 'ASC' : 'DESC' ) : $this->default_relation;
		}else{
			$clauses = array();
		}
		return $clauses;
    }
    
    /**
     * Determine whether this is a first-order clause.
     *
     * Checks to see if the current clause has a key and value field.
     * If so, it's first-order.
     *
     * @param  array $query Query clause.
     * @return bool True if this is a first-order clause.
     */
    protected function fill_clause( $clause ) {
		if( empty( $clause['compare'] ) ){
			$clause['compare'] = '=';
		}
		return $clause;
	}
	/**
     * Determine whether this is a first-order clause.
     *
     * Checks to see if the current clause has a key and value field.
     * If so, it's first-order.
     *
     * @param  array $query Query clause.
     * @return bool True if this is a first-order clause.
     */
    protected function is_first_order_clause( $query ) {
        return ! empty( $query['key'] );
    }
	
	/**
     * Retrieve query variable.
     *
     * @since 0.2.0
     *
     * @access public
     *
     * @param string $query_var Query variable key.
     * @param mixed  $default   Optional. Value to return if the query variable is not set. Default empty string.
     * @return mixed Contents of the query variable.
     */
    public function get( $query_var, $default = '' ) {
        if ( isset( $this->query_vars[ $query_var ] ) ) {
            return $this->query_vars[ $query_var ];
        }
 
        return $default;
    }
 
    /**
     * Set query variable.
     *
     * @since 0.2.0
     * @access public
     *
     * @param string $query_var Query variable key.
     * @param mixed  $value     Query variable value.
     */
    public function set( $query_var, $value ) {
        $this->query_vars[$query_var] = $value;
    }
	
	public function get_collection(){
		if( empty ( $this->collection ) ){
			$this->prepare_sql();
			$this->get_db_response();
		}
		return $this->collection;
	}
	
	public function prepare_sql(){
		global $wpdb;
		
		$qvs = &$this->query_vars_for_sql;
		
		//resolve SELECT
		$select = "";
		switch( $qvs['fields'] ){
			case 'ids':
				$select = "id";
			break;
			default:
				$select = "*";
		}
		
		//resolve FROM
		$from = "{$wpdb->bop_requests}";
		
		//resolve clauses
		$clauses = $this->get_clause_sql( $qvs['clauses'] );
		$join = $clauses['join'];
		$where = $clauses['where'];
		//########## TO DO ###############
		
		//resolve ORDER BY
		$orderby = "";
		foreach( $qvs['orderby'] as $ob ){
			$ob = (array)$ob;
			$comma = $orderby ? ", " : "";
			
			switch( $ob[0] ){
				default:
					$orderby .= $comma . "{$ob[0]} {$ob[1]}";
			}
		}
		if( ! $orderby ){
			
		}
		
		//resolve LIMIT
		$limit = "";
		if( isset( $qvs['limit'] ) && $qvs['limit'] > 0 ){
			$limit = $qvs['limit'];
		}
		
		//resolve OFFSET
		$offset = "";
		if( isset( $qvs['offset'] ) && $qvs['offset'] > 0 ){
			$offset = $qvs['offset'];
		}
		
		$this->sql = "SELECT $select";
		$this->sql .= "\nFROM $from";
		if( $join )
			$this->sql .= "\n$join";
		if( $where )
			$this->sql .= "\nWHERE $where";
		if( $orderby )
			$this->sql .= "\nORDER BY $orderby";
		if( $limit )
			$this->sql .= "\nLIMIT $limit";
		if( $offset )
			$this->sql .= "\nOFFSET $offset";
	}
	
	public function get_clause_sql( $clauses ){
		$join = "";
		$where = "";
		return array( 'join'=>$join, 'where'=>$where );
	}
	
	public function get_db_response(){
		global $wpdb;
		$results = $wpdb->get_results( $this->sql );
	}
	
	public function next_item(){
		if( ++$this->current_index < $this->count ){
			return $this->current_item = $this->collection[$this->current_index];
		}else{
			$this->rewind_collection();
			return false;
		}
	}
	
	public function rewind_collection(){
		$this->current_index = -1;
		if( $this->count > 0 )
			$this->current_item = $this->collection[0];
	}
	
	public function get_comments( $query ){
		
	}
	
}
