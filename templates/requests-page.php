<?php 

switch( $_GET['object_class'] ){
	case 'user':
		$object_class = 'user';
		$ob = get_user( $_GET['id'] );
		$object_id = $ob->ID;
		$ob_title = $ob->display_name;
	break;
	case 'post':
	default:
		$object_class = 'post';
		$ob = get_post( $_GET['id'] );
		$object_id = $ob->ID;
		$ob_title = $ob->post_title;
	break;
}

?>

<div class="bop-requests-page">
	<form id="bop-requests-page-form" action="#">
		<?php wp_nonce_field( 'bop-requests-page-form', "bop-requests-page-form-{$object_class}-{$object_id}", false ) ?>
		<h1><?php printf( __( 'Requests <span>for %s</span>', 'bop-requests' ), $ob_title ) ?></h1>
		<div class="request-list">
			<ul>
			</ul>
		</div>
	</form>
</div>
