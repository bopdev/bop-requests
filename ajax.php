<?php 

add_action( 'wp_ajax_bop_requests', function(){
	
	if( isset( $_POST['deed'] ) ){
		
		$item_type = '';
		if( isset( $_POST['item_type'] ) ){
			$item_type = $_POST['item_type'];
		}
		
		$object_class = '';
		if( isset( $_POST['object_class'] ) ){
			$object_class = $_POST['object_class'];
		}
		
		$object_id = '';
		if( isset( $_POST['object_id'] ) ){
			$object_id = $_POST['object_id'];
		}
		
		
		if( ! isset( $_POST['bop-requests-page-form'] ) || ! wp_verify_nonce( $_POST["bop-requests-page-form"], "bop-requests-page-form-{$object_class}-{$object_id}" ) )
			return;
		
		
		switch( $_POST['deed'] ){
			case 'add':
				if( $item_type == 'request' ){
					if( current_user_can( 'add_request.bop_requests', $object_class, $object_id ) ){
						
					}
				}elseif( $item_type == 'request-reply' ){
					if( current_user_can( 'add_request-reply.bop_requests', $request_id, $object_class, $object_id ) ){
						
					}
				}
			break;
			case 'edit':
				if( $item_type == 'request' ){
					if( current_user_can( 'edit_request.bop_requests', $request_id, $object_class, $object_id ) ){
						
					}
				}elseif( $item_type == 'request-reply' ){
					if( current_user_can( 'edit_request-reply.bop_requests', $reply_id, $request_id, $object_class, $object_id ) ){
						
					}
				}
			break;
			case 'delete':
				if( $item_type == 'request' ){
					if( current_user_can( 'delete_request.bop_requests', $request_id, $object_class, $object_id ) ){
						
					}
				}elseif( $item_type == 'request-reply' ){
					if( current_user_can( 'delete_request-reply.bop_requests', $reply_id, $request_id, $object_class, $object_id ) ){
						
					}
				}
			break;
		}
		
	}elseif( isset( $_GET['deed'] ) ){
		
		$item_type = '';
		if( isset( $_GET['item_type'] ) ){
			$item_type = $_GET['item_type'];
		}
		
		$object_class = '';
		if( isset( $_GET['object_class'] ) ){
			$object_class = $_GET['object_class'];
		}
		
		$object_id = '';
		if( isset( $_GET['object_id'] ) ){
			$object_id = $_GET['object_id'];
		}
		
		switch( $_GET['deed'] ){
			case 'view':
				
			break;
		}
		
	}
	
} );
