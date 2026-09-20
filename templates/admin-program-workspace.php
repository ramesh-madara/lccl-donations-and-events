<?php
/**
 * Programme workspace inside the Programs tab.
 *
 * @package LCCL_Donations_And_Events
 *
 * @var string $program Programme key.
 */

defined( 'ABSPATH' ) || exit;

$program = isset( $program ) ? LCCL_DE_Admin_Programs::normalize_program_key( $program ) : '';
if ( '' === $program ) {
	$program = LCCL_DE_Admin_Programs::PROGRAM_BLOOD;
}

$label = LCCL_DE_Admin_Programs::program_label( $program );
?>
<nav class="lccl-prog__crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'lccl-de' ); ?>">
	<a href="<?php echo esc_url( LCCL_DE_Admin_Programs::url() ); ?>" data-tab="programs"><?php esc_html_e( 'Programs', 'lccl-de' ); ?></a>
	<span class="lccl-prog__crumbs-sep" aria-hidden="true">/</span>
	<span class="lccl-prog__crumbs-current"><?php echo esc_html( $label ); ?></span>
</nav>

<header class="lccl-prog__hero lccl-prog__hero--compact">
	<h2 class="lccl-prog__panel-title"><?php echo esc_html( $label ); ?></h2>
	<p class="lccl-prog__lede">
		<?php echo esc_html( LCCL_DE_Admin_Programs::program_lede( $program ) ); ?>
	</p>
	<div class="lccl-prog__dash-row">
		<a class="lccl-prog__dash-btn" href="<?php echo esc_url( LCCL_DE_Admin_Programs::program_dashboard_url( $program ) ); ?>" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'Open dashboard', 'lccl-de' ); ?>
			<span class="lccl-prog__dash-btn-icon" aria-hidden="true">
				<svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M3 9.5 9.5 3M9.5 3H4.5M9.5 3v5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
			</span>
		</a>
	</div>
</header>

<?php include LCCL_DE_PATH . 'templates/admin-notifications.php'; ?>
