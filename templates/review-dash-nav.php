<?php
/**
 * Shared program switcher for frontend review dashboards.
 *
 * @package LCCL_Donations_And_Events
 *
 * @var string $current_dash blood|projects
 */

defined( 'ABSPATH' ) || exit;

$current_dash = isset( $current_dash ) ? $current_dash : 'blood';
?>
<nav class="lccl-bda__nav" aria-label="<?php esc_attr_e( 'Programs', 'lccl-de' ); ?>">
	<a
		class="lccl-bda__nav-btn<?php echo 'blood' === $current_dash ? ' is-current' : ''; ?>"
		href="<?php echo esc_url( LCCL_DE_Roles::dashboard_url() ); ?>"
		<?php echo 'blood' === $current_dash ? ' aria-current="page"' : ''; ?>
	>
		<?php esc_html_e( 'Blood Donation', 'lccl-de' ); ?>
	</a>
	<a
		class="lccl-bda__nav-btn<?php echo 'projects' === $current_dash ? ' is-current' : ''; ?>"
		href="<?php echo esc_url( LCCL_DE_Roles::projects_dashboard_url() ); ?>"
		<?php echo 'projects' === $current_dash ? ' aria-current="page"' : ''; ?>
	>
		<?php esc_html_e( 'Join Our Projects', 'lccl-de' ); ?>
	</a>
</nav>
