<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$qtf_view = qtf_current_view();
$qtf_user = wp_get_current_user();
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'qtf-app' ); ?>>
<?php wp_body_open(); ?>

<a class="qtf-skip" href="#qtf-main"><?php esc_html_e( 'Skip to content', 'qoutify-estimator' ); ?></a>

<header class="qtf-header">
	<div class="qtf-header__left">
		<button type="button" class="qtf-burger" aria-label="<?php esc_attr_e( 'Open menu', 'qoutify-estimator' ); ?>" aria-expanded="false" aria-controls="qtf-sidebar" data-label-open="<?php esc_attr_e( 'Open menu', 'qoutify-estimator' ); ?>" data-label-close="<?php esc_attr_e( 'Close menu', 'qoutify-estimator' ); ?>">
			<span class="qtf-burger__bar"></span><span class="qtf-burger__bar"></span><span class="qtf-burger__bar"></span>
		</button>
		<a class="qtf-brand" href="<?php echo esc_url( qtf_dashboard_url() ); ?>">
			<?php echo qtf_logo_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<span class="qtf-brand__title"><?php esc_html_e( 'AI Cost Estimation', 'qoutify-estimator' ); ?></span>
		</a>
	</div>
	<a class="qtf-userbox" href="<?php echo esc_url( qtf_dashboard_url( 'settings' ) ); ?>" aria-label="<?php esc_attr_e( 'Settings', 'qoutify-estimator' ); ?>">
		<span class="qtf-avatar"><?php echo qtf_avatar_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
		<span class="qtf-userbox__name"><?php echo esc_html( $qtf_user->display_name ); ?></span>
		<?php echo qtf_icon( 'chevron-down', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	</a>
</header>

<div class="qtf-overlay" data-qtf-overlay></div>

<aside id="qtf-sidebar" class="qtf-sidebar" aria-label="<?php esc_attr_e( 'Dashboard menu', 'qoutify-estimator' ); ?>">
	<nav class="qtf-menu">
		<ul>
			<?php foreach ( qtf_menu_items() as $qtf_key => $qtf_item ) : ?>
				<?php $qtf_active = ( $qtf_key === $qtf_view ); ?>
				<li<?php echo $qtf_active ? ' class="is-active"' : ''; ?>>
					<a href="<?php echo esc_url( $qtf_item['url'] ); ?>"<?php echo $qtf_active ? ' aria-current="page"' : ''; ?>>
						<?php echo qtf_icon( $qtf_item['icon'], 22 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<span class="qtf-menu__label"><?php echo esc_html( $qtf_item['label'] ); ?></span>
						<?php if ( ! empty( $qtf_item['badge'] ) ) : ?>
							<span class="qtf-menu__badge"><?php echo esc_html( $qtf_item['badge'] ); ?></span>
						<?php endif; ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
		<ul class="qtf-menu__bottom">
			<li>
				<a href="<?php echo esc_url( wp_logout_url( qtf_account_url() ) ); ?>">
					<?php echo qtf_icon( 'logout', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<span class="qtf-menu__label"><?php esc_html_e( 'Log out', 'qoutify-estimator' ); ?></span>
				</a>
			</li>
		</ul>
	</nav>
	<div class="qtf-sidebar__foot" aria-hidden="false">
		<svg class="qtf-skyline" viewBox="0 0 240 90" aria-hidden="true" focusable="false">
			<g fill="#16365A">
				<rect x="8" y="50" width="22" height="40"/><rect x="34" y="34" width="26" height="56"/><rect x="64" y="14" width="34" height="76"/>
				<rect x="102" y="40" width="24" height="50"/><rect x="130" y="26" width="30" height="64"/><rect x="164" y="48" width="22" height="42"/><rect x="190" y="36" width="26" height="54"/>
			</g>
			<path d="M0 89.5h240" stroke="#2C4C73" stroke-width="1"/>
		</svg>
		<p class="qtf-sidebar__tag"><?php esc_html_e( 'Smarter Estimates,', 'qoutify-estimator' ); ?><br><?php esc_html_e( 'Better Decisions.', 'qoutify-estimator' ); ?></p>
		<span class="qtf-sidebar__line"></span>
	</div>
</aside>

<main class="qtf-main" id="qtf-main" tabindex="-1">
	<?php qtf_render_view( $qtf_view ); ?>
</main>

<?php wp_footer(); ?>
</body>
</html>
