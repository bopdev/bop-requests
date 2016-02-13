<?php

//Reject if accessed directly or when not uninstalling
defined( 'WP_UNINSTALL_PLUGIN' ) || die( 'Our survey says: ... X.' );


//Uninstall code - remove everything with wiping
global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->bop_requests}" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->bop_requests_user_karma}" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->bop_requests_comments}" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->bop_requests_requestees}" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->bop_requests_meta}" );


wp_delete_post( get_option( 'bop_requests_master_post_id' ), true );
delete_option( 'bop_requests_master_post_id' );

delete_site_option( 'Bop_Requests_version' );
