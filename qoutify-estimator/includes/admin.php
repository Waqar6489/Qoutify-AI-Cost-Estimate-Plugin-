<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * wp-admin screens for administrators: Settings, Pricing Data, Estimates.
 */
function qtf_admin_menu() {
	add_menu_page( 'Qoutify', 'Qoutify', 'manage_options', 'qtf-settings', 'qtf_admin_settings_page', 'dashicons-calculator', 56 );
	add_submenu_page( 'qtf-settings', 'Settings', 'Settings', 'manage_options', 'qtf-settings', 'qtf_admin_settings_page' );
	add_submenu_page( 'qtf-settings', 'Pricing Data', 'Pricing Data', 'manage_options', 'qtf-pricing', 'qtf_admin_pricing_page' );
	add_submenu_page( 'qtf-settings', 'Credit Packages', 'Credit Packages', 'manage_options', 'qtf-packages', 'qtf_admin_packages_page' );
	add_submenu_page( 'qtf-settings', 'Reviews', 'Reviews', 'manage_options', 'qtf-reviews', 'qtf_admin_reviews_page' );
	add_submenu_page( 'qtf-settings', 'AI Prompt', 'AI Prompt', 'manage_options', 'qtf-prompt', 'qtf_admin_prompt_page' );
	add_submenu_page( 'qtf-settings', 'Estimates', 'Estimates', 'manage_options', 'qtf-estimates', 'qtf_admin_estimates_page' );
}
add_action( 'admin_menu', 'qtf_admin_menu' );

function qtf_admin_post( $key, $default = '' ) {
	// Nonce and capability are checked by every caller before this runs.
	return isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : $default; // phpcs:ignore WordPress.Security
}

function qtf_admin_notice( $type, $text ) {
	echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
}

/* ------------------------------------------------------------------ settings */

function qtf_admin_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['qtf_save_settings'] ) ) {
		check_admin_referer( 'qtf_admin_settings' );
		$s = qtf_settings();

		$provider            = sanitize_key( qtf_admin_post( 'ai_provider' ) );
		$s['ai_provider']    = in_array( $provider, array( 'groq', 'openai' ), true ) ? $provider : 'groq';
		$s['ai_model']       = sanitize_text_field( qtf_admin_post( 'ai_model' ) );
		$base                = trim( (string) qtf_admin_post( 'ai_base_url' ) );
		$s['ai_base_url']    = ( '' !== $base && 0 === strpos( $base, 'https://' ) ) ? esc_url_raw( $base ) : '';
		$s['ai_max_images']  = max( 0, min( 5, absint( qtf_admin_post( 'ai_max_images', 4 ) ) ) );
		foreach ( array( 'cost_res_quick', 'cost_res_detailed', 'cost_com_quick', 'cost_com_detailed' ) as $key ) {
			// A cost can never be below 1 credit, so no estimate can be free for a public user.
			$s[ $key ] = max( 1, min( 100, absint( qtf_admin_post( $key, 1 ) ) ) );
		}
		$price_raw = trim( (string) qtf_admin_post( 'credit_price', '' ) );
		if ( is_numeric( $price_raw ) && (float) $price_raw >= 0.01 && (float) $price_raw <= 100000 ) {
			$s['credit_price'] = number_format( round( (float) $price_raw, 2 ), 2, '.', '' );
		} else {
			qtf_admin_notice( 'error', 'The price per credit must be a number from 0.01 to 100000. The old price was kept.' );
		}
		$s['max_custom_credits'] = max( 1, min( 10000, absint( qtf_admin_post( 'max_custom_credits', 500 ) ) ) );
		foreach ( array( 'overhead_pct', 'contingency_pct', 'markup_pct', 'occupied_pct' ) as $key ) {
			$s[ $key ] = max( 0, min( 100, (float) qtf_admin_post( $key, 0 ) ) );
		}
		$status              = sanitize_key( qtf_admin_post( 'pricing_status' ) );
		$s['pricing_status'] = ( 'approved' === $status ) ? 'approved' : 'sample';
		$s['daily_limit']    = absint( qtf_admin_post( 'daily_limit', 10 ) );
		$s['max_files']      = max( 1, min( 30, absint( qtf_admin_post( 'max_files', 10 ) ) ) );
		$s['max_file_mb']    = max( 1, min( 50, absint( qtf_admin_post( 'max_file_mb', 20 ) ) ) );
		update_option( 'qtf_settings', $s );
		qtf_admin_notice( 'success', 'Settings saved.' );
	}

	if ( isset( $_POST['qtf_test_ai'] ) ) {
		check_admin_referer( 'qtf_admin_test_ai' );
		$reply = qtf_ai_call(
			0,
			array(
				array( 'role' => 'system', 'content' => 'Reply with only this JSON: {"ok": true}' ),
				array( 'role' => 'user', 'content' => 'Test' ),
			)
		);
		if ( is_wp_error( $reply ) ) {
			qtf_admin_notice( 'error', 'AI test failed: ' . $reply->get_error_code() . '. Check the key in wp-config.php, the provider and the model name.' );
		} else {
			qtf_admin_notice( 'success', 'AI connection works. Reply: ' . substr( wp_strip_all_tags( $reply ), 0, 120 ) );
		}
	}

	$s       = qtf_settings();
	$has_key = '' !== qtf_ai_key();
	?>
	<div class="wrap">
		<h1>Qoutify Settings</h1>

		<h2>AI connection</h2>
		<p>API key: <strong><?php echo $has_key ? 'found in wp-config.php' : 'NOT FOUND'; ?></strong>.
		The key is never saved in the database or shown here. Add it to <code>wp-config.php</code> above the line "That's all, stop editing":</p>
		<pre style="background:#fff;border:1px solid #ccd0d4;padding:12px;max-width:640px;">define( 'QTF_AI_API_KEY', 'paste-your-key-here' );</pre>
		<form method="post" style="margin-bottom:24px;">
			<?php wp_nonce_field( 'qtf_admin_test_ai' ); ?>
			<button type="submit" name="qtf_test_ai" value="1" class="button">Test AI connection</button>
		</form>

		<form method="post">
			<?php wp_nonce_field( 'qtf_admin_settings' ); ?>
			<table class="form-table" role="presentation">
				<tr><th>Provider</th><td>
					<select name="ai_provider">
						<option value="groq" <?php selected( $s['ai_provider'], 'groq' ); ?>>Groq (free testing)</option>
						<option value="openai" <?php selected( $s['ai_provider'], 'openai' ); ?>>OpenAI (paid)</option>
					</select>
					<p class="description">To switch from testing to paid: change the key in wp-config.php, set the provider and the model here, then click "Test AI connection".</p>
				</td></tr>
				<tr><th>Model</th><td><input type="text" name="ai_model" class="regular-text" value="<?php echo esc_attr( $s['ai_model'] ); ?>"><p class="description">Must be a model that accepts images if you want blueprints analyzed. Check the provider's current model list.</p></td></tr>
				<tr><th>Custom base URL (optional)</th><td><input type="text" name="ai_base_url" class="regular-text" value="<?php echo esc_attr( $s['ai_base_url'] ); ?>"><p class="description">Only for another OpenAI-compatible provider. Must start with https:// and end before /chat/completions.</p></td></tr>
				<tr><th>Maximum images per estimate</th><td><input type="number" name="ai_max_images" min="0" max="5" value="<?php echo esc_attr( $s['ai_max_images'] ); ?>"></td></tr>
			</table>

			<h2>Price per credit</h2>
			<table class="form-table" role="presentation">
				<tr><th>Price of one credit</th><td>
					<input type="number" name="credit_price" step="0.01" min="0.01" value="<?php echo esc_attr( qtf_setting( 'credit_price' ) ); ?>">
					<strong><?php echo esc_html( qtf_currency_code() ); ?></strong>
					<p class="description">Every price in the dashboard is: credits needed &times; this price. Changing it affects new purchases only; old orders keep the price they were bought at. Your WooCommerce store currency is <strong><?php echo esc_html( qtf_currency_code() ); ?></strong>.</p>
				</td></tr>
				<tr><th>Largest custom purchase</th><td><input type="number" name="max_custom_credits" min="1" max="10000" value="<?php echo esc_attr( qtf_setting( 'max_custom_credits' ) ); ?>"> credits at a time</td></tr>
			</table>

			<h2>Credits per estimate (public users)</h2>
			<table class="form-table" role="presentation">
				<tr><th>Residential quick / detailed</th><td><input type="number" min="1" name="cost_res_quick" value="<?php echo esc_attr( $s['cost_res_quick'] ); ?>"> / <input type="number" min="1" name="cost_res_detailed" value="<?php echo esc_attr( $s['cost_res_detailed'] ); ?>"></td></tr>
				<tr><th>Commercial quick / detailed</th><td><input type="number" min="1" name="cost_com_quick" value="<?php echo esc_attr( $s['cost_com_quick'] ); ?>"> / <input type="number" min="1" name="cost_com_detailed" value="<?php echo esc_attr( $s['cost_com_detailed'] ); ?>"></td></tr>
			</table>
			<table class="widefat striped" style="max-width:560px;margin:12px 0;">
				<thead><tr><th>Estimate</th><th>Credits</th><th>Price now</th></tr></thead>
				<tbody>
				<?php foreach ( qtf_estimate_options() as $option ) : ?>
					<tr><td><?php echo esc_html( $option['title'] ); ?></td><td><?php echo esc_html( $option['credits'] ); ?></td><td><?php echo esc_html( qtf_price_text( $option['price'] ) ); ?></td></tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description">Team members (administrators and Qoutify Staff) never use credits. Prices of credit packages are set on the WooCommerce products.</p>

			<h2>Pricing rules</h2>
			<table class="form-table" role="presentation">
				<tr><th>Overhead %</th><td><input type="number" step="0.1" name="overhead_pct" value="<?php echo esc_attr( $s['overhead_pct'] ); ?>"></td></tr>
				<tr><th>Contingency %</th><td><input type="number" step="0.1" name="contingency_pct" value="<?php echo esc_attr( $s['contingency_pct'] ); ?>"></td></tr>
				<tr><th>Markup %</th><td><input type="number" step="0.1" name="markup_pct" value="<?php echo esc_attr( $s['markup_pct'] ); ?>"></td></tr>
				<tr><th>Commercial "occupied" extra %</th><td><input type="number" step="0.1" name="occupied_pct" value="<?php echo esc_attr( $s['occupied_pct'] ); ?>"></td></tr>
				<tr><th>Pricing data status</th><td>
					<select name="pricing_status">
						<option value="sample" <?php selected( $s['pricing_status'], 'sample' ); ?>>Sample data (test mode)</option>
						<option value="approved" <?php selected( $s['pricing_status'], 'approved' ); ?>>Approved real data</option>
					</select>
					<p class="description">While this is "Sample", every estimate shows a test-mode warning. Switch to "Approved" only after real rates are entered on the Pricing Data page.</p>
				</td></tr>
			</table>

			<h2>Limits</h2>
			<table class="form-table" role="presentation">
				<tr><th>Estimates per user per 24 hours</th><td><input type="number" min="0" name="daily_limit" value="<?php echo esc_attr( $s['daily_limit'] ); ?>"> <span class="description">0 = no limit</span></td></tr>
				<tr><th>Maximum files per estimate</th><td><input type="number" min="1" max="30" name="max_files" value="<?php echo esc_attr( $s['max_files'] ); ?>"></td></tr>
				<tr><th>Maximum size per file (MB)</th><td><input type="number" min="1" max="50" name="max_file_mb" value="<?php echo esc_attr( $s['max_file_mb'] ); ?>"></td></tr>
			</table>
			<?php submit_button( 'Save settings', 'primary', 'qtf_save_settings' ); ?>
		</form>
	</div>
	<?php
}

/* -------------------------------------------------------------- pricing data */

function qtf_admin_pricing_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['qtf_save_pricing'] ) ) {
		check_admin_referer( 'qtf_admin_pricing' );
		$defaults = qtf_pricing_defaults();
		$in       = (array) qtf_admin_post( 'p', array() );
		$p        = array(
			'base'      => array(),
			'condition' => array(),
			'quality'   => array(),
			'split'     => array(),
			'region'    => array(),
		);
		foreach ( array( 'residential', 'commercial' ) as $type ) {
			foreach ( array_keys( qtf_categories() ) as $key ) {
				$p['base'][ $type ][ $key ] = isset( $in['base'][ $type ][ $key ] ) ? max( 0, (float) $in['base'][ $type ][ $key ] ) : $defaults['base'][ $type ][ $key ];
			}
		}
		foreach ( array_keys( $defaults['condition'] ) as $key ) {
			$p['condition'][ $key ] = isset( $in['condition'][ $key ] ) ? max( 0.01, (float) $in['condition'][ $key ] ) : $defaults['condition'][ $key ];
		}
		foreach ( array_keys( $defaults['quality'] ) as $key ) {
			$p['quality'][ $key ] = isset( $in['quality'][ $key ] ) ? max( 0.01, (float) $in['quality'][ $key ] ) : $defaults['quality'][ $key ];
		}
		$bad_split = false;
		foreach ( array_keys( qtf_categories() ) as $key ) {
			$row = isset( $in['split'][ $key ] ) ? array_map( 'floatval', (array) $in['split'][ $key ] ) : $defaults['split'][ $key ];
			$row = array_pad( array_slice( $row, 0, 3 ), 3, 0 );
			if ( abs( array_sum( $row ) - 100 ) > 0.5 ) {
				$bad_split = true;
			}
			$p['split'][ $key ] = $row;
		}
		$states = qtf_us_states();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) ( isset( $in['region'] ) ? $in['region'] : '' ) ) as $line ) {
			$parts = explode( '=', $line );
			if ( 2 === count( $parts ) ) {
				$code  = strtoupper( substr( trim( $parts[0] ), 0, 2 ) );
				$value = (float) trim( $parts[1] );
				if ( isset( $states[ $code ] ) && $value >= 0.5 && $value <= 3 ) {
					$p['region'][ $code ] = $value;
				}
			}
		}
		update_option( 'qtf_pricing', $p );
		qtf_admin_notice( 'success', 'Pricing data saved.' );
		if ( $bad_split ) {
			qtf_admin_notice( 'warning', 'A material / labor / equipment split does not add up to 100. Please check the split table.' );
		}
	}

	$p = qtf_pricing();
	?>
	<div class="wrap">
		<h1>Pricing Data</h1>
		<?php if ( qtf_pricing_is_sample() ) : ?>
			<div class="notice notice-warning"><p><strong>These are SAMPLE rates for testing.</strong> Replace them with the client's real rates, then set "Pricing data status" to Approved on the Settings page.</p></div>
		<?php endif; ?>
		<p>Formula for each category: size &times; scope share (from the AI) &times; base rate &times; condition &times; quality &times; region &times; occupied.</p>
		<form method="post">
			<?php wp_nonce_field( 'qtf_admin_pricing' ); ?>

			<h2>Base rate ($ per sq ft, Moderate renovation at Standard quality)</h2>
			<table class="widefat striped" style="max-width:760px;">
				<thead><tr><th>Category</th><th>Residential</th><th>Commercial</th></tr></thead>
				<tbody>
				<?php foreach ( qtf_categories() as $key => $label ) : ?>
					<tr>
						<td><?php echo esc_html( $label ); ?></td>
						<td><input type="number" step="0.01" min="0" name="p[base][residential][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $p['base']['residential'][ $key ] ); ?>"></td>
						<td><input type="number" step="0.01" min="0" name="p[base][commercial][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $p['base']['commercial'][ $key ] ); ?>"></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2>Condition multipliers</h2>
			<table class="widefat striped" style="max-width:760px;"><tbody>
			<?php foreach ( qtf_condition_options() as $key => $label ) : ?>
				<tr><td><?php echo esc_html( $label ); ?></td><td><input type="number" step="0.01" min="0.01" name="p[condition][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $p['condition'][ $key ] ); ?>"></td></tr>
			<?php endforeach; ?>
			</tbody></table>

			<h2>Quality multipliers</h2>
			<table class="widefat striped" style="max-width:760px;"><tbody>
			<?php foreach ( qtf_quality_options() as $key => $label ) : ?>
				<tr><td><?php echo esc_html( $label ); ?></td><td><input type="number" step="0.01" min="0.01" name="p[quality][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $p['quality'][ $key ] ); ?>"></td></tr>
			<?php endforeach; ?>
			</tbody></table>

			<h2>Material / labor / equipment split (% of each category, must total 100)</h2>
			<table class="widefat striped" style="max-width:760px;">
				<thead><tr><th>Category</th><th>Material</th><th>Labor</th><th>Equipment</th></tr></thead>
				<tbody>
				<?php foreach ( qtf_categories() as $key => $label ) : ?>
					<tr>
						<td><?php echo esc_html( $label ); ?></td>
						<?php for ( $i = 0; $i < 3; $i++ ) : ?>
							<td><input type="number" step="0.1" min="0" max="100" name="p[split][<?php echo esc_attr( $key ); ?>][]" value="<?php echo esc_attr( $p['split'][ $key ][ $i ] ); ?>"></td>
						<?php endfor; ?>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2>Regional price factor by state</h2>
			<p>One state per line, for example <code>NY=1.25</code>. States not listed use 1.00 and the estimate says local pricing needs verification.</p>
			<?php
			$lines = array();
			foreach ( $p['region'] as $code => $value ) {
				$lines[] = $code . '=' . $value;
			}
			?>
			<textarea name="p[region]" rows="8" cols="30"><?php echo esc_textarea( implode( "\n", $lines ) ); ?></textarea>

			<?php submit_button( 'Save pricing data', 'primary', 'qtf_save_pricing' ); ?>
		</form>
	</div>
	<?php
}

/* ----------------------------------------------------------------- estimates */

function qtf_admin_estimates_page() {
	global $wpdb;
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$rows = $wpdb->get_results(
		'SELECT e.*, u.user_email, (SELECT COALESCE(SUM(a.input_tokens + a.output_tokens),0) FROM ' . qtf_table( 'ai_usage' ) . ' a WHERE a.estimate_id = e.id) AS tokens FROM ' . qtf_table( 'estimates' ) . ' e LEFT JOIN ' . $wpdb->users . ' u ON u.ID = e.user_id ORDER BY e.id DESC LIMIT 100'
	);
	?>
	<div class="wrap">
		<h1>Estimates</h1>
		<p>The latest 100 estimates from all users. Street addresses and uploaded files are not shown here.</p>
		<table class="widefat striped">
			<thead><tr><th>ID</th><th>Date (UTC)</th><th>User</th><th>Type</th><th>Status</th><th>Credits</th><th>Estimate</th><th>Confidence</th><th>Model</th><th>Tokens</th><th>Error</th></tr></thead>
			<tbody>
			<?php if ( ! $rows ) : ?>
				<tr><td colspan="11">No estimates yet.</td></tr>
			<?php endif; ?>
			<?php foreach ( $rows as $row ) : ?>
				<tr>
					<td><?php echo esc_html( $row->id ); ?></td>
					<td><?php echo esc_html( $row->created_at ); ?></td>
					<td><?php echo esc_html( $row->user_email ); ?><?php echo $row->is_team ? ' (team)' : ''; ?></td>
					<td><?php echo esc_html( $row->project_type . ' / ' . $row->path ); ?></td>
					<td><?php echo esc_html( $row->status ); ?></td>
					<td><?php echo esc_html( $row->credits_charged ); ?></td>
					<td><?php echo 'completed' === $row->status ? esc_html( qtf_money( $row->total_low ) . ' - ' . qtf_money( $row->total_high ) ) : '-'; ?></td>
					<td><?php echo null !== $row->confidence ? esc_html( $row->confidence . '%' ) : '-'; ?></td>
					<td><?php echo esc_html( $row->ai_model ); ?></td>
					<td><?php echo esc_html( number_format_i18n( $row->tokens ) ); ?></td>
					<td><?php echo esc_html( $row->error_message ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}


/* ----------------------------------------------------------------- AI prompt */

function qtf_admin_prompt_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['qtf_save_prompt'] ) ) {
		check_admin_referer( 'qtf_admin_prompt' );
		$text = trim( sanitize_textarea_field( qtf_admin_post( 'qtf_instructions' ) ) );
		if ( '' === $text ) {
			qtf_admin_notice( 'error', 'The instructions cannot be empty. Use "Reset to default" if you want the original text.' );
		} elseif ( strlen( $text ) > 6000 ) {
			qtf_admin_notice( 'error', 'The instructions are too long. The maximum is 6000 characters.' );
		} else {
			update_option( 'qtf_prompt', $text );
			update_option( 'qtf_prompt_version', qtf_prompt_version() + 1 );
			qtf_admin_notice( 'success', 'Instructions saved. Now use "Test this prompt" to check that the AI still answers correctly.' );
		}
	}

	if ( isset( $_POST['qtf_reset_prompt'] ) ) {
		check_admin_referer( 'qtf_admin_prompt' );
		delete_option( 'qtf_prompt' );
		update_option( 'qtf_prompt_version', qtf_prompt_version() + 1 );
		qtf_admin_notice( 'success', 'The instructions were reset to the default text.' );
	}

	$test = null;
	if ( isset( $_POST['qtf_test_prompt'] ) ) {
		check_admin_referer( 'qtf_admin_prompt_test' );
		$fake    = (object) array(
			'project_type' => 'residential',
			'path'         => 'quick',
		);
		$details = array(
			'city'          => 'Austin',
			'state'         => 'TX',
			'property_type' => 'single_family',
			'project_kind'  => 'renovation',
			'sqft'          => 2000,
			'floors'        => 2,
			'bedrooms'      => 4,
			'bathrooms'     => 2.5,
			'condition'     => 'moderate',
			'quality'       => 'standard',
			'year_built'    => 1995,
			'work_wanted'   => array( 'kitchen', 'bathrooms', 'flooring', 'painting' ),
			'description'   => 'Update the kitchen and all bathrooms, replace flooring on the first floor and repaint the whole house.',
		);
		$reply = qtf_ai_call( 0, qtf_ai_build_messages( $fake, $details, array( 'images' => array(), 'texts' => array(), 'notes' => array() ) ) );
		if ( is_wp_error( $reply ) ) {
			$test = array( 'error' => 'The AI request failed: ' . $reply->get_error_code() );
		} else {
			$parsed = qtf_ai_parse( $reply );
			$test   = $parsed ? array( 'parsed' => $parsed ) : array( 'error' => 'The AI answered, but the answer was not in the required format.', 'raw' => substr( wp_strip_all_tags( $reply ), 0, 400 ) );
		}
	}
	?>
	<div class="wrap">
		<h1>AI Prompt</h1>
		<p>This is the task the AI receives with every estimate. Prompt version: <strong><?php echo esc_html( qtf_prompt_version() ); ?></strong>. The version is saved with each estimate, so you can always see which instructions produced it.</p>

		<form method="post">
			<?php wp_nonce_field( 'qtf_admin_prompt' ); ?>
			<h2>Instructions (you can edit this)</h2>
			<textarea name="qtf_instructions" rows="16" style="width:100%;max-width:900px;"><?php echo esc_textarea( qtf_ai_instructions() ); ?></textarea>
			<p class="description">Describe the task and how careful the AI should be. Change one thing at a time, then test. Do not paste prices or secret information here.</p>
			<p>
				<?php submit_button( 'Save instructions', 'primary', 'qtf_save_prompt', false ); ?>
				<?php submit_button( 'Reset to default', 'secondary', 'qtf_reset_prompt', false, array( 'onclick' => "return confirm('Reset the instructions to the default text?');" ) ); ?>
			</p>
		</form>

		<h2>Fixed rules and answer format (locked)</h2>
		<p class="description">The code always adds this after your instructions. It keeps the safety rules and the answer format that the calculator needs, so an edit above cannot break the estimator.</p>
		<pre style="background:#fff;border:1px solid #ccd0d4;padding:12px;max-width:900px;white-space:pre-wrap;"><?php echo esc_html( qtf_ai_locked_prompt() ); ?></pre>

		<h2>Test this prompt</h2>
		<p>Sends a sample 2,000 sq ft house renovation (kitchen, bathrooms, flooring, painting) and shows how the AI answers. No credits are used and no estimate is created.</p>
		<form method="post">
			<?php wp_nonce_field( 'qtf_admin_prompt_test' ); ?>
			<?php submit_button( 'Test this prompt', 'secondary', 'qtf_test_prompt', false ); ?>
		</form>

		<?php if ( is_array( $test ) ) : ?>
			<h3>Test result</h3>
			<?php if ( isset( $test['error'] ) ) : ?>
				<div class="notice notice-error inline"><p><?php echo esc_html( $test['error'] ); ?></p>
				<?php if ( ! empty( $test['raw'] ) ) : ?><p><code><?php echo esc_html( $test['raw'] ); ?></code></p><?php endif; ?></div>
			<?php else : ?>
				<table class="widefat striped" style="max-width:900px;">
					<thead><tr><th>Category</th><th>Scope share</th><th>Reason</th></tr></thead>
					<tbody>
					<?php foreach ( qtf_categories() as $key => $label ) : ?>
						<tr>
							<td><?php echo esc_html( $label ); ?></td>
							<td><?php echo esc_html( round( $test['parsed']['scope'][ $key ] * 100 ) . '%' ); ?></td>
							<td><?php echo esc_html( $test['parsed']['reasons'][ $key ] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p><strong>Summary:</strong> <?php echo esc_html( $test['parsed']['summary'] ); ?></p>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}


/* ------------------------------------------------------------ credit packages */

function qtf_package_posts() {
	return get_posts(
		array(
			'post_type'      => 'product',
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'posts_per_page' => 50,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'meta_query'     => array(
				array(
					'key'     => '_qtf_credits',
					'value'   => 0,
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
			),
		)
	);
}

function qtf_admin_packages_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! function_exists( 'wc_get_product' ) ) {
		echo '<div class="wrap"><h1>Credit Packages</h1><p>WooCommerce must be active.</p></div>';
		return;
	}

	if ( isset( $_POST['qtf_save_packages'] ) ) {
		check_admin_referer( 'qtf_admin_packages' );
		$rows = (array) qtf_admin_post( 'pkg', array() );
		foreach ( $rows as $id => $row ) {
			$id = absint( $id );
			// Only products that are already credit packages can be changed here.
			if ( ! $id || (int) get_post_meta( $id, '_qtf_credits', true ) < 1 ) {
				continue;
			}
			$product = wc_get_product( $id );
			if ( ! $product ) {
				continue;
			}
			if ( ! empty( $row['trash'] ) ) {
				wp_trash_post( $id );
				continue;
			}
			$credits = isset( $row['credits'] ) ? absint( $row['credits'] ) : 0;
			$price   = isset( $row['price'] ) ? (float) $row['price'] : 0;
			if ( $credits < 1 || $credits > 100000 || $price <= 0 ) {
				qtf_admin_notice( 'error', 'Package #' . $id . ' was not changed: credits must be at least 1 and the price above 0.' );
				continue;
			}
			$product->set_name( sanitize_text_field( isset( $row['name'] ) ? $row['name'] : $product->get_name() ) );
			$product->set_regular_price( wc_format_decimal( $price ) );
			$product->set_status( empty( $row['active'] ) ? 'draft' : 'publish' );
			$product->update_meta_data( '_qtf_credits', $credits );
			$product->save();
		}

		$new = (array) qtf_admin_post( 'new', array() );
		$name    = isset( $new['name'] ) ? sanitize_text_field( $new['name'] ) : '';
		$credits = isset( $new['credits'] ) ? absint( $new['credits'] ) : 0;
		$price   = isset( $new['price'] ) ? (float) $new['price'] : 0;
		if ( '' !== $name || $credits || $price ) {
			if ( '' === $name || $credits < 1 || $credits > 100000 || $price <= 0 ) {
				qtf_admin_notice( 'error', 'The new package was not added: it needs a name, at least 1 credit and a price above 0.' );
			} else {
				$product = new WC_Product_Simple();
				$product->set_name( $name );
				$product->set_status( 'publish' );
				$product->set_catalog_visibility( 'hidden' );
				$product->set_virtual( true );
				$product->set_sold_individually( true );
				$product->set_regular_price( wc_format_decimal( $price ) );
				$product->update_meta_data( '_qtf_credits', $credits );
				$product->save();
			}
		}
		qtf_admin_notice( 'success', 'Packages saved.' );
	}

	$posts = qtf_package_posts();
	?>
	<div class="wrap">
		<h1>Credit Packages</h1>
		<p>Ready-made packages customers can buy on the Credits page. Each one is a WooCommerce product, so payments, orders and refunds work as usual. Standard price = credits &times; the price per credit (<?php echo esc_html( qtf_price_text( qtf_credit_price() ) ); ?> now), so you can see each discount.</p>
		<form method="post">
			<?php wp_nonce_field( 'qtf_admin_packages' ); ?>
			<table class="widefat striped" style="max-width:980px;">
				<thead><tr><th>Name</th><th>Credits</th><th>Price (<?php echo esc_html( qtf_currency_code() ); ?>)</th><th>Per credit</th><th>Standard price</th><th>Active</th><th>Remove</th></tr></thead>
				<tbody>
				<?php foreach ( $posts as $post ) :
					$product = wc_get_product( $post->ID );
					if ( ! $product ) {
						continue;
					}
					$credits  = (int) get_post_meta( $post->ID, '_qtf_credits', true );
					$price    = (float) $product->get_regular_price();
					$standard = qtf_price_for_credits( $credits );
					?>
					<tr>
						<td><input type="text" class="regular-text" name="pkg[<?php echo esc_attr( $post->ID ); ?>][name]" value="<?php echo esc_attr( $product->get_name() ); ?>"></td>
						<td><input type="number" min="1" name="pkg[<?php echo esc_attr( $post->ID ); ?>][credits]" value="<?php echo esc_attr( $credits ); ?>" class="small-text"></td>
						<td><input type="number" min="0.01" step="0.01" name="pkg[<?php echo esc_attr( $post->ID ); ?>][price]" value="<?php echo esc_attr( $price ); ?>" class="small-text"></td>
						<td><?php echo esc_html( $credits ? qtf_price_text( round( $price / $credits, 2 ) ) : '-' ); ?></td>
						<td><?php echo esc_html( qtf_price_text( $standard ) ); ?><?php echo ( $standard > 0 && $price < $standard ) ? ' <small>(' . esc_html( round( ( 1 - $price / $standard ) * 100 ) ) . '% off)</small>' : ''; ?></td>
						<td><input type="checkbox" name="pkg[<?php echo esc_attr( $post->ID ); ?>][active]" value="1" <?php checked( 'publish', $post->post_status ); ?>></td>
						<td><input type="checkbox" name="pkg[<?php echo esc_attr( $post->ID ); ?>][trash]" value="1"></td>
					</tr>
				<?php endforeach; ?>
				<?php if ( ! $posts ) : ?>
					<tr><td colspan="7">No packages yet. Add one below.</td></tr>
				<?php endif; ?>
				<tr>
					<td><input type="text" class="regular-text" name="new[name]" placeholder="New package name, for example 10 Credits"></td>
					<td><input type="number" min="1" name="new[credits]" class="small-text" placeholder="10"></td>
					<td><input type="number" min="0.01" step="0.01" name="new[price]" class="small-text" placeholder="45"></td>
					<td colspan="4"><em>Leave empty if you do not want to add a package.</em></td>
				</tr>
				</tbody>
			</table>
			<?php submit_button( 'Save packages', 'primary', 'qtf_save_packages' ); ?>
		</form>
	</div>
	<?php
}
