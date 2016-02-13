<?php 

//Reject if accessed directly
defined( 'BOP_PLUGIN_ACTIVATING' ) || die( 'Our survey says: ... X.' );

//Update (or install) script

//DB
global $wpdb;

//Guide: https://codex.wordpress.org/Creating_Tables_with_Plugins
//Check https://core.trac.wordpress.org/browser/trunk/src/wp-admin/includes/schema.php#L0 for example sql


$charset_collate = $wpdb->get_charset_collate();
$max_index_length = 191;

$create_tables_sql = "
CREATE TABLE $wpdb->bop_requests (
	request_id bigint(20) unsigned NOT NULL auto_increment,
	object_class varchar(31) NOT NULL default '',
	object_id bigint(20) unsigned NOT NULL default 0,
	created datetime NOT NULL default '0000-00-00 00:00:00',
	created_gmt datetime NOT NULL default '0000-00-00 00:00:00',
	edited datetime NOT NULL default '0000-00-00 00:00:00',
	edited_gmt datetime NOT NULL default '0000-00-00 00:00:00',
	content text NOT NULL,
	karma int(11) NOT NULL default 0,
	status varchar(20) NOT NULL default 1,
	type varchar(20) NOT NULL default '',
	author_id bigint(20) unsigned NOT NULL default 0,
	PRIMARY KEY  (request_id),
	KEY object_class_and_id (object_class, object_id),
	KEY created_gmt (created_gmt),
	KEY edited_gmt (edited_gmt),
	KEY author_id (author_id)
) $charset_collate;
CREATE TABLE $wpdb->bop_requests_user_karma (
	request_id bigint(20) unsigned NOT NULL default 0,
	user_id bigint(20) unsigned NOT NULL default 0,
	value tinyint(6) signed NOT NULL default 0,
	PRIMARY KEY (request_id, user_id)
) $charset_collate;
CREATE TABLE $wpdb->bop_requests_comments (
	comment_id bigint(20) unsigned NOT NULL default 0,
	request_id bigint(20) unsigned NOT NULL default 0,
	PRIMARY KEY (comment_id)
	KEY request_id (request_id)
) $charset_collate;
CREATE TABLE $wpdb->bop_requests_requestees (
	requestee_id bigint(20) unsigned NOT NULL auto_increment,
	request_id bigint(20) unsigned NOT NULL default 0,
	user_id bigint(20) unsigned NOT NULL default 0,
	PRIMARY KEY (requestee_id),
	KEY request_id (request_id),
	KEY user_id (user_id)
) $charset_collate;
CREATE TABLE $wpdb->bop_requests_meta (
	meta_id bigint(20) unsigned NOT NULL auto_increment,
	request_id bigint(20) unsigned NOT NULL default 0,
	meta_key varchar(255) default NULL,
	meta_value longtext,
	PRIMARY KEY (meta_id)
	KEY request_id (request_id),
	KEY meta_key (meta_key({$max_index_length}));
) $charset_collate;";

require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
dbDelta( $create_tables_sql );

$master_id = wp_insert_post( array( 'post_type'=>'bop_requests_master', 'post_title'=>'Bop Requests Master', 'post_content'=>'Bop Requests Master Post.', 'post_status'=>'master' ) );

add_option( 'bop_requests_master_post_id', $master_id, '', false );

unset( $create_tables_sql, $charset_collate, $max_index_length );
