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
     * default ORDER BY field.
     *
     * @since 0.2.0
     * @access public
     * @var string
     */
    public $default_orderby = 'id';
    
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
					'key'=>'request_id',
					'value'=>$qvs['id']
				)
			);
			if( ! empty( $prev_clauses ) ){
				$qvs['clauses'][] = $prev_clauses;
			}
		}
		
		$qvs['clauses'] = $this->fill_clauses( $qvs['clauses'] );
		
		//standardise orderby
		$qvs['orderby'] = (array)$qvs['orderby'];
		for( $i=0; $i<count( $qvs['orderby'] ); $i++ ){
			if( $ob = $this->fill_orderby_clause( $qvs['orderby'][$i] ) ){
				$qvs['orderby'][$i] = $ob;
			}else{
				break;
			}
		}
		if( ! isset( $qvs['orderby'][0] ) ){
			$qvs['orderby'][0] = array( $this->default_orderby, $this->default_direction );
		}
		
		$this->query_vars = $qvs;
		
		$this->query_vars_for_sql = array_intersect_key( $qvs, $this->query_vars_for_sql );
		
		$this->query_vars_hash = md5( serialize( $this->query_vars_for_sql ) );
		
	}
	
	public function fill_orderby_clause( $ob ){
		
		if( ! is_array( $ob ) ){
			if( ! is_string( $ob ) ){
				return false;
			}else{
				$ob_key = $ob;
				$ob_dir = $this->default_direction;
			}
		}elseif( isset( $ob[0] ) && is_string( $ob[0] ) ){
			$ob_key = $ob[0];
			$ob_dir = isset( $ob[1] ) ? ( strtoupper( $ob[1] ) !== $this->default_direction ? 'ASC' : 'DESC' ) : $this->default_direction;
		}else{
			return false;
		}
		
		return array( $ob_key, $ob_dir );
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
				if( in_array( $k, array( 'meta_query', 'tax_query' ) ) ){
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
    
    protected function fill_clause( $clause ) {
		if( $clause['key'] == 'id' ){
			$clause['key'] = 'request_id';
		}
		
		if( isset( $clause['compare'] ) ){
            $clause['compare'] = strtoupper( $clause['compare'] );
        }elseif( isset( $clause['value'] ) && is_array( $clause['value'] ) ){
            $clause['compare'] = 'IN';
		}else{
            $clause['compare'] = '=';
        }
		
		return $clause;
	}
	
	/**
     * Determine whether this is a valid first-order clause.
     *
     * Checks to see if the current clause has a key and value field.
     * If so, it's first-order.
     *
     * @since 0.2.0
     *
     * @access protected
     *
     * @param  array $clause Query clause.
     * @return bool True if this is a first-order clause.
     */
    protected function is_first_order_clause( $clause ) {
        
        if( ( isset( $clause['key'] ) && ! is_string( $clause['key'] ) )
			|| ( isset( $clause['compare'] ) && ! is_string( $clause['compare'] ) )
        ){
			return false;
		}
        
        if( ! empty( $clause['compare'] ) ){
			$clause['compare'] = strtoupper( $clause['compare'] );
			
			if( ! in_array( $clause['compare'], array(
				'=', '!=', '>', '>=', '<', '<=',
				'LIKE', 'NOT LIKE',
				'IN', 'NOT IN',
				'BETWEEN', 'NOT BETWEEN',
				'REGEXP', 'NOT REGEXP', 'RLIKE'
			) ) ){
				return false;
			}
			
			if( $clause['compare'] == 'IN' || $clause['compare'] == 'NOT IN' ){
				return is_array( $clause['value'] ) && ! empty( $clause['value'] );
			}
			
			if( $clause['compare'] == 'BETWEEN' || $clause['compare'] == 'NOT BETWEEN' ){
				return is_array( $clause['value'] ) && count( $clause['value'] ) == 2;
			}
        }
        
        //for everything else we need a key and a value and that value to not be an array
        return ! empty( $clause['key'] ) && ! empty( $clause['value'] ) && ! is_array( $clause['value'] );
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
				$select = "request_id";
			break;
			default:
				$select = "*";
		}
		
		//resolve FROM
		$from = "{$wpdb->bop_requests}";
		
		//resolve clauses
		$sql_chunks = $this->get_clause_sql( $qvs['clauses'] );
		$join = $sql_chunks['join'];
		$where = $sql_chunks['where'];
		
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
		global $wpdb;
		
		$join = "";
		$where = "";
		
		$table_aliases = array();
		
		foreach( $clauses as $k=>$clause ){
			
			if( $k === 'meta_query' ){
				
				$mq = new WP_Meta_Query( $clause );
				$sql_chunks = $mq->get_sql( 'request', $wpdb->bop_requests, 'request_id', $this );
				
				unset( $mq );
				
			}elseif( $k === 'tax_query' ){
				
				$tq = new WP_Tax_Query( $clause );
				$sql_chunks = $mq->get_sql( 'request', $wpdb->bop_requests, 'request_id', $this );
				
				unset( $tq );
				
			}elseif( is_numeric( $k ) ){
				
				if( $this->is_first_order_clause( $clause ) ){
					
					//key for join
					switch(){
						case 'user_karma_value':
						case 'user_karma_user_id':
							$table_alias = "brq_requests_user_karma";
							if( ! in_array( $table_alias, $table_aliases ) ){
								$table_aliases[] = $table_alias;
								$sql_chunks['join'] = "LEFT JOIN {$wpdb->bop_requests_user_karma} AS {$table_alias} ON (`request_id` = `{$table_alias}.request_id`)";
							}
						break;
						
						case 'requestee_id':
							$table_alias = "brq_requests_requestees";
							if( ! in_array( $table_alias, $table_aliases ) ){
								$table_aliases[] = $table_alias;
								$sql_chunks['join'] = "LEFT JOIN {$wpdb->bop_requests_requestees} AS {$table_alias} ON (`request_id` = `{$table_alias}.request_id`)";
							}
						break;
					}
					//key for where
					switch( $clause['key'] ){
						case 'request_id':
						case 'object_class':
						case 'object_id':
						case 'created':
						case 'created_gmt':
						case 'edited':
						case 'edited_gmt':
						case 'content':
						case 'karma':
						case 'status':
						case 'type':
						case 'author_id':
							$column = "`{$clause['key']}`";
						break;
						
						case 'user_karma_value':
							$column = "`{$table_alias}.value`";
						break;
						
						case 'user_karma_user_id':
							$column = "`{$table_alias}.user_id`";
						break;
						
						case 'requestee_id':
							
						break;
					}
					
					$compare = $clause['compare'];
					
					switch( $compare ){
						case 'IN':
						case 'NOT IN':
							$value_format = '(' . substr( str_repeat( ',%s', count( $clause['value'] ) ), 1 ) . ')';
							$value = $wpdb->prepare( $value_format, $clause['value'] );
						break;
						
						case 'BETWEEN':
						case 'NOT BETWEEN':
							$value = $wpdb->prepare( '%s AND %s', $clause['value'] );
						break;
						
						case 'LIKE':
						case 'NOT LIKE':
							$value = '%' . $wpdb->esc_like( $clause['value'] ) . '%';
							$value = $wpdb->prepare( '%s', $value );
						break;
						
						default:
							$value = $wpdb->prepare( '%s', $meta_value );
						break
					}
					
					$type = $this->get_cast_for_type( $clause['type'] );
					
					$sql_chunks['where'] .= "CAST({$column} AS {$type}) {$compare} {$value}";
					
					unset( $column, $type, $compare, $value );
					
				}else{
					
					$sql_chunks = $this->get_clause_sql( $clause );
					
				}
				
			}
			
			$join .= "\n{$sql_chunks['join']}";
			$where .= "\n  {$clauses['relation']} ( {$sql_chunks['where']} )";
			
		}
		
		return array( 'join'=>$join, 'where'=>$where );
	}
	
	/**
     * Return the appropriate alias for the given meta type if applicable.
     *
     * @since 0.2.0
     * @access public
     *
     * @param string $type MySQL type to cast value.
     * @return string MySQL type.
     */
    public function get_cast_for_type( $type = '' ) {
        if ( empty( $type ) )
            return 'CHAR';
 
        $type = strtoupper( $type );
 
        if ( ! preg_match( '/^(?:BINARY|CHAR|DATE|DATETIME|SIGNED|UNSIGNED|TIME|NUMERIC(?:\(\d+(?:,\s?\d+)?\))?|DECIMAL(?:\(\d+(?:,\s?\d+)?\))?)$/', $type ) )
            return 'CHAR';
 
        if ( 'NUMERIC' == $type )
            $type = 'SIGNED';
 
        return $type;
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
