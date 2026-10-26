<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'QTF_DB_VERSION', '2' );

function qtf_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'qtf_' . $name;
}

/**
 * Creates or updates the plugin tables. dbDelta needs this exact layout.
 */
function qtf_create_tables() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();

	$estimates = 'CREATE TABLE ' . qtf_table( 'estimates' ) . " (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	uuid char(36) NOT NULL,
	user_id bigint(20) unsigned NOT NULL,
	project_type varchar(20) NOT NULL,
	path varchar(20) NOT NULL DEFAULT 'quick',
	status varchar(20) NOT NULL DEFAULT 'draft',
	details longtext NULL,
	credits_charged int(11) NOT NULL DEFAULT 0,
	is_team tinyint(1) NOT NULL DEFAULT 0,
	error_message text NULL,
	total_low decimal(14,2) NULL,
	total_high decimal(14,2) NULL,
	confidence tinyint(3) unsigned NULL,
	result longtext NULL,
	ai_model varchar(100) NULL,
	created_at datetime NOT NULL,
	updated_at datetime NOT NULL,
	completed_at datetime NULL,
	PRIMARY KEY  (id),
	UNIQUE KEY uuid (uuid),
	KEY user_status (user_id,status),
	KEY created_at (created_at)
) $charset;";

	$documents = 'CREATE TABLE ' . qtf_table( 'documents' ) . " (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	estimate_id bigint(20) unsigned NOT NULL,
	user_id bigint(20) unsigned NOT NULL,
	original_name varchar(255) NOT NULL,
	stored_name varchar(100) NOT NULL,
	mime varchar(100) NOT NULL,
	size_bytes bigint(20) unsigned NOT NULL DEFAULT 0,
	sha256 char(64) NOT NULL,
	category varchar(20) NOT NULL,
	created_at datetime NOT NULL,
	PRIMARY KEY  (id),
	KEY estimate_id (estimate_id)
) $charset;";

	$ledger = 'CREATE TABLE ' . qtf_table( 'credit_ledger' ) . " (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	user_id bigint(20) unsigned NOT NULL,
	delta int(11) NOT NULL,
	reason varchar(20) NOT NULL,
	estimate_id bigint(20) unsigned NULL,
	order_id bigint(20) unsigned NULL,
	idem_key varchar(100) NOT NULL,
	note varchar(255) NULL,
	created_at datetime NOT NULL,
	PRIMARY KEY  (id),
	UNIQUE KEY idem_key (idem_key),
	KEY user_id (user_id)
) $charset;";

	$usage = 'CREATE TABLE ' . qtf_table( 'ai_usage' ) . " (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	estimate_id bigint(20) unsigned NOT NULL,
	model varchar(100) NOT NULL,
	input_tokens int(11) NOT NULL DEFAULT 0,
	output_tokens int(11) NOT NULL DEFAULT 0,
	duration_ms int(11) NOT NULL DEFAULT 0,
	status varchar(20) NOT NULL,
	error_code varchar(100) NULL,
	created_at datetime NOT NULL,
	PRIMARY KEY  (id),
	KEY estimate_id (estimate_id)
) $charset;";

	$reviews = 'CREATE TABLE ' . qtf_table( 'reviews' ) . " (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	user_id bigint(20) unsigned NOT NULL,
	estimate_id bigint(20) unsigned NOT NULL,
	rating tinyint(3) unsigned NOT NULL,
	body text NOT NULL,
	status varchar(20) NOT NULL DEFAULT 'pending',
	created_at datetime NOT NULL,
	updated_at datetime NOT NULL,
	moderated_at datetime NULL,
	PRIMARY KEY  (id),
	UNIQUE KEY user_estimate (user_id,estimate_id),
	KEY status_created (status,created_at)
) $charset;";

	dbDelta( $estimates );
	dbDelta( $documents );
	dbDelta( $ledger );
	dbDelta( $usage );
	dbDelta( $reviews );

	update_option( 'qtf_db_version', QTF_DB_VERSION );
}

function qtf_maybe_upgrade_db() {
	if ( get_option( 'qtf_db_version' ) !== QTF_DB_VERSION ) {
		qtf_create_tables();
	}
}
add_action( 'plugins_loaded', 'qtf_maybe_upgrade_db', 20 );
