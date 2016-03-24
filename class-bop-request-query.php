<?php 

//Reject if accessed directly
defined( 'ABSPATH' ) || die( 'Our survey says: ... X.' );

/**
 * 
 * 
 */
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
     * Query vars for sql, after parsing
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
     * Default query vars.
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
		'cache_query_result'=>false
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
     * The amount of items per page for the current query.
     *
     * @since 0.2.0
     * @access public
     * @var int | false
     */
    public $per_page = false;
 
    /**
     * The current page of items.
     *
     * @since 0.2.0
     * @access public
     * @var int | false
     */
    public $page = false;
	
	/**
     * Is a single row.
     *
     * @since 0.2.0
     * @access public
     * @var bool
     */
	public $single_item = false;
	
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
     * @var object | int
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
    
    /**
     * Use cache if available.
     *
     * @since 0.2.0
     * @access public
     * @var string
     */
    public $use_query_cache = false;
    
    /**
     * WPDB.
     *
     * @since 0.2.0
     * @access public
     * @var object
     */
    protected $_db;
	
	public function __construct( $query = null ){
		global $wpdb;
		$this->_db = $wpdb;
		
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
		$this->per_page = false;
		$this->page = false;
		$this->single_item = false;
		$this->current_index = -1;
		$this->in_the_loop = false;
		unset( $this->current_item );
		unset( $this->query_vars_hash );
		$this->use_query_cache = apply_filters( 'use_query_cache.bop_requests' false, $this );
	}
	
	public function query( $q = array() ){
		$this->reset();
		$this->query = $q;
		$this->parse_query();
		$this->fetch_collection();
	}
	
	
	public function parse_query( $query = '' ){
		if ( ! empty( $query ) ) {
			$this->reset();
			$this->query = $query;
		}
		
		$qvs = wp_parse_args( $this->query, $this->default_query_vars );
		
		$new_clauses = array();
		
		//add id as clause if given
		if( ! empty( $qvs['id'] ) ){
			$new_clauses[] = array( 
				'key'=>'request_id',
				'value'=>$qvs['id']
			);
		}
		
		if( ! empty( $new_clauses ) ){
			$new_clauses['relation'] = $this->default_relation;
			$new_clauses[] = $qvs['clauses'];
			$qvs['clauses'] = $new_clauses;
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
	
	public function prepare_sql(){
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
		$from = "{$this->_db->bop_requests}";
		
		//resolve clauses
		$sql_chunks = $this->get_clause_sql( $qvs['clauses'] );
		$join = $sql_chunks['join'];
		$where = $sql_chunks['where'];
		$groupby = $sql_chunks['groupby'];
		$having = $sql_chunks['having'];
		
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
		
		if( $limit > 0 && $offset % $limit == 0 ){
			$this->per_page = $limit;
			$this->page = $offset / $limit;
		}else{
			$this->per_page = false;
			$this->page = false;
		}
		
		if( trim( $limit ) == "1" ){
			$this->single_item = true;
			$calc_rows = "";
		}else{
			$this->single_item = false;
			$calc_rows = "SQL_CALC_FOUND_ROWS";
		}
		
		
		$this->sql = "SELECT $calc_rows $select";
		$this->sql .= "\nFROM $from";
		if( $join )
			$this->sql .= "\n$join";
		if( $where )
			$this->sql .= "\nWHERE $where";
		if( $groupby )
			$this->sql .= "\nGROUP BY $groupby";
		if( $groupby && $having )
			$this->sql .= "\nHAVING $having";
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
		$groupby = "";
		$having = "";
		
		$table_aliases = array();
		
		foreach( $clauses as $k=>$clause ){
			
			if( $k === 'meta_query' ){
				
				$mq = new WP_Meta_Query( $clause );
				$sql_chunks = $mq->get_sql( 'request', $this->_db->bop_requests, 'request_id', $this );
				
				unset( $mq );
				
			}elseif( $k === 'tax_query' ){
				
				$tq = new WP_Tax_Query( $clause );
				$sql_chunks = $mq->get_sql( 'request', $this->_db->bop_requests, 'request_id', $this );
				
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
								$sql_chunks['join'] = "LEFT JOIN {$this->_db->bop_requests_user_karma} AS {$table_alias} ON (`request_id` = `{$table_alias}.request_id`)";
							}
						break;
						
						case 'requestee_id':
							$table_alias = "brq_requests_requestees";
							if( ! in_array( $table_alias, $table_aliases ) ){
								$table_aliases[] = $table_alias;
								$sql_chunks['join'] = "LEFT JOIN {$this->_db->bop_requests_requestees} AS {$table_alias} ON (`request_id` = `{$table_alias}.request_id`)";
							}
						break;
						
						case 'comment_id':
							$table_alias = "brq_requests_comments";
							if( ! in_array( $table_alias, $table_aliases ) ){
								$table_aliases[] = $table_alias;
								$sql_chunks['join'] = "LEFT JOIN {$this->_db->bop_requests_requestees} AS {$table_alias} ON (`request_id` = `{$table_alias}.request_id`)";
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
							$column = "`{$table_alias}.requestee_id`";
						break;
						
						case 'comment_id':
							$column = "`{$table_alias}.comment_id`";
						break;
					}
					
					//compare for where
					$compare = $clause['compare'];
					
					switch( $compare ){
						case 'IN':
						case 'NOT IN':
							$value_format = '(' . substr( str_repeat( ',%s', count( $clause['value'] ) ), 1 ) . ')';
							$value = $this->_db->prepare( $value_format, $clause['value'] );
						break;
						
						case 'BETWEEN':
						case 'NOT BETWEEN':
							$value = $this->_db->prepare( '%s AND %s', $clause['value'] );
						break;
						
						case 'LIKE':
						case 'NOT LIKE':
							$value = '%' . $this->_db->esc_like( $clause['value'] ) . '%';
							$value = $this->_db->prepare( '%s', $value );
						break;
						
						default:
							$value = $this->_db->prepare( '%s', $meta_value );
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
			$where .= "\n{$clauses['relation']} ( {$sql_chunks['where']} )";
			
		}
		
		return array( 'join'=>$join, 'where'=>$where, 'groupby'=>$groupby, 'having'=>$having );
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
	
	public function run_sql(){
		$this->collection = $this->_db->get_results( $this->sql );
		
		if( $this->single_item ){
			$this->total_count = 1;
			$this->count = 1;
		}else{
			$this->total_count = $this->_db( "SELECT FOUND_ROWS()" );
			$this->count = count( $this->collection );
		}
	}
	
	public function next_item(){
		if( ++$this->current_index < $this->count ){
			$this->in_the_loop = true;
			return $this->current_item = $this->collection[$this->current_index];
		}else{
			$this->rewind_collection();
			return false;
		}
	}
	
	public function to_item( $i ){
		if( $i == -1 ){
			$this->rewind_collection();
			return false;
		}
		
		if( $i < -1 || $i > $this->count )
			return false;
		
		$this->current_index = $i;
		$this->in_the_loop = true;
		return $this->current_item = $this->collection[$i];
	}
	
	public function rewind_collection(){
		$this->current_index = -1;
		$this->in_the_loop = false;
		if( $this->count > 0 )
			$this->current_item = $this->collection[0];
	}
	
	public function fetch_collection(){
		
		if( $this->use_query_cache ){
			$this->collection = wp_cache_get( $this->query_vars_hash, 'bop_requests_queries' );
		}else{		
			$this->prepare_sql();
			$this->run_sql();
		}
		
		$ids = $this->get_ids_from_collection();
		
		if( $this->query_vars['cache_query_result'] && $this->query_vars['cache_each_item'] ){
			wp_cache_add( $this->query_vars_hash, $ids, 'bop_requests_queries' );
		}
		
		if( $this->query_vars_for_sql['fields'] == 'ids' ){
			$this->collection = $ids;
		}else{
			for( $i=0; $i<count( $this->collection ); $i++ ){
				$this->collection[$i] = new Bop_Request( $this->collection[$i], $this->query_vars['cache_each_item'] );
			}
		}
		
		return $this->collection;
	}
	
	public function get_ids_from_collection(){
		if( empty( $this->collection ) )
			return array();
		
		if( is_numeric( $this->collection[0] ) )
			return $this->collection;
		
		$_current_index = $this->current_index;
		$this->rewind_collection();
		
		$ids = array();
		while( $item = $this->next_item() ){
			$ids[] = $item->id;
		}
		
		$this->to_item( $_current_index );
		
		return $ids;
	}
	
	public function get_comment_ids(){
		$ids = $this->get_ids_from_collection();
		
		if( empty( $ids ) )
			return array();
		
		$cnrs = $this->_db->get_results( $this->_db->prepare( "SELECT request_id, comment_id FROM {$this->_db->bop_requests_comments} WHERE request_id IN (" . implode( ", ", array_fill( 0, count( $ids ), "%d" ) ) . ")" );
		
		//order by request id and as flat list
		$cids = array();
		$c2rs = array();
		for( $i=0; $i<count( $cnrs ); $i++ ){
			if( ! isset( $c2rs[$cnrs[$i]['request_id']] ) )
				$c2rs[$cnrs[$i]['request_id']] = array();
				
			$c2rs[$cnrs[$i]['request_id']][] = $cnrs[$i]['comment_id'];
			
			$cids[] = $cnrs[$i]['comment_id'];
		}
		
		//attach appropriately to requests in collection
		if( ! is_numeric( $this->collection[0] ) ){
			for( $i=0; $i<count( $this->collection ); $i++ ){
				if( isset( $c2rs[$this->collection[$i]->id] )
					$this->collection[$i]->comment_ids = $c2rs[$this->collection[$i]->id];
			}
		}
		
		//return flat list
		return $cids;
	}
	
	public function get_requestee_ids(){
		$ids = $this->get_ids_from_collection();
		
		if( empty( $ids ) )
			return array();
		
		$cnrs = $this->_db->get_results( $this->_db->prepare( "SELECT request_id, user_id FROM {$this->_db->bop_requests_requestees} WHERE request_id IN (" . implode( ", ", array_fill( 0, count( $ids ), "%d" ) ) . ")" );
		
		//order by request id and as flat list
		$uids = array();
		$u2rs = array();
		for( $i=0; $i<count( $unrs ); $i++ ){
			if( ! isset( $u2rs[$unrs[$i]['request_id']] ) )
				$u2rs[$unrs[$i]['request_id']] = array();
				
			$u2rs[$unrs[$i]['request_id']][] = $unrs[$i]['user_id'];
			
			$uids[] = $unrs[$i]['user_id'];
		}
		
		//attach appropriately to requests in collection
		if( ! is_numeric( $this->collection[0] ) ){
			for( $i=0; $i<count( $this->collection ); $i++ ){
				if( isset( $u2rs[$this->collection[$i]->id] )
					$this->collection[$i]->requestee_ids = $u2rs[$this->collection[$i]->id];
			}
		}
		
		//return flat list
		return $uids;
	}
	
	public function get_author_ids(){
		if( empty( $this->colleciton ) || is_numeric( $this->collection[0] ) )
			return array();
			
		$_current_index = $this->current_index;
		$this->rewind_collection();
		
		$ids = array();
		while( $item = $this->next_item() ){
			$ids[] = $item->author_id;
		}
		
		$this->to_item( $_current_index );
		
		return $ids;
	}
	
	public function get_parent_ids(){
		if( empty( $this->colleciton ) || is_numeric( $this->collection[0] ) )
			return array();
			
		$_current_index = $this->current_index;
		$this->rewind_collection();
		
		$ids = array();
		while( $item = $this->next_item() ){
			if( ! isset( $ids[$item->parent_class] ) )
				$ids[$item->parent_class] = array();
			
			$ids[$item->parent_class][] = $item->parent_id;
		}
		
		$this->to_item( $_current_index );
		
		return $ids;
	}
}
