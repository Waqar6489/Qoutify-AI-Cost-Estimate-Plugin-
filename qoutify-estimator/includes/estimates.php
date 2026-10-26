<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qtf_now() {
	return current_time( 'mysql', true );
}

/**
 * Create a new draft. The user id is always the logged-in user.
 */
function qtf_estimate_create( $user_id, $type ) {
	global $wpdb;
	$type = ( 'commercial' === $type ) ? 'commercial' : 'residential';
	$now  = qtf_now();
	$ok   = $wpdb->insert(
		qtf_table( 'estimates' ),
		array(
			'uuid'         => wp_generate_uuid4(),
			'user_id'      => (int) $user_id,
			'project_type' => $type,
			'path'         => 'quick',
			'status'       => 'draft',
			'is_team'      => qtf_user_is_staff( $user_id ) ? 1 : 0,
			'created_at'   => $now,
			'updated_at'   => $now,
		)
	);
	if ( ! $ok ) {
		return null;
	}
	return qtf_estimate_by_id( $wpdb->insert_id );
}

function qtf_estimate_by_id( $id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . qtf_table( 'estimates' ) . ' WHERE id = %d', (int) $id ) );
}

/**
 * Looks up an estimate by its public id. A user id is required, so a user can only ever load their own.
 */
function qtf_estimate_by_uuid( $uuid, $user_id ) {
	global $wpdb;
	if ( ! preg_match( '/^[a-f0-9\-]{36}$/', (string) $uuid ) ) {
		return null;
	}
	return $wpdb->get_row(
		$wpdb->prepare( 'SELECT * FROM ' . qtf_table( 'estimates' ) . ' WHERE uuid = %s AND user_id = %d', $uuid, (int) $user_id )
	);
}

function qtf_estimate_update( $id, $data ) {
	global $wpdb;
	$data['updated_at'] = qtf_now();
	return $wpdb->update( qtf_table( 'estimates' ), $data, array( 'id' => (int) $id ) );
}

function qtf_estimate_details( $row ) {
	$details = $row && $row->details ? json_decode( $row->details, true ) : array();
	return is_array( $details ) ? $details : array();
}

function qtf_estimate_result( $row ) {
	$result = $row && $row->result ? json_decode( $row->result, true ) : array();
	return is_array( $result ) ? $result : array();
}

function qtf_estimate_title( $row ) {
	$d     = qtf_estimate_details( $row );
	$parts = array();
	foreach ( array( 'street', 'city', 'state' ) as $key ) {
		if ( ! empty( $d[ $key ] ) ) {
			$parts[] = $d[ $key ];
		}
	}
	return $parts ? implode( ', ', $parts ) : __( 'Untitled estimate', 'qoutify-estimator' );
}

function qtf_estimate_list( $user_id, $page = 1, $per_page = 10 ) {
	global $wpdb;
	$offset = max( 0, ( (int) $page - 1 ) * (int) $per_page );
	return $wpdb->get_results(
		$wpdb->prepare(
			'SELECT * FROM ' . qtf_table( 'estimates' ) . ' WHERE user_id = %d ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d',
			(int) $user_id,
			(int) $per_page,
			$offset
		)
	);
}

function qtf_estimate_total_count( $user_id ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . qtf_table( 'estimates' ) . ' WHERE user_id = %d', (int) $user_id ) );
}

/**
 * Estimates that were submitted (not drafts) count toward "total estimates" and the daily limit.
 */
function qtf_submitted_today( $user_id ) {
	global $wpdb;
	$since = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM ' . qtf_table( 'estimates' ) . " WHERE user_id = %d AND status <> 'draft' AND created_at >= %s",
			(int) $user_id,
			$since
		)
	);
}

function qtf_estimate_completed_count( $user_id ) {
	global $wpdb;
	return (int) $wpdb->get_var(
		$wpdb->prepare( 'SELECT COUNT(*) FROM ' . qtf_table( 'estimates' ) . " WHERE user_id = %d AND status = 'completed'", (int) $user_id )
	);
}

function qtf_draft_count( $user_id ) {
	global $wpdb;
	return (int) $wpdb->get_var(
		$wpdb->prepare( 'SELECT COUNT(*) FROM ' . qtf_table( 'estimates' ) . " WHERE user_id = %d AND status = 'draft'", (int) $user_id )
	);
}

function qtf_latest_estimate( $user_id ) {
	global $wpdb;
	return $wpdb->get_row(
		$wpdb->prepare( 'SELECT * FROM ' . qtf_table( 'estimates' ) . " WHERE user_id = %d AND status <> 'draft' ORDER BY created_at DESC, id DESC LIMIT 1", (int) $user_id )
	);
}

/**
 * Deletes the estimate, its files and its usage rows.
 */
function qtf_estimate_delete( $row ) {
	global $wpdb;
	if ( ! $row ) {
		return;
	}
	foreach ( qtf_docs_list( $row->id ) as $doc ) {
		qtf_doc_delete( $doc );
	}
	$wpdb->delete( qtf_table( 'ai_usage' ), array( 'estimate_id' => (int) $row->id ) );
	$wpdb->delete( qtf_table( 'estimates' ), array( 'id' => (int) $row->id ) );
}

/**
 * Account deletion: remove every estimate and file of the user.
 */
function qtf_delete_user_data( $user_id ) {
	global $wpdb;
	$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . qtf_table( 'estimates' ) . ' WHERE user_id = %d', (int) $user_id ) );
	foreach ( $ids as $id ) {
		qtf_estimate_delete( qtf_estimate_by_id( $id ) );
	}
}
add_action( 'qtf_before_delete_account', 'qtf_delete_user_data' );

/* ---------------------------------------------------------------- documents */

function qtf_docs_list( $estimate_id ) {
	global $wpdb;
	return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . qtf_table( 'documents' ) . ' WHERE estimate_id = %d ORDER BY id ASC', (int) $estimate_id ) );
}

function qtf_doc_get( $doc_id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . qtf_table( 'documents' ) . ' WHERE id = %d', (int) $doc_id ) );
}

function qtf_private_dir() {
	$upload = wp_upload_dir();
	$dir    = trailingslashit( $upload['basedir'] ) . 'qtf-private';
	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
		file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	return $dir;
}

function qtf_doc_path( $doc ) {
	return trailingslashit( qtf_private_dir() ) . basename( $doc->stored_name );
}

function qtf_doc_delete( $doc ) {
	global $wpdb;
	$path = qtf_doc_path( $doc );
	if ( file_exists( $path ) ) {
		wp_delete_file( $path );
	}
	$wpdb->delete( qtf_table( 'documents' ), array( 'id' => (int) $doc->id ) );
}

function qtf_doc_categories() {
	return array(
		'blueprint' => __( 'Blueprint', 'qoutify-estimator' ),
		'scope'     => __( 'Scope of Work', 'qoutify-estimator' ),
		'photo'     => __( 'Photos / other', 'qoutify-estimator' ),
	);
}

function qtf_allowed_mimes() {
	return array(
		'pdf'  => 'application/pdf',
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'png'  => 'image/png',
		'txt'  => 'text/plain',
	);
}

/**
 * Stores the files posted in $_FILES[ $field ] for one category. Returns array( saved count, error code or '' ).
 */
function qtf_store_uploads( $estimate, $field, $category ) {
	global $wpdb;
	if ( empty( $_FILES[ $field ]['name'] ) || ! is_array( $_FILES[ $field ]['name'] ) ) { // phpcs:ignore WordPress.Security
		return array( 0, '' );
	}
	$files    = $_FILES[ $field ]; // phpcs:ignore WordPress.Security
	$max_each = max( 1, (int) qtf_setting( 'max_file_mb' ) ) * MB_IN_BYTES;
	$max_num  = max( 1, (int) qtf_setting( 'max_files' ) );
	$max_all  = 50 * MB_IN_BYTES;
	$existing = qtf_docs_list( $estimate->id );
	$count    = count( $existing );
	$total    = 0;
	foreach ( $existing as $doc ) {
		$total += (int) $doc->size_bytes;
	}
	$mimes = qtf_allowed_mimes();
	$saved = 0;
	$error = '';

	foreach ( $files['name'] as $i => $name ) {
		if ( '' === $name ) {
			continue;
		}
		$err  = isset( $files['error'][ $i ] ) ? (int) $files['error'][ $i ] : UPLOAD_ERR_NO_FILE;
		$tmp  = isset( $files['tmp_name'][ $i ] ) ? $files['tmp_name'][ $i ] : '';
		$size = isset( $files['size'][ $i ] ) ? (int) $files['size'][ $i ] : 0;

		if ( UPLOAD_ERR_OK !== $err || ! $tmp || ! is_uploaded_file( $tmp ) ) {
			$error = 'doc_fail';
			continue;
		}
		if ( $size < 1 || $size > $max_each ) {
			$error = 'doc_big';
			continue;
		}
		if ( $count >= $max_num ) {
			$error = 'doc_many';
			break;
		}
		if ( $total + $size > $max_all ) {
			$error = 'doc_big';
			break;
		}

		$check = wp_check_filetype( sanitize_file_name( $name ), $mimes );
		$ext   = strtolower( (string) $check['ext'] );
		if ( ! $ext ) {
			$error = 'doc_type';
			continue;
		}
		// The real content type must match too.
		$real = '';
		if ( function_exists( 'finfo_open' ) ) {
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			if ( $finfo ) {
				$real = (string) finfo_file( $finfo, $tmp );
				finfo_close( $finfo );
			}
		}
		if ( ! in_array( $real, array( 'application/pdf', 'image/jpeg', 'image/png', 'text/plain' ), true ) || $real !== $check['type'] ) {
			$error = 'doc_type';
			continue;
		}

		$stored = strtolower( wp_generate_password( 32, false, false ) ) . '.' . $ext;
		$dest   = trailingslashit( qtf_private_dir() ) . $stored;
		if ( ! move_uploaded_file( $tmp, $dest ) ) {
			$error = 'doc_fail';
			continue;
		}
		$wpdb->insert(
			qtf_table( 'documents' ),
			array(
				'estimate_id'   => (int) $estimate->id,
				'user_id'       => (int) $estimate->user_id,
				'original_name' => substr( sanitize_file_name( $name ), 0, 200 ),
				'stored_name'   => $stored,
				'mime'          => $real,
				'size_bytes'    => $size,
				'sha256'        => hash_file( 'sha256', $dest ),
				'category'      => $category,
				'created_at'    => qtf_now(),
			)
		);
		++$count;
		$total += $size;
		++$saved;
	}
	return array( $saved, $error );
}

/**
 * Logged-in download endpoint. Only the owner (or an administrator) can open a file.
 * Link: ?qtf_file=ID&_wpnonce=...
 */
function qtf_doc_url( $doc ) {
	return add_query_arg(
		array(
			'qtf_file' => (int) $doc->id,
			'_wpnonce' => wp_create_nonce( 'qtf_file_' . (int) $doc->id ),
		),
		qtf_dashboard_url( 'estimate' )
	);
}

function qtf_serve_file() {
	if ( empty( $_GET['qtf_file'] ) || ! is_user_logged_in() ) {
		return;
	}
	$id    = absint( $_GET['qtf_file'] );
	$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
	$doc   = qtf_doc_get( $id );
	if ( ! $doc || ! wp_verify_nonce( $nonce, 'qtf_file_' . $id ) ) {
		wp_die( esc_html__( 'File not found.', 'qoutify-estimator' ), '', array( 'response' => 404 ) );
	}
	if ( (int) $doc->user_id !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'File not found.', 'qoutify-estimator' ), '', array( 'response' => 404 ) );
	}
	$path = qtf_doc_path( $doc );
	if ( ! file_exists( $path ) ) {
		wp_die( esc_html__( 'File not found.', 'qoutify-estimator' ), '', array( 'response' => 404 ) );
	}
	nocache_headers();
	header( 'Content-Type: ' . $doc->mime );
	header( 'Content-Length: ' . filesize( $path ) );
	header( 'Content-Disposition: inline; filename="' . sanitize_file_name( $doc->original_name ) . '"' );
	header( 'X-Content-Type-Options: nosniff' );
	readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
}
add_action( 'template_redirect', 'qtf_serve_file', 2 );
