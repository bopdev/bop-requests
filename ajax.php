<?php 

add_action( 'wp_ajax_bop_requests', function(){
	
	if( isset( $_GET['deed'] ) ){
		
		$deed = $_GET['deed'];
		
		switch( $deed ){
			
			case 'get_request':
				if( ! isset( $_GET['id'] ) ){
					wp_send_json_error( array( 'code'=>'BOPREQAJAX002', 'message'=>'A valid id needs to be provided.' ) );
					die;
				}
				
				$id = $_GET['id'];
					
				if( ! current_user_can( 'get_request.bop_requests', $id ) ){
					wp_send_json_error( array( 'code'=>'BOPREQAJAX001', 'message'=>'User not permitted.' ) );
					die;
				}
				
				if( is_numeric( $id ) ){
					$r = new Bop_Request( $id );
					if( is_object( $r ) ){
						wp_send_json_success( $r );
						die;
					}
				}
				wp_send_json_error( array( 'code'=>'BOPREQAJAX002', 'message'=>'A valid id needs to be provided.' ) );
				die;
				
			break;
			
			case 'get_requests':
				if( ! current_user_can( 'get_requests.bop_requests' ) ){
					wp_send_json_error( array( 'code'=>'BOPREQAJAX001', 'message'=>'User not permitted.' ) );
					die;
				}
				
				$query = array();
				
				//build clauses
				$clauses = array();
				
				if( isset( $_GET['parent_class'] ) && $_GET['parent_class'] && isset( $_GET['parent_id'] ) && $_GET['parent_id'] ){
					$clauses[] = array(
						'key'=>'parent_class',
						'value'=>$_GET['parent_class']
					);
					$clauses[] = array(
						'key'=>'parent_id',
						'value'=>$_GET['parent_id']
					);
				}
				
				if( ! empty( $_GET['status'] ) ){
					$clauses[] = array( 
						'key'=>'status',
						'value'=>$_GET['status']
					);
				}else{
					$clauses[] = array( 
						'key'=>'status',
						'value'=>'pending'
					);
				}
				
				if( ! empty( $_GET['type'] ) ){
					$clauses[] = array( 
						'key'=>'type',
						'value'=>$_GET['type']
					);
				}
				
				if( ! empty( $_GET['author_id'] ) ){
					$clauses[] = array( 
						'key'=>'author_id',
						'value'=>$_GET['author_id']
					);
				}
				
				if( ! empty( $_GET['requestee_id'] ) ){
					$clauses[] = array( 
						'key'=>'requestee_id',
						'value'=>$_GET['requestee_id']
					);
				}
				
				
				//build pagination
				if( ! empty( $_GET['count'] ) ){
					
					$limit = $_GET['count'];
					
					if( ! empty( $_GET['page'] ) ){
						$offset = ( $_GET['page'] - 1 ) * $limit;
					}
				}
				
				
				//build orderby
				if( ! empty( $_GET['orderby'] ) ){
					$orderby = $_GET['orderby'];
					
					if( ! empty( $_GET['order'] ) ){
						$order = $_GET['order'];
					}
				}
				
				
				//combine query
				if( ! empty( $clauses ) )
					$query['clauses'] = $clauses;
					
				if( ! empty( $limit ) )
					$query['limit'] = $limit;
					
				if( ! empty( $offset ) )
					$query['offset'] = $offset;
				
				if( ! empty( $orderby ) )
					$query['orderby'] = array( array( $orderby, $order ) );
				
				$brq = new Bop_Request_Query( $query );
				
				$output = array( 'requests'=>$brq->collection );
				
				wp_send_json_success( $output );
				die;
			break;
			
			case 'get_comment':
				if( ! isset( $_GET['id'] ) ){
					wp_send_json_error( array( 'code'=>'BOPREQAJAX002', 'message'=>'A valid id needs to be provided.' ) );
					die;
				}
				
				$id = $_GET['id'];
				
				if( ! current_user_can( 'get_comment.bop_requests', $id ) ){
					wp_send_json_error( array( 'code'=>'BOPREQAJAX001', 'message'=>'User not permitted.' ) );
					die;
				}
				
				$c = get_comment( $id );
				
				if( ! $c ){
					wp_send_json_error( array( 'code'=>'BOPREQAJAX002', 'message'=>'A valid id needs to be provided.' ) );
				}
				
				wp_send_json_success( $c );
				die;
				
			break;
			
			case 'get_comments':
				if( ! isset( $_GET['request_id'] ) ){
					wp_send_json_error( array( 'code'=>'BOPREQAJAX002', 'message'=>'A valid id needs to be provided.' ) );
					die;
				}
				
				$rid = $_GET['request_id'];
				
				if( ! current_user_can( 'get_comments.bop_requests' ) ){
					wp_send_json_error( array( 'code'=>'BOPREQAJAX001', 'message'=>'User not permitted.' ) );
					die;
				}
				
				$r = new Bop_Request( null, false );
				$r->id = $rid;
				$cids = $r->get_comment_ids();
				
				$cs = get_comments( array( 'comment__in'=>$cids ) );
				
				wp_send_json_success( $cs );
				die;
				
			break;
			
			case 'get_user':
				if( ! isset( $_GET['id'] ) ){
					wp_send_json_error( array( 'code'=>'BOPREQAJAX002', 'message'=>'A valid id needs to be provided.' ) );
					die;
				}
				
				$id = $_GET['id'];
				
				if( ! current_user_can( 'get_user.bop_requests', $id ) ){
					wp_send_json_error( array( 'code'=>'BOPREQAJAX001', 'message'=>'User not permitted.' ) );
					die;
				}
				
				$u = get_user_by( 'id', $id );
				
				if( ! $c ){
					wp_send_json_error( array( 'code'=>'BOPREQAJAX002', 'message'=>'A valid id needs to be provided.' ) );
				}
				
				wp_send_json_success( $u );
				die;
				
			break;
			
		}
		
	}elseif( isset( $_POST['deed'] ) ){
		
		$deed = $_POST['deed'];
		
		switch( $deed ){
			
			case 'add_request':
			
			if( isset( $_POST['parent_class'] ) && isset( $_POST['parent_id'] ) ){
				wp_send_json_error( array( 'code'=>'BOPREQAJAX003', 'message'=>'A valid class (e.g. user, post, comment) and id need to be provided.' ) );
				die;
			}
			
			$pc = $_POST['parent_class'];
			$pid = $_POST['parent_id'];
			
			$type = isset( $_POST['type'] ) ? $_POST['type'] : 'custom';
			
			if( ! current_user_can( 'add_request.bop_requests', $pc, $pid, $type ) ){
				wp_send_json_error( array( 'code'=>'BOPREQAJAX001', 'message'=>'User not permitted.' ) );
				die;
			}
			
			break;
			
			case 'add_comment':
			break;
			
			case 'change_status':
			break;
			
		}
		
	}
	
}, 10 );
