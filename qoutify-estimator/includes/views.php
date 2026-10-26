<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------ helpers */

function qtf_messages() {
	return array_merge(
		qtf_settings_messages(),
		array(
			'nocredit'      => array( 'error', __( 'You do not have enough credits for this estimate. Please buy more credits.', 'qoutify-estimator' ) ),
			'docs_nocredit' => array( 'error', __( 'Your files were not uploaded because you do not have enough credits for a Detailed Estimate. See the requirement below.', 'qoutify-estimator' ) ),
			'drafts_full'   => array( 'error', __( 'You have 5 unfinished drafts. Finish or delete one first.', 'qoutify-estimator' ) ),
			'missing'       => array( 'error', __( 'That estimate was not found.', 'qoutify-estimator' ) ),
			'ack'           => array( 'error', __( 'Please confirm that you understand this is a preliminary estimate.', 'qoutify-estimator' ) ),
			'limit'         => array( 'error', __( 'You reached the daily estimate limit. Please try again tomorrow.', 'qoutify-estimator' ) ),
			'incomplete'    => array( 'error', __( 'Some required details are missing. Please complete the details step.', 'qoutify-estimator' ) ),
			'docs_ok'       => array( 'success', __( 'Files uploaded.', 'qoutify-estimator' ) ),
			'doc_removed'   => array( 'success', __( 'File removed.', 'qoutify-estimator' ) ),
			'doc_big'       => array( 'error', __( 'A file is too large, or the total size is over the limit.', 'qoutify-estimator' ) ),
			'doc_many'      => array( 'error', __( 'You reached the maximum number of files.', 'qoutify-estimator' ) ),
			'doc_type'      => array( 'error', __( 'Only PDF, JPG, PNG and TXT files are allowed.', 'qoutify-estimator' ) ),
			'doc_fail'      => array( 'error', __( 'A file could not be uploaded. Please try again.', 'qoutify-estimator' ) ),
			'deleted'       => array( 'success', __( 'Estimate deleted.', 'qoutify-estimator' ) ),
			'busy'          => array( 'error', __( 'This estimate is still being processed.', 'qoutify-estimator' ) ),
			'review_sent'   => array( 'success', __( 'Thank you! Your review was sent and is waiting for approval.', 'qoutify-estimator' ) ),
			'review_exists' => array( 'error', __( 'You already reviewed this estimate.', 'qoutify-estimator' ) ),
			'review_bad'    => array( 'error', __( 'Please choose a rating and write between 10 and 1000 characters.', 'qoutify-estimator' ) ),
			'review_deleted' => array( 'success', __( 'Your review was deleted.', 'qoutify-estimator' ) ),
			'bad_amount'    => array( 'error', __( 'Enter a whole number of credits within the allowed range.', 'qoutify-estimator' ) ),
			'buy_fail'      => array( 'error', __( 'We could not start the checkout. Please try again or contact support.', 'qoutify-estimator' ) ),
			'profile_ok'    => array( 'success', __( 'Your profile was saved.', 'qoutify-estimator' ) ),
			'profile_bad'   => array( 'error', __( 'Please enter your name (2 to 60 characters) and a valid phone number.', 'qoutify-estimator' ) ),
		)
	);
}

function qtf_view_flash() {
	$code = isset( $_GET['qtf_msg'] ) ? sanitize_key( wp_unslash( $_GET['qtf_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$all  = qtf_messages();
	if ( isset( $all[ $code ] ) ) {
		echo '<div class="qtf-notice qtf-notice--' . esc_attr( $all[ $code ][0] ) . '" role="status">' . esc_html( $all[ $code ][1] ) . '</div>';
	}
}

function qtf_title( $text ) {
	qtf_page_header( $text );
}

function qtf_money( $value ) {
	return '$' . number_format_i18n( (float) $value, 0 );
}

function qtf_status_badge( $status ) {
	$labels = array(
		'draft'      => __( 'Draft', 'qoutify-estimator' ),
		'queued'     => __( 'Queued', 'qoutify-estimator' ),
		'processing' => __( 'Processing', 'qoutify-estimator' ),
		'completed'  => __( 'Completed', 'qoutify-estimator' ),
		'failed'     => __( 'Failed', 'qoutify-estimator' ),
	);
	$label = isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	return '<span class="qtf-badge qtf-badge--' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
}

function qtf_error_text( $code ) {
	if ( in_array( $code, array( 'http_429', 'http_503', 'http_500' ), true ) ) {
		return __( 'The AI service is busy right now. Please try again in a few minutes.', 'qoutify-estimator' );
	}
	if ( in_array( $code, array( 'no_key', 'http_401', 'http_403', 'http_404', 'http_400' ), true ) ) {
		return __( 'The estimator is not set up correctly yet. Please contact support.', 'qoutify-estimator' );
	}
	return __( 'We could not finish this estimate. Please try again.', 'qoutify-estimator' );
}

function qtf_type_label( $row ) {
	$types = qtf_project_types();
	$type  = isset( $types[ $row->project_type ] ) ? $types[ $row->project_type ] : $row->project_type;
	$path  = ( 'detailed' === $row->path ) ? __( 'Detailed', 'qoutify-estimator' ) : __( 'Quick', 'qoutify-estimator' );
	return $type . ' - ' . $path;
}

function qtf_post_form_start( $action, $uuid = '', $extra = '' ) {
	echo '<form method="post"' . $extra . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
	wp_nonce_field( 'qtf_' . $action );
	echo '<input type="hidden" name="qtf_action" value="' . esc_attr( $action ) . '">';
	if ( $uuid ) {
		echo '<input type="hidden" name="id" value="' . esc_attr( $uuid ) . '">';
	}
}

function qtf_delete_button( $uuid ) {
	qtf_post_form_start( 'estimate_delete', $uuid, ' class="qtf-inline" data-qtf-confirm="' . esc_attr__( 'Delete this estimate? This cannot be undone.', 'qoutify-estimator' ) . '"' );
	echo '<button type="submit" class="qtf-link-danger">' . qtf_icon( 'trash', 16 ) . esc_html__( 'Delete', 'qoutify-estimator' ) . '</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput
}

function qtf_estimates_table( $rows, $with_delete = false ) {
	echo '<div class="qtf-table-wrap"><table class="qtf-table"><thead><tr>';
	foreach ( array( __( 'Date', 'qoutify-estimator' ), __( 'Property', 'qoutify-estimator' ), __( 'Type', 'qoutify-estimator' ), __( 'Status', 'qoutify-estimator' ), __( 'Estimate', 'qoutify-estimator' ), '' ) as $head ) {
		echo '<th>' . esc_html( $head ) . '</th>';
	}
	echo '</tr></thead><tbody>';
	foreach ( $rows as $row ) {
		$open = ( 'draft' === $row->status ) ? qtf_wizard_url( $row->uuid, 'details' ) : qtf_estimate_url( $row->uuid );
		$cta  = ( 'draft' === $row->status ) ? __( 'Continue', 'qoutify-estimator' ) : __( 'Open', 'qoutify-estimator' );
		echo '<tr>';
		echo '<td>' . esc_html( get_date_from_gmt( $row->created_at, 'M j, Y' ) ) . '</td>';
		echo '<td>' . esc_html( qtf_estimate_title( $row ) ) . '</td>';
		echo '<td>' . esc_html( qtf_type_label( $row ) ) . '</td>';
		echo '<td>' . qtf_status_badge( $row->status ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<td>' . ( 'completed' === $row->status ? esc_html( qtf_money( $row->total_low ) . ' - ' . qtf_money( $row->total_high ) ) : '-' ) . '</td>';
		echo '<td class="qtf-actions"><a href="' . esc_url( $open ) . '">' . esc_html( $cta ) . '</a>';
		if ( $with_delete ) {
			qtf_delete_button( $row->uuid );
		}
		echo '</td></tr>';
	}
	echo '</tbody></table></div>';
}

function qtf_locked_box() {
	echo '<div class="qtf-card qtf-locked">';
	echo '<p><span class="qtf-btn qtf-btn--locked" aria-disabled="true">' . qtf_lock_icon() . esc_html__( 'New Estimate', 'qoutify-estimator' ) . '</span></p>'; // phpcs:ignore WordPress.Security.EscapeOutput
	/* translators: %s: credits */
	echo '<p>' . esc_html( sprintf( __( 'You need at least %s to start an estimate. Upgrade your plan to continue.', 'qoutify-estimator' ), qtf_credits_label( qtf_min_cost() ) ) ) . '</p>';
	echo '<a class="qtf-btn qtf-btn--primary" href="' . esc_url( qtf_dashboard_url( 'credits' ) ) . '">' . esc_html__( 'Upgrade plan', 'qoutify-estimator' ) . '</a>';
	echo '</div>';
}

function qtf_render_view( $view ) {
	qtf_view_flash();
	switch ( $view ) {
		case 'new':
			qtf_view_new_estimate();
			break;
		case 'estimate':
			qtf_view_estimate();
			break;
		case 'list':
			qtf_view_my_estimates();
			break;
		case 'credits':
			qtf_view_credits();
			break;
		case 'reviews':
			qtf_view_reviews();
			break;
		case 'settings':
			qtf_view_settings();
			break;
		default:
			qtf_view_overview();
	}
}

function qtf_view_overview() {
	$is_team = qtf_user_is_staff();
	$can     = qtf_can_start();
	$user_id = get_current_user_id();

	$action = $can
		? '<a class="qtf-btn qtf-btn--primary" href="' . esc_url( qtf_dashboard_url( 'new-estimate' ) ) . '">' . qtf_icon( 'plus-circle', 18 ) . esc_html__( 'New Estimate', 'qoutify-estimator' ) . qtf_icon( 'arrow-right', 18 ) . '</a>'
		: '<span class="qtf-btn qtf-btn--locked" aria-disabled="true">' . qtf_lock_icon() . esc_html__( 'New Estimate', 'qoutify-estimator' ) . '</span>';

	echo '<header class="qtf-pagehead"><div class="qtf-pagehead__text">';
	/* translators: %s: first name */
	echo '<h1 class="qtf-title">' . sprintf( esc_html__( 'Welcome back, %s', 'qoutify-estimator' ), esc_html( qtf_user_first_name() ) ) . ' <span aria-hidden="true">&#128075;</span></h1>';
	echo '<p class="qtf-muted">' . esc_html__( 'Manage your property estimates and credits in one place.', 'qoutify-estimator' ) . '</p></div>';
	echo '<div class="qtf-pagehead__action">' . $action . '</div></header>'; // phpcs:ignore WordPress.Security.EscapeOutput

	echo '<div class="qtf-stats">';
	qtf_stat_card( 'file', 'blue', __( 'Total estimates', 'qoutify-estimator' ), qtf_estimate_count( $user_id ), qtf_dashboard_url( 'my-estimates' ) );
	if ( ! $is_team ) {
		qtf_stat_card( 'coins', 'gold', __( 'Available credits', 'qoutify-estimator' ), qtf_get_credits( $user_id ), qtf_dashboard_url( 'credits' ) );
	}
	qtf_stat_card( 'check-circle', 'green', __( 'Completed estimates', 'qoutify-estimator' ), qtf_estimate_completed_count( $user_id ), qtf_dashboard_url( 'my-estimates' ) );
	echo '</div>';

	echo '<div class="qtf-card qtf-cta"><div class="qtf-cta__art">' . qtf_illustration() . '</div><div class="qtf-cta__body">'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<h2 class="qtf-h2">' . esc_html__( 'Ready to estimate your next project?', 'qoutify-estimator' ) . '</h2>';
	if ( $can ) {
		echo '<p class="qtf-muted">' . esc_html__( 'Start a residential or commercial estimate. Add blueprints and documents for a more detailed result.', 'qoutify-estimator' ) . '</p>';
		echo '<a class="qtf-btn qtf-btn--primary" href="' . esc_url( qtf_dashboard_url( 'new-estimate' ) ) . '">' . qtf_icon( 'plus-circle', 18 ) . esc_html__( 'New Estimate', 'qoutify-estimator' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
	} else {
		echo '<p class="qtf-muted">' . esc_html( qtf_get_credits() > 0 ? __( 'Your credits are not enough for a new estimate. Choose a credit package to continue creating residential or commercial estimates.', 'qoutify-estimator' ) : __( "You're out of credits. Choose a credit package to continue creating residential or commercial estimates.", 'qoutify-estimator' ) ) . '</p>';
		echo '<div class="qtf-actions-row"><a class="qtf-btn qtf-btn--primary" href="' . esc_url( qtf_dashboard_url( 'credits' ) ) . '">' . qtf_icon( 'arrow-right', 18 ) . esc_html__( 'Buy credits', 'qoutify-estimator' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<a class="qtf-btn qtf-btn--text" href="' . esc_url( qtf_dashboard_url( 'credits' ) . '#qtf-packages' ) . '">' . esc_html__( 'View credit packages', 'qoutify-estimator' ) . qtf_icon( 'arrow-right', 16 ) . '</a></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div></div>';

	echo '<div class="qtf-card"><h2 class="qtf-h2">' . esc_html__( 'Recent estimates', 'qoutify-estimator' ) . '</h2>';
	$recent = qtf_estimate_list( $user_id, 1, 5 );
	if ( $recent ) {
		qtf_estimates_table( $recent );
		echo '<p><a href="' . esc_url( qtf_dashboard_url( 'my-estimates' ) ) . '">' . esc_html__( 'View all estimates', 'qoutify-estimator' ) . '</a></p>';
	} else {
		echo '<div class="qtf-empty"><span class="qtf-empty__icon">' . qtf_icon( 'file', 26 ) . '</span><p>' . esc_html__( 'Your estimates will appear here once you create one.', 'qoutify-estimator' ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div>';
	echo qtf_support_line(); // phpcs:ignore WordPress.Security.EscapeOutput
}

function qtf_wizard_steps( $current ) {
	$steps = array(
		'type'      => __( 'Type', 'qoutify-estimator' ),
		'details'   => __( 'Details', 'qoutify-estimator' ),
		'documents' => __( 'Documents', 'qoutify-estimator' ),
		'review'    => __( 'Review', 'qoutify-estimator' ),
	);
	$keys   = array_keys( $steps );
	$index  = array_search( $current, $keys, true );
	echo '<ol class="qtf-steps">';
	foreach ( $steps as $key => $label ) {
		$pos   = array_search( $key, $keys, true );
		$class = ( $pos < $index ) ? 'is-done' : ( ( $pos === $index ) ? 'is-current' : '' );
		echo '<li class="' . esc_attr( $class ) . '"><span>' . ( $pos + 1 ) . '</span> ' . esc_html( $label ) . '</li>';
	}
	echo '</ol>';
}

function qtf_view_new_estimate() {
	qtf_page_header( __( 'New Estimate', 'qoutify-estimator' ), __( 'Answer a few questions and get a preliminary cost range.', 'qoutify-estimator' ), 'plus-circle' );

	$uuid     = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$estimate = $uuid ? qtf_estimate_by_uuid( $uuid, get_current_user_id() ) : null;

	if ( ! $estimate ) {
		if ( ! qtf_can_start() ) {
			qtf_locked_box();
			return;
		}
		qtf_step_type();
		return;
	}
	if ( 'draft' !== $estimate->status ) {
		echo '<div class="qtf-card"><p>' . esc_html__( 'This estimate was already submitted.', 'qoutify-estimator' ) . '</p><a class="qtf-btn qtf-btn--primary" href="' . esc_url( qtf_estimate_url( $estimate->uuid ) ) . '">' . esc_html__( 'Open estimate', 'qoutify-estimator' ) . '</a></div>';
		return;
	}

	$step = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : 'details'; // phpcs:ignore WordPress.Security.NonceVerification
	if ( qtf_wizard_state( 'errors' ) ) {
		$step = 'details';
	}
	if ( 'documents' === $step ) {
		qtf_step_documents( $estimate );
	} elseif ( 'review' === $step ) {
		qtf_step_review( $estimate );
	} else {
		qtf_step_details( $estimate );
	}
}

function qtf_step_type() {
	qtf_wizard_steps( 'type' );
	$team = qtf_user_is_staff();
	echo '<div class="qtf-card"><h2 class="qtf-h2">' . esc_html__( 'What type of project is it?', 'qoutify-estimator' ) . '</h2>';
	qtf_post_form_start( 'wizard_type' );
	echo '<div class="qtf-choices">';
	$descriptions = array(
		'residential' => __( 'Homes, condos and townhouses', 'qoutify-estimator' ),
		'commercial'  => __( 'Offices, retail, restaurants, warehouses and more', 'qoutify-estimator' ),
	);
	$locked_any = false;
	foreach ( qtf_project_types() as $key => $label ) {
		$quick    = qtf_credit_cost( $key, 'quick' );
		$detailed = qtf_credit_cost( $key, 'detailed' );
		$locked   = ! $team && qtf_get_credits() < $quick;
		$locked_any = $locked_any || $locked;
		echo '<label class="qtf-choice' . ( $locked ? ' is-locked' : '' ) . '"><input type="radio" name="project_type" value="' . esc_attr( $key ) . '" required' . ( $locked ? ' disabled' : '' ) . '>';
		echo '<span class="qtf-choice__title">' . esc_html( $label ) . ( $locked ? ' ' . qtf_lock_icon() : '' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<span class="qtf-muted">' . esc_html( $descriptions[ $key ] ) . '</span>';
		if ( $team ) {
			echo '<span class="qtf-choice__cost">' . esc_html__( 'Free for team accounts', 'qoutify-estimator' ) . '</span>';
		} else {
			/* translators: 1: quick credits, 2: quick price, 3: detailed credits, 4: detailed price */
			echo '<span class="qtf-choice__cost">' . esc_html( sprintf( __( 'Quick: %1$s (%2$s) | Detailed: %3$s (%4$s)', 'qoutify-estimator' ), qtf_credits_label( $quick ), qtf_price_text( qtf_price_for_credits( $quick ) ), qtf_credits_label( $detailed ), qtf_price_text( qtf_price_for_credits( $detailed ) ) ) ) . '</span>';
		}
		if ( $locked ) {
			echo '<span class="qtf-choice__lock">' . esc_html( qtf_need_credits_text( $key, 'quick' ) ) . '</span>';
		}
		echo '</label>';
	}
	echo '</div>';
	echo '<button type="submit" class="qtf-btn qtf-btn--primary">' . esc_html__( 'Continue', 'qoutify-estimator' ) . '</button>';
	if ( $locked_any ) {
		echo ' <a class="qtf-btn qtf-btn--secondary" href="' . esc_url( qtf_dashboard_url( 'credits' ) ) . '">' . esc_html__( 'Buy credits', 'qoutify-estimator' ) . '</a>';
	}
	echo '</form></div>';
}

function qtf_step_details( $estimate ) {
	qtf_wizard_steps( 'details' );
	$types  = qtf_project_types();
	$values = qtf_wizard_state( 'values' );
	$values = is_array( $values ) ? $values : qtf_estimate_details( $estimate );
	$errors = qtf_wizard_state( 'errors' );
	$errors = is_array( $errors ) ? $errors : array();

	echo '<div class="qtf-card"><h2 class="qtf-h2">' . esc_html( $types[ $estimate->project_type ] ) . ' ' . esc_html__( 'project details', 'qoutify-estimator' ) . '</h2>';
	if ( $errors ) {
		echo '<div class="qtf-notice qtf-notice--error">' . esc_html__( 'Please fix the highlighted fields.', 'qoutify-estimator' ) . '</div>';
	}
	qtf_post_form_start( 'wizard_details', $estimate->uuid );

	$fields = qtf_fields( $estimate->project_type );
	foreach ( qtf_field_groups() as $group => $group_label ) {
		echo '<h3 class="qtf-h3">' . esc_html( $group_label ) . '</h3><div class="qtf-grid">';
		foreach ( $fields as $field ) {
			if ( $field['group'] !== $group ) {
				continue;
			}
			$value = isset( $values[ $field['key'] ] ) ? $values[ $field['key'] ] : '';
			$wide  = in_array( $field['type'], array( 'checkboxes', 'textarea' ), true );
			echo '<div class="' . ( $wide ? 'qtf-grid__wide' : '' ) . '">' . qtf_render_field( $field, $value, isset( $errors[ $field['key'] ] ) ? $errors[ $field['key'] ] : '' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div>';
	}

	echo '<p class="qtf-actions-row"><button type="submit" class="qtf-btn qtf-btn--primary">' . esc_html__( 'Save and continue', 'qoutify-estimator' ) . '</button></p>';
	echo '</form></div>';
}

function qtf_step_documents( $estimate ) {
	qtf_wizard_steps( 'documents' );
	$docs     = qtf_docs_list( $estimate->id );
	$cats     = qtf_doc_categories();
	$max_f    = (int) qtf_setting( 'max_files' );
	$max_m    = (int) qtf_setting( 'max_file_mb' );
	$team     = qtf_user_is_staff();
	$type     = $estimate->project_type;
	$types    = qtf_project_types();
	$quick    = qtf_credit_cost( $type, 'quick' );
	$detailed = qtf_credit_cost( $type, 'detailed' );
	// Documents make it a Detailed estimate. Without enough credits the upload is gated.
	$blocked  = ! $team && ! $docs && qtf_get_credits() < $detailed;

	echo '<div class="qtf-card"><h2 class="qtf-h2">' . esc_html__( 'Blueprints and documents (optional)', 'qoutify-estimator' ) . '</h2>';
	echo '<p>' . esc_html__( 'Skip this step for a Quick Estimate. Upload blueprints or a scope of work for a Detailed Estimate with a narrower range.', 'qoutify-estimator' ) . '</p>';

	if ( $team ) {
		echo '<div class="qtf-notice qtf-notice--info">' . esc_html__( 'Team account: Quick and Detailed estimates are free.', 'qoutify-estimator' ) . '</div>';
	} else {
		/* translators: 1: type, 2: quick credits, 3: quick price, 4: detailed credits, 5: detailed price */
		echo '<div class="qtf-notice qtf-notice--info">' . esc_html( sprintf( __( '%1$s Quick Estimate (no documents): %2$s, %3$s. %1$s Detailed Estimate (with documents): %4$s, %5$s.', 'qoutify-estimator' ), $types[ $type ], qtf_credits_label( $quick ), qtf_price_text( qtf_price_for_credits( $quick ) ), qtf_credits_label( $detailed ), qtf_price_text( qtf_price_for_credits( $detailed ) ) ) ) . '</div>';
	}
	if ( $blocked ) {
		echo '<div class="qtf-notice qtf-notice--error" role="alert"><strong>' . esc_html( qtf_need_credits_text( $type, 'detailed' ) ) . '</strong> <a href="' . esc_url( qtf_dashboard_url( 'credits' ) ) . '">' . esc_html__( 'Buy credits', 'qoutify-estimator' ) . '</a></div>';
	}
	/* translators: 1: number of files, 2: size in MB */
	echo '<p class="qtf-muted qtf-small">' . esc_html( sprintf( __( 'PDF, JPG, PNG or TXT. Up to %1$d files, %2$d MB each. The AI reads JPG and PNG pages best; a written scope of work is best as a TXT file or typed in the description.', 'qoutify-estimator' ), $max_f, $max_m ) ) . '</p>';

	if ( $docs ) {
		echo '<div class="qtf-table-wrap"><table class="qtf-table"><thead><tr><th>' . esc_html__( 'File', 'qoutify-estimator' ) . '</th><th>' . esc_html__( 'Type', 'qoutify-estimator' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $docs as $doc ) {
			echo '<tr><td><a href="' . esc_url( qtf_doc_url( $doc ) ) . '" target="_blank" rel="noopener">' . esc_html( $doc->original_name ) . '</a></td>';
			echo '<td>' . esc_html( isset( $cats[ $doc->category ] ) ? $cats[ $doc->category ] : $doc->category ) . '</td><td class="qtf-actions">';
			qtf_post_form_start( 'doc_delete', $estimate->uuid, ' class="qtf-inline"' );
			echo '<input type="hidden" name="doc_id" value="' . esc_attr( $doc->id ) . '"><button type="submit" class="qtf-link-danger">' . qtf_icon( 'trash', 16 ) . esc_html__( 'Remove', 'qoutify-estimator' ) . '</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	qtf_post_form_start( 'wizard_docs', $estimate->uuid, ' enctype="multipart/form-data"' );
	$accept = '.pdf,.jpg,.jpeg,.png,.txt';
	$inputs = array(
		'qtf_blueprint' => array( __( 'Blueprints', 'qoutify-estimator' ), __( 'Upload blueprints, floor plans or architectural drawings.', 'qoutify-estimator' ) ),
		'qtf_scope'     => array( __( 'Scope of Work', 'qoutify-estimator' ), __( 'Upload your scope of work or a written list of the work to be done.', 'qoutify-estimator' ) ),
		'qtf_photo'     => array( __( 'Photos / other documents', 'qoutify-estimator' ), __( 'Upload photos of the property or other supporting documents.', 'qoutify-estimator' ) ),
	);
	foreach ( $inputs as $name => $info ) {
		echo '<div class="qtf-field"><label for="' . esc_attr( $name ) . '">' . esc_html( $info[0] ) . '</label>';
		echo '<input type="file" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '[]" accept="' . esc_attr( $accept ) . '" multiple aria-describedby="' . esc_attr( $name ) . '_help"' . ( $blocked ? ' disabled' : '' ) . '>';
		echo '<span class="qtf-help" id="' . esc_attr( $name ) . '_help">' . esc_html( $info[1] ) . '</span></div>';
	}
	echo '<p class="qtf-actions-row">';
	echo '<button type="submit" name="qtf_next" value="upload" class="qtf-btn qtf-btn--secondary"' . ( $blocked ? ' disabled' : '' ) . '>' . esc_html__( 'Upload files', 'qoutify-estimator' ) . '</button> ';
	echo '<button type="submit" name="qtf_next" value="continue" class="qtf-btn qtf-btn--primary">' . esc_html( $docs ? __( 'Continue', 'qoutify-estimator' ) : __( 'Skip documents', 'qoutify-estimator' ) ) . '</button> ';
	echo '<a class="qtf-btn qtf-btn--text" href="' . esc_url( qtf_wizard_url( $estimate->uuid, 'details' ) ) . '">' . esc_html__( 'Back', 'qoutify-estimator' ) . '</a>';
	echo '</p></form></div>';
}

function qtf_step_review( $estimate ) {
	qtf_wizard_steps( 'review' );
	$details = qtf_estimate_details( $estimate );
	$docs    = qtf_docs_list( $estimate->id );
	$path    = $docs ? 'detailed' : 'quick';
	$team    = qtf_user_is_staff();
	$cost    = $team ? 0 : qtf_credit_cost( $estimate->project_type, $path );
	$balance = qtf_get_credits();
	$enough  = $team || $balance >= $cost;

	echo '<div class="qtf-card"><h2 class="qtf-h2">' . esc_html__( 'Review your estimate', 'qoutify-estimator' ) . '</h2>';
	echo '<div class="qtf-table-wrap"><table class="qtf-table qtf-table--plain"><tbody>';
	echo '<tr><th>' . esc_html__( 'Estimate type', 'qoutify-estimator' ) . '</th><td>' . esc_html( qtf_type_label( (object) array( 'project_type' => $estimate->project_type, 'path' => $path ) ) ) . '</td></tr>';
	foreach ( qtf_fields( $estimate->project_type ) as $field ) {
		$value = isset( $details[ $field['key'] ] ) ? $details[ $field['key'] ] : '';
		echo '<tr><th>' . esc_html( $field['label'] ) . '</th><td>' . nl2br( esc_html( qtf_field_display( $field, $value ) ) ) . '</td></tr>';
	}
	echo '<tr><th>' . esc_html__( 'Documents', 'qoutify-estimator' ) . '</th><td>' . esc_html( $docs ? sprintf( _n( '%d file', '%d files', count( $docs ), 'qoutify-estimator' ), count( $docs ) ) : __( 'None (Quick Estimate)', 'qoutify-estimator' ) ) . '</td></tr>';
	echo '</tbody></table></div>';

	if ( $team ) {
		echo '<div class="qtf-notice qtf-notice--info">' . esc_html__( 'Team account: this estimate is free.', 'qoutify-estimator' ) . '</div>';
	} else {
		/* translators: 1: credits needed, 2: price, 3: balance */
		echo '<div class="qtf-notice qtf-notice--info"><strong>' . esc_html( sprintf( __( 'This estimate uses %1$s (%2$s). Your balance: %3$s.', 'qoutify-estimator' ), qtf_credits_label( $cost ), qtf_price_text( qtf_price_for_credits( $cost ) ), qtf_credits_label( $balance ) ) ) . '</strong></div>';
		if ( ! $enough ) {
			echo '<div class="qtf-notice qtf-notice--error" role="alert">' . esc_html( qtf_need_credits_text( $estimate->project_type, $path ) ) . '</div>';
		}
	}

	qtf_post_form_start( 'wizard_submit', $estimate->uuid );
	if ( $enough ) {
		echo '<label class="qtf-check"><input type="checkbox" name="qtf_ack" value="1" required> ' . esc_html__( 'I understand this is a preliminary estimate, not a quote or contract price.', 'qoutify-estimator' ) . '</label>';
		echo '<p class="qtf-actions-row"><button type="submit" class="qtf-btn qtf-btn--primary">' . esc_html__( 'Generate Estimate', 'qoutify-estimator' ) . '</button> ';
		echo '<a class="qtf-btn qtf-btn--text" href="' . esc_url( qtf_wizard_url( $estimate->uuid, 'documents' ) ) . '">' . esc_html__( 'Back', 'qoutify-estimator' ) . '</a></p>';
	} else {
		echo '<p class="qtf-actions-row"><span class="qtf-btn qtf-btn--locked" aria-disabled="true">' . qtf_lock_icon() . esc_html__( 'Generate Estimate', 'qoutify-estimator' ) . '</span> '; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<a class="qtf-btn qtf-btn--primary" href="' . esc_url( qtf_dashboard_url( 'credits' ) ) . '">' . esc_html__( 'Buy credits', 'qoutify-estimator' ) . '</a> ';
		echo '<a class="qtf-btn qtf-btn--text" href="' . esc_url( qtf_wizard_url( $estimate->uuid, 'documents' ) ) . '">' . esc_html__( 'Back', 'qoutify-estimator' ) . '</a></p>';
	}
	echo '</form></div>';
}

function qtf_view_estimate() {
	qtf_page_header( __( 'Estimate', 'qoutify-estimator' ), __( 'Your estimate result and breakdown.', 'qoutify-estimator' ), 'file' );

	$uuid = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( $uuid ) {
		$estimate = qtf_estimate_by_uuid( $uuid, get_current_user_id() );
	} else {
		$estimate = qtf_latest_estimate( get_current_user_id() );
	}
	if ( ! $estimate ) {
		echo '<div class="qtf-card"><div class="qtf-empty"><span class="qtf-empty__icon">' . qtf_icon( 'file', 26 ) . '</span><p>' . esc_html__( 'No estimate to show yet.', 'qoutify-estimator' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( qtf_can_start() ) {
			echo '<a class="qtf-btn qtf-btn--primary" href="' . esc_url( qtf_dashboard_url( 'new-estimate' ) ) . '">' . esc_html__( 'Start a new estimate', 'qoutify-estimator' ) . '</a>';
		}
		echo '</div></div>';
		return;
	}

	switch ( $estimate->status ) {
		case 'draft':
			echo '<div class="qtf-card"><p>' . esc_html__( 'This estimate is a draft.', 'qoutify-estimator' ) . '</p><a class="qtf-btn qtf-btn--primary" href="' . esc_url( qtf_wizard_url( $estimate->uuid, 'details' ) ) . '">' . esc_html__( 'Continue', 'qoutify-estimator' ) . '</a></div>';
			break;
		case 'queued':
		case 'processing':
			echo '<div class="qtf-card qtf-processing" id="qtf-status" data-uuid="' . esc_attr( $estimate->uuid ) . '">';
			echo '<div class="qtf-spinner" aria-hidden="true"></div>';
			echo '<h2 class="qtf-h2">' . esc_html__( 'Analyzing your project', 'qoutify-estimator' ) . '</h2>';
			echo '<p>' . esc_html__( 'This usually takes under a minute. You can leave this page; we will email you when it is ready.', 'qoutify-estimator' ) . '</p>';
			echo '<noscript><p>' . esc_html__( 'Refresh this page in a minute to see the result.', 'qoutify-estimator' ) . '</p></noscript></div>';
			break;
		case 'failed':
			echo '<div class="qtf-card"><h2 class="qtf-h2">' . esc_html__( 'We could not finish this estimate', 'qoutify-estimator' ) . '</h2>';
			echo '<p>' . esc_html( qtf_error_text( $estimate->error_message ) ) . '</p>';
			if ( (int) $estimate->credits_charged > 0 ) {
				echo '<p><strong>' . esc_html( sprintf( _n( 'Your %d credit was returned.', 'Your %d credits were returned.', (int) $estimate->credits_charged, 'qoutify-estimator' ), (int) $estimate->credits_charged ) ) . '</strong></p>';
			}
			echo '<div class="qtf-actions-row"><a class="qtf-btn qtf-btn--primary" href="' . esc_url( qtf_dashboard_url( 'new-estimate' ) ) . '">' . esc_html__( 'Start again', 'qoutify-estimator' ) . '</a> ';
			qtf_delete_button( $estimate->uuid );
			echo '</div>' . qtf_support_line() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
			break;
		default:
			qtf_render_result( $estimate );
			if ( qtf_should_prompt_review( $estimate ) ) {
				qtf_review_modal( $estimate );
			}
	}
}

function qtf_render_list( $items ) {
	echo '<ul class="qtf-list">';
	foreach ( $items as $item ) {
		echo '<li>' . esc_html( $item ) . '</li>';
	}
	echo '</ul>';
}

function qtf_render_result( $estimate ) {
	$r       = qtf_estimate_result( $estimate );
	$details = qtf_estimate_details( $estimate );
	if ( ! $r ) {
		echo '<div class="qtf-card"><p>' . esc_html__( 'The result is not available.', 'qoutify-estimator' ) . '</p></div>';
		return;
	}

	if ( ! empty( $r['pricing_sample'] ) ) {
		echo '<div class="qtf-notice qtf-notice--info">' . esc_html__( 'Test mode: this estimate uses sample pricing data and does not represent real prices.', 'qoutify-estimator' ) . '</div>';
	}

	echo '<div class="qtf-card qtf-result">';
	echo '<p class="qtf-muted">' . esc_html( qtf_estimate_title( $estimate ) ) . ' | ' . esc_html( qtf_type_label( $estimate ) ) . ' | ' . esc_html( get_date_from_gmt( $estimate->completed_at ? $estimate->completed_at : $estimate->created_at, 'M j, Y' ) ) . '</p>';
	echo '<p class="qtf-stat__label">' . esc_html__( 'Estimated project cost', 'qoutify-estimator' ) . '</p>';
	echo '<p class="qtf-range">' . esc_html( qtf_money( $r['low'] ) . ' - ' . qtf_money( $r['high'] ) ) . '</p>';
	$conf = (int) $r['confidence'];
	echo '<p class="qtf-stat__label">' . esc_html__( 'Confidence', 'qoutify-estimator' ) . ': <strong>' . esc_html( $conf ) . '%</strong></p>';
	echo '<div class="qtf-meter" role="img" aria-label="' . esc_attr( $conf ) . '%"><span style="width:' . esc_attr( $conf ) . '%"></span></div>';
	if ( ! empty( $r['confidence_notes'] ) ) {
		qtf_render_list( $r['confidence_notes'] );
	}
	if ( ! empty( $r['summary'] ) ) {
		echo '<p>' . esc_html( $r['summary'] ) . '</p>';
	}
	echo '</div>';

	echo '<div class="qtf-card"><h2 class="qtf-h2">' . esc_html__( 'Estimate breakdown', 'qoutify-estimator' ) . '</h2><div class="qtf-table-wrap"><table class="qtf-table"><thead><tr>';
	foreach ( array( __( 'Category', 'qoutify-estimator' ), __( 'Scope', 'qoutify-estimator' ), __( 'Material', 'qoutify-estimator' ), __( 'Labor', 'qoutify-estimator' ), __( 'Equipment', 'qoutify-estimator' ), __( 'Total', 'qoutify-estimator' ) ) as $head ) {
		echo '<th>' . esc_html( $head ) . '</th>';
	}
	echo '</tr></thead><tbody>';
	foreach ( $r['lines'] as $line ) {
		echo '<tr><td>' . esc_html( $line['label'] );
		if ( ! empty( $line['reason'] ) ) {
			echo '<br><span class="qtf-muted qtf-small">' . esc_html( $line['reason'] ) . '</span>';
		}
		echo '</td><td>' . esc_html( round( $line['scope'] * 100 ) ) . '%</td><td>' . esc_html( qtf_money( $line['material'] ) ) . '</td><td>' . esc_html( qtf_money( $line['labor'] ) ) . '</td><td>' . esc_html( qtf_money( $line['equipment'] ) ) . '</td><td>' . esc_html( qtf_money( $line['total'] ) ) . '</td></tr>';
	}
	$sums = array(
		__( 'Direct construction cost', 'qoutify-estimator' ) => $r['direct'],
		__( 'Overhead', 'qoutify-estimator' )                 => $r['overhead'],
		__( 'Contingency', 'qoutify-estimator' )              => $r['contingency'],
		__( 'Markup', 'qoutify-estimator' )                   => $r['markup'],
		__( 'Estimated total (midpoint)', 'qoutify-estimator' ) => $r['total'],
	);
	foreach ( $sums as $label => $value ) {
		echo '<tr class="qtf-table__sum"><td colspan="5">' . esc_html( $label ) . '</td><td>' . esc_html( qtf_money( $value ) ) . '</td></tr>';
	}
	echo '</tbody></table></div></div>';

	foreach ( array(
		'assumptions'  => __( 'Project assumptions', 'qoutify-estimator' ),
		'verify_items' => __( 'Items requiring verification', 'qoutify-estimator' ),
		'cost_drivers' => __( 'Cost drivers', 'qoutify-estimator' ),
	) as $key => $heading ) {
		if ( ! empty( $r[ $key ] ) ) {
			echo '<div class="qtf-card"><h2 class="qtf-h2">' . esc_html( $heading ) . '</h2>';
			qtf_render_list( $r[ $key ] );
			echo '</div>';
		}
	}

	$docs = qtf_docs_list( $estimate->id );
	if ( $docs ) {
		echo '<div class="qtf-card"><h2 class="qtf-h2">' . esc_html__( 'Documents used', 'qoutify-estimator' ) . '</h2><ul class="qtf-list">';
		foreach ( $docs as $doc ) {
			echo '<li><a href="' . esc_url( qtf_doc_url( $doc ) ) . '" target="_blank" rel="noopener">' . esc_html( $doc->original_name ) . '</a></li>';
		}
		echo '</ul></div>';
	}

	echo '<div class="qtf-card qtf-disclaimer"><h2 class="qtf-h2">' . esc_html__( 'Important disclaimer', 'qoutify-estimator' ) . '</h2><p class="qtf-small">' . esc_html( qtf_disclaimer_text() ) . '</p></div>';

	echo '<div class="qtf-actions-row qtf-noprint"><button type="button" class="qtf-btn qtf-btn--secondary" data-qtf-print>' . esc_html__( 'Download PDF / Print', 'qoutify-estimator' ) . '</button> ';
	echo '<a class="qtf-btn qtf-btn--text" href="' . esc_url( qtf_dashboard_url( 'my-estimates' ) ) . '">' . esc_html__( 'All estimates', 'qoutify-estimator' ) . '</a> ';
	qtf_delete_button( $estimate->uuid );
	echo '</div>';
}

/* --------------------------------------------------------------- my estimates */

function qtf_view_my_estimates() {
	qtf_title( __( 'My Estimates', 'qoutify-estimator' ) );
	$user_id = get_current_user_id();
	$page    = isset( $_GET['pg'] ) ? max( 1, absint( $_GET['pg'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
	$per     = 10;
	$total   = qtf_estimate_total_count( $user_id );
	$rows    = qtf_estimate_list( $user_id, $page, $per );

	if ( ! $rows ) {
		echo '<div class="qtf-card"><p>' . esc_html__( 'You have no estimates yet.', 'qoutify-estimator' ) . '</p>';
		if ( qtf_can_start() ) {
			echo '<a class="qtf-btn qtf-btn--primary" href="' . esc_url( qtf_dashboard_url( 'new-estimate' ) ) . '">+ ' . esc_html__( 'New Estimate', 'qoutify-estimator' ) . '</a>';
		}
		echo '</div>';
		return;
	}

	echo '<div class="qtf-card">';
	qtf_estimates_table( $rows, true );
	$pages = (int) ceil( $total / $per );
	if ( $pages > 1 ) {
		echo '<p class="qtf-pager">';
		for ( $i = 1; $i <= $pages; $i++ ) {
			echo '<a class="' . ( $i === $page ? 'is-current' : '' ) . '" href="' . esc_url( add_query_arg( 'pg', $i, qtf_dashboard_url( 'my-estimates' ) ) ) . '">' . esc_html( $i ) . '</a> ';
		}
		echo '</p>';
	}
	echo '</div>';
}

/* -------------------------------------------------------------------- credits */

function qtf_view_credits() {
	$user_id = get_current_user_id();
	qtf_page_header( __( 'Credits', 'qoutify-estimator' ), __( 'Buy credits and see what each estimate costs.', 'qoutify-estimator' ), 'coins' );

	echo qtf_order_notice(); // phpcs:ignore WordPress.Security.EscapeOutput

	echo '<div class="qtf-stats">';
	qtf_stat_card( 'coins', 'gold', __( 'Available credits', 'qoutify-estimator' ), qtf_get_credits( $user_id ), qtf_dashboard_url( 'credits' ) );
	echo '</div>';

	echo '<div class="qtf-card"><h2 class="qtf-h2">' . esc_html__( 'What is a credit?', 'qoutify-estimator' ) . '</h2>';
	/* translators: %s: price of one credit */
	echo '<p>' . esc_html( sprintf( __( 'A credit is what you spend to create an estimate. One credit costs %s. Each estimate uses a fixed number of credits, shown below.', 'qoutify-estimator' ), qtf_price_text( qtf_credit_price() ) ) ) . '</p></div>';

	echo '<div class="qtf-card"><h2 class="qtf-h2">' . esc_html__( 'What each estimate costs', 'qoutify-estimator' ) . '</h2>';
	qtf_estimate_cards();
	echo '<p class="qtf-muted qtf-small">' . esc_html__( 'These cards show the cost of one estimate. They are not prepaid bundles. A Detailed Estimate is any estimate where you upload one or more documents.', 'qoutify-estimator' ) . '</p></div>';

	echo '<div class="qtf-card"><h2 class="qtf-h2">' . esc_html__( 'Buy credits', 'qoutify-estimator' ) . '</h2>';
	echo '<h3 class="qtf-h3">' . esc_html__( 'Option 1: choose your own amount', 'qoutify-estimator' ) . '</h3>';
	$symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) : '$';
	$max    = max( 1, (int) qtf_setting( 'max_custom_credits' ) );
	qtf_post_form_start( 'buy_custom', '', ' class="qtf-buy-form" data-price="' . esc_attr( qtf_credit_price() ) . '" data-symbol="' . esc_attr( $symbol ) . '" data-decimals="' . (int) ( function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2 ) . '"' );
	echo '<div class="qtf-field"><label for="qtf_credits_amount">' . esc_html__( 'Number of credits', 'qoutify-estimator' ) . '</label>';
	echo '<input type="number" id="qtf_credits_amount" name="credits" min="1" max="' . esc_attr( $max ) . '" step="1" value="5" inputmode="numeric" placeholder="' . esc_attr__( 'Enter how many credits you want', 'qoutify-estimator' ) . '" required aria-describedby="qtf_credits_help">';
	/* translators: 1: maximum credits */
	echo '<span class="qtf-help" id="qtf_credits_help">' . esc_html( sprintf( __( 'Whole numbers from 1 to %d.', 'qoutify-estimator' ), $max ) ) . '</span></div>';
	/* translators: %s: price per credit */
	echo '<p class="qtf-buy-total">' . esc_html__( 'Total:', 'qoutify-estimator' ) . ' <strong data-qtf-total>' . esc_html( qtf_price_text( qtf_price_for_credits( 5 ) ) ) . '</strong> <span class="qtf-muted">' . esc_html( sprintf( __( '(%s per credit)', 'qoutify-estimator' ), qtf_price_text( qtf_credit_price() ) ) ) . '</span></p>';
	echo '<button type="submit" class="qtf-btn qtf-btn--primary">' . esc_html__( 'Continue to checkout', 'qoutify-estimator' ) . qtf_icon( 'arrow-right', 18 ) . '</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput

	echo '<h3 class="qtf-h3" id="qtf-packages">' . esc_html__( 'Option 2: ready-made packages', 'qoutify-estimator' ) . '</h3>';
	$packages = function_exists( 'wc_get_product' ) ? qtf_credit_packages() : array();
	if ( $packages ) {
		echo '<div class="qtf-packages">';
		foreach ( $packages as $package ) {
			$product  = $package['product'];
			$credits  = (int) $package['credits'];
			$price    = (float) $product->get_price();
			$standard = qtf_price_for_credits( $credits );
			$url      = add_query_arg(
				array(
					'add-to-cart' => $product->get_id(),
					'qtf_buy'     => 1,
				),
				wc_get_checkout_url()
			);
			echo '<div class="qtf-package"><strong class="qtf-package__credits">' . esc_html( qtf_credits_label( $credits ) ) . '</strong>';
			echo '<span class="qtf-package__name">' . esc_html( $product->get_name() ) . '</span>';
			echo '<span class="qtf-package__price">' . esc_html( qtf_price_text( $price ) ) . '</span>';
			echo '<span class="qtf-muted qtf-small">' . esc_html( sprintf( __( '%s per credit', 'qoutify-estimator' ), qtf_price_text( round( $price / max( 1, $credits ), 2 ) ) ) ) . '</span>';
			if ( $standard > $price ) {
				echo '<span class="qtf-package__save">' . esc_html( sprintf( __( 'Save %d%%', 'qoutify-estimator' ), round( ( 1 - $price / $standard ) * 100 ) ) ) . '</span>';
			}
			echo '<a class="qtf-btn qtf-btn--primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Buy', 'qoutify-estimator' ) . '</a></div>';
		}
		echo '</div>';
	} else {
		echo '<div class="qtf-empty"><span class="qtf-empty__icon">' . qtf_icon( 'coins', 26 ) . '</span><p>' . esc_html__( 'No credit packages are available yet. You can still choose your own amount above.', 'qoutify-estimator' ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div>';

	echo '<div class="qtf-card"><h2 class="qtf-h2">' . esc_html__( 'How buying works', 'qoutify-estimator' ) . '</h2><ol class="qtf-list">';
	foreach ( array(
		__( 'Choose your own amount or a package, then go to the secure checkout.', 'qoutify-estimator' ),
		__( 'Pay with the payment method shown at checkout. Your card details go to the payment provider, never to Qoutify.', 'qoutify-estimator' ),
		__( 'Credits are added after the payment is confirmed, usually within a minute. You get a confirmation email.', 'qoutify-estimator' ),
		__( 'If an estimate fails, its credits are returned to you automatically, once. If an order is refunded, its credits are removed.', 'qoutify-estimator' ),
	) as $step ) {
		echo '<li>' . esc_html( $step ) . '</li>';
	}
	echo '</ol>' . qtf_support_line() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput

	$history = qtf_credit_history( $user_id, 20 );
	echo '<div class="qtf-card"><h2 class="qtf-h2">' . esc_html__( 'Credit history', 'qoutify-estimator' ) . '</h2>';
	if ( $history ) {
		$labels = array(
			'purchase' => __( 'Purchase', 'qoutify-estimator' ),
			'spend'    => __( 'Estimate', 'qoutify-estimator' ),
			'refund'   => __( 'Refund', 'qoutify-estimator' ),
			'revoke'   => __( 'Order refunded', 'qoutify-estimator' ),
			'admin'    => __( 'Adjustment', 'qoutify-estimator' ),
		);
		echo '<div class="qtf-table-wrap"><table class="qtf-table"><thead><tr><th>' . esc_html__( 'Date', 'qoutify-estimator' ) . '</th><th>' . esc_html__( 'Activity', 'qoutify-estimator' ) . '</th><th>' . esc_html__( 'Credits', 'qoutify-estimator' ) . '</th></tr></thead><tbody>';
		foreach ( $history as $row ) {
			$delta = (int) $row->delta;
			echo '<tr><td>' . esc_html( get_date_from_gmt( $row->created_at, 'M j, Y' ) ) . '</td><td>' . esc_html( isset( $labels[ $row->reason ] ) ? $labels[ $row->reason ] : $row->reason ) . '</td><td>' . esc_html( ( $delta > 0 ? '+' : '' ) . $delta ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	} else {
		echo '<div class="qtf-empty"><span class="qtf-empty__icon">' . qtf_icon( 'list', 26 ) . '</span><p>' . esc_html__( 'No credit activity yet.', 'qoutify-estimator' ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div>';
}

function qtf_settings_messages() {
	return array(
		'avatar_ok'      => array( 'success', __( 'Profile image updated.', 'qoutify-estimator' ) ),
		'avatar_removed' => array( 'success', __( 'Profile image removed.', 'qoutify-estimator' ) ),
		'avatar_none'    => array( 'error', __( 'Choose an image first.', 'qoutify-estimator' ) ),
		'avatar_big'     => array( 'error', __( 'The image is too large. The maximum size is 2 MB.', 'qoutify-estimator' ) ),
		'avatar_bad'     => array( 'error', __( 'Use a JPG, PNG or WebP image.', 'qoutify-estimator' ) ),
		'pw_ok'          => array( 'success', __( 'Password changed.', 'qoutify-estimator' ) ),
		'pw_wrong'       => array( 'error', __( 'Your current password is not correct.', 'qoutify-estimator' ) ),
		'pw_short'       => array( 'error', __( 'The new password must be at least 8 characters.', 'qoutify-estimator' ) ),
		'pw_mismatch'    => array( 'error', __( 'The new passwords do not match.', 'qoutify-estimator' ) ),
		'del_text'       => array( 'error', __( 'Type DELETE in capital letters to confirm.', 'qoutify-estimator' ) ),
		'del_pw'         => array( 'error', __( 'Your password is not correct. Your account was not deleted.', 'qoutify-estimator' ) ),
		'del_admin'      => array( 'error', __( 'Administrator accounts cannot be deleted here.', 'qoutify-estimator' ) ),
		'nonce'          => array( 'error', __( 'The page expired. Please try again.', 'qoutify-estimator' ) ),
	);
}

function qtf_view_settings() {
	$user = wp_get_current_user();
	qtf_page_header( __( 'Settings', 'qoutify-estimator' ), __( 'Manage your profile, security and account settings.', 'qoutify-estimator' ), 'settings' );

	$has_avatar = (bool) get_user_meta( $user->ID, 'qtf_avatar_url', true );
	$verified   = '0' !== (string) get_user_meta( $user->ID, 'qtf_email_verified', true );
	$full_name  = trim( $user->first_name . ' ' . $user->last_name );
	$full_name  = '' !== $full_name ? $full_name : $user->display_name;

	// Profile information.
	echo '<section class="qtf-card qtf-section"><div class="qtf-section__head"><span class="qtf-pagehead__icon qtf-pagehead__icon--sm">' . qtf_icon( 'user', 22 ) . '</span><div><h2 class="qtf-h2">' . esc_html__( 'Profile information', 'qoutify-estimator' ) . '</h2><p class="qtf-muted">' . esc_html__( 'Update your personal details.', 'qoutify-estimator' ) . '</p></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<div class="qtf-profile"><div class="qtf-avatar qtf-avatar--large">' . qtf_avatar_html() . '</div><div><h3 class="qtf-h3">' . esc_html( $full_name ) . '</h3><p class="qtf-muted">' . esc_html( $user->user_email ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
	if ( $verified ) {
		echo '<span class="qtf-badge qtf-badge--completed">' . qtf_icon( 'check-circle', 14 ) . esc_html__( 'Email verified', 'qoutify-estimator' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div></div>';
	echo '<form method="post" class="qtf-form">';
	wp_nonce_field( 'qtf_profile' );
	echo '<input type="hidden" name="qtf_action" value="profile">';
	echo '<div class="qtf-field"><label for="qtf_full_name">' . esc_html__( 'Full name', 'qoutify-estimator' ) . '</label><input type="text" id="qtf_full_name" name="full_name" value="' . esc_attr( $full_name ) . '" maxlength="60" autocomplete="name" placeholder="' . esc_attr__( 'Enter your full name', 'qoutify-estimator' ) . '" required></div>';
	echo '<div class="qtf-field"><label for="qtf_email">' . esc_html__( 'Email address', 'qoutify-estimator' ) . '</label><input type="email" id="qtf_email" value="' . esc_attr( $user->user_email ) . '" readonly aria-describedby="qtf_email_help"><span class="qtf-help" id="qtf_email_help">' . esc_html__( 'Your email address cannot be changed here.', 'qoutify-estimator' ) . '</span></div>';
	echo '<div class="qtf-field"><label for="qtf_phone">' . esc_html__( 'Phone number (optional)', 'qoutify-estimator' ) . '</label><input type="tel" id="qtf_phone" name="phone" value="' . esc_attr( (string) get_user_meta( $user->ID, 'qtf_phone', true ) ) . '" maxlength="20" autocomplete="tel" placeholder="' . esc_attr__( 'For example +1 234 567 8900', 'qoutify-estimator' ) . '"></div>';
	echo '<button type="submit" class="qtf-btn qtf-btn--primary">' . qtf_icon( 'save', 18 ) . esc_html__( 'Save changes', 'qoutify-estimator' ) . '</button></form></section>'; // phpcs:ignore WordPress.Security.EscapeOutput

	// Profile image.
	echo '<section class="qtf-card qtf-section"><div class="qtf-section__head"><span class="qtf-pagehead__icon qtf-pagehead__icon--sm">' . qtf_icon( 'image', 22 ) . '</span><div><h2 class="qtf-h2">' . esc_html__( 'Profile image', 'qoutify-estimator' ) . '</h2><p class="qtf-muted">' . esc_html__( 'Upload a JPG, PNG or WebP image (max 2 MB).', 'qoutify-estimator' ) . '</p></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<div class="qtf-image-row"><form method="post" enctype="multipart/form-data" class="qtf-form qtf-dropzone" data-qtf-dropzone>';
	wp_nonce_field( 'qtf_avatar' );
	echo '<input type="hidden" name="qtf_action" value="avatar">';
	echo '<span class="qtf-dropzone__icon">' . qtf_icon( 'upload', 34 ) . '</span><p>' . esc_html__( 'Drag and drop your image here', 'qoutify-estimator' ) . '</p><p class="qtf-muted">' . esc_html__( 'or', 'qoutify-estimator' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<input type="file" id="qtf_avatar" name="qtf_avatar" accept="image/jpeg,image/png,image/webp" class="qtf-sr-input" aria-describedby="qtf_avatar_name">';
	echo '<label for="qtf_avatar" class="qtf-btn qtf-btn--secondary">' . esc_html__( 'Choose file', 'qoutify-estimator' ) . '</label>';
	echo '<p class="qtf-muted qtf-small" id="qtf_avatar_name" data-qtf-filename></p>';
	echo '<button type="submit" class="qtf-btn qtf-btn--primary">' . esc_html__( 'Save image', 'qoutify-estimator' ) . '</button></form>';
	if ( $has_avatar ) {
		echo '<div class="qtf-current-image"><div class="qtf-avatar qtf-avatar--large">' . qtf_avatar_html() . '</div><div><strong>' . esc_html__( 'Current image', 'qoutify-estimator' ) . '</strong><p class="qtf-muted qtf-small">' . esc_html__( 'Image uploaded', 'qoutify-estimator' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<form method="post" class="qtf-inline" data-qtf-confirm="' . esc_attr__( 'Remove your profile image?', 'qoutify-estimator' ) . '">';
		wp_nonce_field( 'qtf_avatar_remove' );
		echo '<input type="hidden" name="qtf_action" value="avatar_remove"><button type="submit" class="qtf-link-danger">' . qtf_icon( 'trash', 16 ) . esc_html__( 'Remove image', 'qoutify-estimator' ) . '</button></form></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div></section>';

	// Change password.
	echo '<section class="qtf-card qtf-section"><div class="qtf-section__head"><span class="qtf-pagehead__icon qtf-pagehead__icon--sm">' . qtf_icon( 'lock', 22 ) . '</span><div><h2 class="qtf-h2">' . esc_html__( 'Change password', 'qoutify-estimator' ) . '</h2><p class="qtf-muted">' . esc_html__( 'Keep your account secure with a strong password.', 'qoutify-estimator' ) . '</p></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<form method="post" class="qtf-form">';
	wp_nonce_field( 'qtf_password' );
	echo '<input type="hidden" name="qtf_action" value="password"><div class="qtf-grid qtf-grid--3">';
	echo qtf_password_field( 'qtf_current', 'current_password', __( 'Current password', 'qoutify-estimator' ), 'current-password', __( 'Enter your current password', 'qoutify-estimator' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo qtf_password_field( 'qtf_new', 'new_password', __( 'New password', 'qoutify-estimator' ), 'new-password', __( 'Enter new password', 'qoutify-estimator' ), 8 ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo qtf_password_field( 'qtf_confirm', 'confirm_password', __( 'Confirm new password', 'qoutify-estimator' ), 'new-password', __( 'Confirm new password', 'qoutify-estimator' ), 8 ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</div><button type="submit" class="qtf-btn qtf-btn--primary">' . qtf_icon( 'lock', 18 ) . esc_html__( 'Update password', 'qoutify-estimator' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<p class="qtf-muted qtf-small">' . esc_html__( 'Use at least 8 characters. Signed up with Google? Use "Lost your password?" on the login page to set a password first.', 'qoutify-estimator' ) . '</p></form></section>';

	// Delete account (not for administrators).
	if ( ! user_can( $user, 'manage_options' ) ) {
		echo '<section class="qtf-card qtf-section qtf-danger"><div class="qtf-section__head"><span class="qtf-pagehead__icon qtf-pagehead__icon--sm qtf-pagehead__icon--danger">' . qtf_icon( 'trash', 22 ) . '</span><div><h2 class="qtf-h2">' . esc_html__( 'Delete account', 'qoutify-estimator' ) . '</h2><p class="qtf-muted">' . esc_html__( 'This permanently deletes your account and your saved estimates. This action cannot be undone.', 'qoutify-estimator' ) . '</p></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( ! qtf_user_is_staff() && qtf_get_credits() > 0 ) {
			echo '<p><strong>' . esc_html__( 'Your unused credits will be lost.', 'qoutify-estimator' ) . '</strong></p>';
		}
		echo '<button type="button" id="qtf-delete-open" class="qtf-link-danger">' . qtf_icon( 'trash', 16 ) . esc_html__( 'Delete account', 'qoutify-estimator' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div id="qtf-delete-panel" hidden>';
		echo '<p><strong>' . esc_html__( 'Are you sure? Confirm below to delete your account.', 'qoutify-estimator' ) . '</strong></p>';
		echo '<form method="post" id="qtf-delete-form" class="qtf-form">';
		wp_nonce_field( 'qtf_delete' );
		echo '<input type="hidden" name="qtf_action" value="delete">';
		echo '<div class="qtf-field"><label for="qtf_del_text">' . esc_html__( 'Type DELETE to confirm', 'qoutify-estimator' ) . '</label><input type="text" id="qtf_del_text" name="confirm_text" autocomplete="off" placeholder="DELETE" required></div>';
		echo qtf_password_field( 'qtf_del_pw', 'confirm_password', __( 'Your password', 'qoutify-estimator' ), 'current-password', __( 'Enter your password', 'qoutify-estimator' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="qtf-actions-row"><button type="submit" class="qtf-link-danger">' . qtf_icon( 'trash', 16 ) . esc_html__( 'Yes, delete my account', 'qoutify-estimator' ) . '</button> '; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<button type="button" id="qtf-delete-cancel" class="qtf-btn qtf-btn--secondary">' . esc_html__( 'Cancel', 'qoutify-estimator' ) . '</button></div>';
		echo '</form></div></section>';
	}
	echo qtf_support_line(); // phpcs:ignore WordPress.Security.EscapeOutput
}

function qtf_password_field( $id, $name, $label, $autocomplete, $placeholder = '', $minlength = 0 ) {
	$min = $minlength ? ' minlength="' . (int) $minlength . '"' : '';
	return '<div class="qtf-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>'
		. '<div class="qtf-pass"><input type="password" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" autocomplete="' . esc_attr( $autocomplete ) . '" placeholder="' . esc_attr( $placeholder ) . '"' . $min . ' required>'
		. '<button type="button" class="qtf-pass__toggle" data-qtf-toggle aria-label="' . esc_attr__( 'Show password', 'qoutify-estimator' ) . '" data-label-show="' . esc_attr__( 'Show password', 'qoutify-estimator' ) . '" data-label-hide="' . esc_attr__( 'Hide password', 'qoutify-estimator' ) . '" aria-pressed="false">'
		. '<span class="qtf-pass__on">' . qtf_icon( 'eye', 20 ) . '</span><span class="qtf-pass__off">' . qtf_icon( 'eye-off', 20 ) . '</span></button></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
}

function qtf_support_line() {
	return '<p class="qtf-support">' . qtf_icon( 'mail', 16 ) . ' ' . esc_html__( 'Need help? Email', 'qoutify-estimator' ) . ' ' . qtf_support_link() . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
}

function qtf_page_header( $title, $subtitle = '', $icon = '', $action = '' ) {
	echo '<header class="qtf-pagehead">';
	if ( $icon ) {
		echo '<span class="qtf-pagehead__icon">' . qtf_icon( $icon, 26 ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '<div class="qtf-pagehead__text"><h1 class="qtf-title">' . esc_html( $title ) . '</h1>';
	if ( $subtitle ) {
		echo '<p class="qtf-muted">' . esc_html( $subtitle ) . '</p>';
	}
	echo '</div>';
	if ( $action ) {
		echo '<div class="qtf-pagehead__action">' . $action . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</header>';
}

function qtf_illustration() {
	return '<svg viewBox="0 0 320 190" width="100%" height="auto" role="img" aria-label="" focusable="false">'
		. '<circle cx="170" cy="100" r="82" fill="#EAF0F6"/>'
		. '<rect x="30" y="20" width="130" height="110" rx="4" fill="#fff" stroke="#BFD0E4" transform="rotate(-6 95 75)"/>'
		. '<path d="M50 50h80M50 70h60M50 90h80M50 110h50" stroke="#BFD0E4" stroke-width="3" transform="rotate(-6 95 75)"/>'
		. '<rect x="130" y="95" width="110" height="70" fill="#061F3B"/>'
		. '<path d="M118 98 185 45l67 53z" fill="#D5A52A"/>'
		. '<rect x="172" y="120" width="26" height="45" fill="#F5E8BF"/>'
		. '<rect x="142" y="112" width="20" height="20" fill="#EAF0F6"/><rect x="212" y="112" width="20" height="20" fill="#EAF0F6"/>'
		. '<rect x="14" y="160" width="250" height="8" rx="4" fill="#BFD0E4"/>'
		. '<circle cx="262" cy="120" r="22" fill="#2F6F5E"/><rect x="259" y="135" width="6" height="30" fill="#061F3B"/>'
		. '</svg>';
}

function qtf_stat_card( $icon, $tone, $label, $value, $url ) {
	echo '<a class="qtf-stat qtf-stat--link" href="' . esc_url( $url ) . '">';
	echo '<span class="qtf-stat__icon qtf-stat__icon--' . esc_attr( $tone ) . '">' . qtf_icon( $icon, 26 ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<span class="qtf-stat__body"><span class="qtf-stat__label">' . esc_html( $label ) . '</span><strong class="qtf-stat__value">' . esc_html( number_format_i18n( $value ) ) . '</strong></span>';
	echo '<span class="qtf-stat__go">' . qtf_icon( 'arrow-right', 18 ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput
}

function qtf_review_form( $estimate, $suffix = '' ) {
	qtf_post_form_start( 'review_submit', $estimate->uuid, ' class="qtf-form qtf-review-form"' );
	echo '<fieldset class="qtf-rating"><legend>' . esc_html__( 'Your rating', 'qoutify-estimator' ) . '</legend><div class="qtf-stars">';
	for ( $i = 5; $i >= 1; $i-- ) {
		$id = 'qtf_star_' . $i . $suffix;
		echo '<input type="radio" name="rating" id="' . esc_attr( $id ) . '" value="' . (int) $i . '" required>';
		/* translators: %d: stars */
		echo '<label for="' . esc_attr( $id ) . '"><span class="qtf-sr">' . esc_html( sprintf( _n( '%d star', '%d stars', $i, 'qoutify-estimator' ), $i ) ) . '</span>&#9733;</label>';
	}
	echo '</div></fieldset>';
	echo '<div class="qtf-field"><label for="qtf_review_body' . esc_attr( $suffix ) . '">' . esc_html__( 'Your feedback', 'qoutify-estimator' ) . '</label>';
	echo '<textarea id="qtf_review_body' . esc_attr( $suffix ) . '" name="body" rows="4" minlength="10" maxlength="1000" required placeholder="' . esc_attr__( 'Tell us about your experience with this estimate', 'qoutify-estimator' ) . '" aria-describedby="qtf_review_help' . esc_attr( $suffix ) . '"></textarea>';
	echo '<span class="qtf-help" id="qtf_review_help' . esc_attr( $suffix ) . '">' . esc_html__( 'Between 10 and 1000 characters. If approved, your first name and review may appear on the Qoutify website.', 'qoutify-estimator' ) . '</span></div>';
	echo '<button type="submit" class="qtf-btn qtf-btn--primary">' . esc_html__( 'Submit review', 'qoutify-estimator' ) . '</button></form>';
}

/** Popup shown only for a completed estimate that has no review and was not dismissed. */

function qtf_review_modal( $estimate ) {
	echo '<div class="qtf-modal" id="qtf-review-modal" role="dialog" aria-modal="true" aria-labelledby="qtf-review-title" hidden>';
	echo '<div class="qtf-modal__box"><button type="button" class="qtf-modal__close" data-qtf-modal-close aria-label="' . esc_attr__( 'Close', 'qoutify-estimator' ) . '">&times;</button>';
	echo '<h2 class="qtf-h2" id="qtf-review-title">' . esc_html__( 'How was your estimate?', 'qoutify-estimator' ) . '</h2>';
	echo '<p class="qtf-muted">' . esc_html__( 'Your feedback helps us improve. It takes less than a minute.', 'qoutify-estimator' ) . '</p>';
	qtf_review_form( $estimate, '_m' );
	qtf_post_form_start( 'review_dismiss', $estimate->uuid, ' id="qtf-dismiss-form" class="qtf-inline"' );
	echo '<button type="submit" class="qtf-btn qtf-btn--text">' . esc_html__( 'Maybe later', 'qoutify-estimator' ) . '</button></form>';
	echo '</div></div>';
}

function qtf_estimate_cards() {
	$bullets = array(
		'quick'    => array( __( 'Enter your property details', 'qoutify-estimator' ), __( 'No documents needed', 'qoutify-estimator' ), __( 'Cost range with a category breakdown', 'qoutify-estimator' ) ),
		'detailed' => array( __( 'Upload blueprints or documents (1 or more)', 'qoutify-estimator' ), __( 'Narrower range, higher confidence', 'qoutify-estimator' ), __( 'Everything in a Quick Estimate', 'qoutify-estimator' ) ),
	);
	echo '<div class="qtf-price-cards">';
	foreach ( qtf_estimate_options() as $option ) {
		echo '<div class="qtf-price-card"><h3 class="qtf-price-card__title">' . esc_html( $option['title'] ) . '</h3>';
		echo '<p class="qtf-price-card__credits">' . esc_html( qtf_credits_label( $option['credits'] ) ) . '</p>';
		echo '<p class="qtf-price-card__price">' . esc_html( qtf_price_text( $option['price'] ) ) . '</p><ul class="qtf-ticks">';
		foreach ( $bullets[ $option['path'] ] as $bullet ) {
			echo '<li>' . qtf_icon( 'check', 16 ) . esc_html( $bullet ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</ul></div>';
	}
	echo '</div>';
}

function qtf_view_reviews() {
	$user_id = get_current_user_id();
	update_user_meta( $user_id, 'qtf_reviews_seen', 1 );
	qtf_page_header( __( 'My Reviews', 'qoutify-estimator' ), __( 'Share feedback about your completed estimates.', 'qoutify-estimator' ), 'star' );

	// A review form for one completed estimate (?write=ID).
	$write = isset( $_GET['write'] ) ? sanitize_text_field( wp_unslash( $_GET['write'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( $write ) {
		$estimate = qtf_estimate_by_uuid( $write, $user_id );
		if ( $estimate && 'completed' === $estimate->status && ! qtf_review_for_estimate( $user_id, $estimate->id ) ) {
			echo '<div class="qtf-card"><h2 class="qtf-h2">' . esc_html__( 'Write a review', 'qoutify-estimator' ) . '</h2><p class="qtf-muted">' . esc_html( qtf_estimate_title( $estimate ) ) . '</p>';
			qtf_review_form( $estimate );
			echo '</div>';
		}
	}

	$awaiting = qtf_estimates_awaiting_review( $user_id );
	if ( $awaiting ) {
		echo '<div class="qtf-card"><h2 class="qtf-h2">' . esc_html__( 'Waiting for your review', 'qoutify-estimator' ) . '</h2><div class="qtf-table-wrap"><table class="qtf-table"><tbody>';
		foreach ( $awaiting as $row ) {
			echo '<tr><td>' . esc_html( qtf_estimate_title( $row ) ) . '<br><span class="qtf-muted qtf-small">' . esc_html( qtf_type_label( $row ) ) . '</span></td>';
			echo '<td class="qtf-actions"><a class="qtf-btn qtf-btn--secondary" href="' . esc_url( add_query_arg( 'write', $row->uuid, qtf_dashboard_url( 'my-reviews' ) ) ) . '">' . esc_html__( 'Write a review', 'qoutify-estimator' ) . '</a></td></tr>';
		}
		echo '</tbody></table></div></div>';
	}

	$reviews = qtf_reviews_for_user( $user_id );
	echo '<div class="qtf-card"><h2 class="qtf-h2">' . esc_html__( 'Your reviews', 'qoutify-estimator' ) . '</h2>';
	if ( $reviews ) {
		echo '<div class="qtf-table-wrap"><table class="qtf-table"><thead><tr><th>' . esc_html__( 'Date', 'qoutify-estimator' ) . '</th><th>' . esc_html__( 'Rating', 'qoutify-estimator' ) . '</th><th>' . esc_html__( 'Review', 'qoutify-estimator' ) . '</th><th>' . esc_html__( 'Status', 'qoutify-estimator' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $reviews as $review ) {
			echo '<tr><td>' . esc_html( get_date_from_gmt( $review->created_at, 'M j, Y' ) ) . '</td>';
			echo '<td><span class="qtf-rating-static" aria-label="' . esc_attr( sprintf( __( '%d out of 5', 'qoutify-estimator' ), (int) $review->rating ) ) . '">' . esc_html( str_repeat( "\u{2605}", (int) $review->rating ) ) . '</span></td>';
			echo '<td>' . nl2br( esc_html( $review->body ) ) . '</td><td>' . qtf_review_status_badge( $review->status ) . '</td><td class="qtf-actions">'; // phpcs:ignore WordPress.Security.EscapeOutput
			qtf_post_form_start( 'review_delete', '', ' class="qtf-inline" data-qtf-confirm="' . esc_attr__( 'Delete your review?', 'qoutify-estimator' ) . '"' );
			echo '<input type="hidden" name="review_id" value="' . esc_attr( $review->id ) . '"><button type="submit" class="qtf-link-danger">' . qtf_icon( 'trash', 16 ) . esc_html__( 'Delete', 'qoutify-estimator' ) . '</button></form></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</tbody></table></div><p class="qtf-muted qtf-small">' . esc_html__( 'New reviews are checked by our team before they can appear on the website.', 'qoutify-estimator' ) . '</p>';
	} else {
		echo '<div class="qtf-empty"><span class="qtf-empty__icon">' . qtf_icon( 'star', 26 ) . '</span><p>' . esc_html__( 'You have not written a review yet. After an estimate is completed, you can share your feedback here.', 'qoutify-estimator' ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div>';
}

