<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qtf_redirect( $url, $code = '' ) {
	if ( $code ) {
		$url = add_query_arg( 'qtf_msg', $code, $url );
	}
	wp_safe_redirect( $url );
	exit;
}

function qtf_wizard_url( $uuid = '', $step = '' ) {
	$args = array();
	if ( $uuid ) {
		$args['id'] = $uuid;
	}
	if ( $step ) {
		$args['step'] = $step;
	}
	return add_query_arg( $args, qtf_dashboard_url( 'new-estimate' ) );
}

function qtf_estimate_url( $uuid ) {
	return add_query_arg( 'id', $uuid, qtf_dashboard_url( 'estimate' ) );
}

/** Errors and typed values passed from the POST handler to the view in the same request. */
function qtf_wizard_state( $key, $value = null ) {
	static $state = array();
	if ( null !== $value ) {
		$state[ $key ] = $value;
	}
	return isset( $state[ $key ] ) ? $state[ $key ] : null;
}

/**
 * Handles the forms of the New Estimate wizard, the delete button and the document delete button.
 * Every action checks: logged in, nonce, and that the estimate belongs to the current user.
 */
function qtf_process_dashboard_post() {
	if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] || empty( $_POST['qtf_action'] ) ) {
		return;
	}
	if ( ! is_user_logged_in() || ! qtf_is_dashboard_request() ) {
		return;
	}
	$view = qtf_current_view();
	if ( 'settings' === $view ) {
		return; // The Settings forms are handled in settings-actions.php.
	}

	$action = sanitize_key( wp_unslash( $_POST['qtf_action'] ) );
	$nonce  = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'qtf_' . $action ) ) {
		qtf_redirect( qtf_dashboard_url(), 'nonce' );
	}

	$user_id  = get_current_user_id();
	$uuid     = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
	$estimate = $uuid ? qtf_estimate_by_uuid( $uuid, $user_id ) : null;

	switch ( $action ) {
		case 'wizard_type':
			if ( 'new' !== $view ) {
				return;
			}
			$types = qtf_project_types();
			$type  = isset( $_POST['project_type'] ) ? sanitize_key( wp_unslash( $_POST['project_type'] ) ) : '';
			if ( ! isset( $types[ $type ] ) ) {
				qtf_redirect( qtf_wizard_url(), 'missing' );
			}
			// The balance must cover at least the Quick estimate of the chosen type.
			if ( ! qtf_user_is_staff() && qtf_get_credits() < qtf_credit_cost( $type, 'quick' ) ) {
				qtf_redirect( qtf_wizard_url(), 'nocredit' );
			}
			if ( qtf_draft_count( $user_id ) >= 5 ) {
				qtf_redirect( qtf_dashboard_url( 'my-estimates' ), 'drafts_full' );
			}
			$new = qtf_estimate_create( $user_id, $type );
			if ( ! $new ) {
				qtf_redirect( qtf_wizard_url(), 'missing' );
			}
			qtf_redirect( qtf_wizard_url( $new->uuid, 'details' ) );
			break;

		case 'wizard_details':
			if ( 'new' !== $view || ! $estimate || 'draft' !== $estimate->status ) {
				qtf_redirect( qtf_wizard_url(), 'missing' );
			}
			$input = isset( $_POST['qtf_d'] ) && is_array( $_POST['qtf_d'] ) ? $_POST['qtf_d'] : array(); // phpcs:ignore WordPress.Security
			list( $clean, $errors ) = qtf_validate_details( $estimate->project_type, $input );
			if ( $errors ) {
				// Show the form again with the errors (no redirect, so the typed values stay).
				qtf_wizard_state( 'errors', $errors );
				qtf_wizard_state( 'values', $clean );
				return;
			}
			qtf_estimate_update( $estimate->id, array( 'details' => wp_json_encode( $clean ) ) );
			qtf_redirect( qtf_wizard_url( $estimate->uuid, 'documents' ) );
			break;

		case 'wizard_docs':
			if ( 'new' !== $view || ! $estimate || 'draft' !== $estimate->status ) {
				qtf_redirect( qtf_wizard_url(), 'missing' );
			}
			$saved = 0;
			$error = '';
			$map   = array(
				'blueprint' => 'qtf_blueprint',
				'scope'     => 'qtf_scope',
				'photo'     => 'qtf_photo',
			);

			// Server-side gate: documents make it a Detailed estimate, which needs more credits.
			// The browser only hides the button; this check cannot be skipped.
			$posted_files = false;
			foreach ( $map as $field ) {
				if ( ! empty( $_FILES[ $field ]['name'] ) && is_array( $_FILES[ $field ]['name'] ) && array_filter( $_FILES[ $field ]['name'] ) ) { // phpcs:ignore WordPress.Security
					$posted_files = true;
				}
			}
			if ( $posted_files && ! qtf_user_is_staff( $user_id ) && qtf_get_credits( $user_id ) < qtf_credit_cost( $estimate->project_type, 'detailed' ) ) {
				qtf_redirect( qtf_wizard_url( $estimate->uuid, 'documents' ), 'docs_nocredit' );
			}

			foreach ( $map as $category => $field ) {
				list( $n, $err ) = qtf_store_uploads( $estimate, $field, $category );
				$saved          += $n;
				if ( $err ) {
					$error = $err;
				}
			}
			$count = count( qtf_docs_list( $estimate->id ) );
			qtf_estimate_update( $estimate->id, array( 'path' => $count > 0 ? 'detailed' : 'quick' ) );

			$next = isset( $_POST['qtf_next'] ) ? sanitize_key( wp_unslash( $_POST['qtf_next'] ) ) : '';
			$code = $error ? $error : ( $saved > 0 ? 'docs_ok' : '' );
			if ( 'continue' === $next ) {
				qtf_redirect( qtf_wizard_url( $estimate->uuid, $error ? 'documents' : 'review' ), $error );
			}
			qtf_redirect( qtf_wizard_url( $estimate->uuid, 'documents' ), $code );
			break;

		case 'doc_delete':
			if ( 'new' !== $view || ! $estimate || 'draft' !== $estimate->status ) {
				qtf_redirect( qtf_wizard_url(), 'missing' );
			}
			$doc = qtf_doc_get( isset( $_POST['doc_id'] ) ? absint( $_POST['doc_id'] ) : 0 );
			if ( $doc && (int) $doc->estimate_id === (int) $estimate->id ) {
				qtf_doc_delete( $doc );
				$count = count( qtf_docs_list( $estimate->id ) );
				qtf_estimate_update( $estimate->id, array( 'path' => $count > 0 ? 'detailed' : 'quick' ) );
			}
			qtf_redirect( qtf_wizard_url( $estimate->uuid, 'documents' ), 'doc_removed' );
			break;

		case 'wizard_submit':
			if ( 'new' !== $view || ! $estimate ) {
				qtf_redirect( qtf_wizard_url(), 'missing' );
			}
			if ( 'draft' !== $estimate->status ) {
				qtf_redirect( qtf_estimate_url( $estimate->uuid ) );
			}
			if ( empty( $_POST['qtf_ack'] ) ) {
				qtf_redirect( qtf_wizard_url( $estimate->uuid, 'review' ), 'ack' );
			}
			$details = qtf_estimate_details( $estimate );
			list( , $errors ) = qtf_validate_details( $estimate->project_type, $details, false );
			if ( $errors ) {
				qtf_redirect( qtf_wizard_url( $estimate->uuid, 'details' ), 'incomplete' );
			}
			$limit = (int) qtf_setting( 'daily_limit' );
			if ( $limit > 0 && qtf_submitted_today( $user_id ) >= $limit ) {
				qtf_redirect( qtf_wizard_url( $estimate->uuid, 'review' ), 'limit' );
			}

			$path = count( qtf_docs_list( $estimate->id ) ) > 0 ? 'detailed' : 'quick';
			$team = qtf_user_is_staff( $user_id );
			$cost = $team ? 0 : qtf_credit_cost( $estimate->project_type, $path );
			if ( ! $team && ! qtf_credits_spend( $user_id, $cost, $estimate->id ) ) {
				qtf_redirect( qtf_wizard_url( $estimate->uuid, 'review' ), 'nocredit' );
			}
			qtf_estimate_update(
				$estimate->id,
				array(
					'status'          => 'queued',
					'path'            => $path,
					'credits_charged' => $cost,
					'is_team'         => $team ? 1 : 0,
				)
			);
			qtf_enqueue_estimate( $estimate->id );
			qtf_redirect( qtf_estimate_url( $estimate->uuid ) );
			break;

		case 'estimate_delete':
			if ( ! $estimate ) {
				qtf_redirect( qtf_dashboard_url( 'my-estimates' ), 'missing' );
			}
			if ( in_array( $estimate->status, array( 'queued', 'processing' ), true ) ) {
				qtf_redirect( qtf_dashboard_url( 'my-estimates' ), 'busy' );
			}
			qtf_estimate_delete( $estimate );
			qtf_redirect( qtf_dashboard_url( 'my-estimates' ), 'deleted' );
			break;
	}
}
add_action( 'template_redirect', 'qtf_process_dashboard_post', 3 );
