<?php
/**
 * LCCL Programs hub: program cards, reviewer accounts, and SMS.
 *
 * @package LCCL_Donations_And_Events
 *
 * @var string       $tab     programs|users|sms
 * @var string       $program blood-donation|our-projects|free-spectacles|''
 * @var string       $action  list|add|edit
 * @var string       $message Flash code
 * @var string       $error   Flash error
 * @var WP_User|null $edit    User being edited
 */

defined( 'ABSPATH' ) || exit;

$tab     = isset( $tab ) ? LCCL_DE_Admin_Programs::normalize_tab( $tab ) : LCCL_DE_Admin_Programs::TAB_PROGRAMS;
$program = isset( $program ) ? LCCL_DE_Admin_Programs::normalize_program_key( $program ) : '';
if ( LCCL_DE_Admin_Programs::TAB_PROGRAMS !== $tab ) {
	$program = '';
}
?>
<div class="wrap lccl-prog">
	<header class="lccl-prog__hero lccl-prog__hero--compact">
		<p class="lccl-prog__brand">LCCL</p>
		<h1 class="lccl-prog__title"><?php esc_html_e( 'LCCL Programs', 'lccl-de' ); ?></h1>
		<p class="lccl-prog__lede">
			<?php esc_html_e( 'Choose a program to manage its notifications, open User Management for reviewer accounts, or set the shared SMS credentials. Reviewers can open every program dashboard and cannot reach wp-admin.', 'lccl-de' ); ?>
		</p>
	</header>

	<nav class="lccl-prog__tabs" role="tablist" data-lccl-tabs aria-label="<?php esc_attr_e( 'LCCL Programs', 'lccl-de' ); ?>">
		<a
			class="lccl-prog__tab<?php echo LCCL_DE_Admin_Programs::TAB_PROGRAMS === $tab ? ' is-current' : ''; ?>"
			id="lccl-prog-tab-programs"
			href="<?php echo esc_url( LCCL_DE_Admin_Programs::url() ); ?>"
			role="tab"
			data-tab="programs"
			aria-selected="<?php echo LCCL_DE_Admin_Programs::TAB_PROGRAMS === $tab ? 'true' : 'false'; ?>"
			aria-controls="lccl-prog-tab-panel"
		>
			<?php esc_html_e( 'Programs', 'lccl-de' ); ?>
		</a>
		<a
			class="lccl-prog__tab<?php echo LCCL_DE_Admin_Programs::TAB_USERS === $tab ? ' is-current' : ''; ?>"
			id="lccl-prog-tab-users"
			href="<?php echo esc_url( LCCL_DE_Admin_Programs::users_url() ); ?>"
			role="tab"
			data-tab="users"
			aria-selected="<?php echo LCCL_DE_Admin_Programs::TAB_USERS === $tab ? 'true' : 'false'; ?>"
			aria-controls="lccl-prog-tab-panel"
		>
			<?php esc_html_e( 'User Management', 'lccl-de' ); ?>
		</a>
		<a
			class="lccl-prog__tab<?php echo LCCL_DE_Admin_Programs::TAB_SMS === $tab ? ' is-current' : ''; ?>"
			id="lccl-prog-tab-sms"
			href="<?php echo esc_url( LCCL_DE_Admin_Programs::sms_url() ); ?>"
			role="tab"
			data-tab="sms"
			aria-selected="<?php echo LCCL_DE_Admin_Programs::TAB_SMS === $tab ? 'true' : 'false'; ?>"
			aria-controls="lccl-prog-tab-panel"
		>
			<?php esc_html_e( 'SMS', 'lccl-de' ); ?>
		</a>
		<a
			class="lccl-prog__tab<?php echo LCCL_DE_Admin_Programs::TAB_GATEWAY === $tab ? ' is-current' : ''; ?>"
			id="lccl-prog-tab-gateway"
			href="<?php echo esc_url( LCCL_DE_Admin_Programs::gateway_url() ); ?>"
			role="tab"
			data-tab="gateway"
			aria-selected="<?php echo LCCL_DE_Admin_Programs::TAB_GATEWAY === $tab ? 'true' : 'false'; ?>"
			aria-controls="lccl-prog-tab-panel"
		>
			<?php esc_html_e( 'Payment Gateway', 'lccl-de' ); ?>
		</a>
	</nav>

	<div class="lccl-prog__stage">
		<div class="lccl-prog__panel-loader" data-lccl-tab-loader hidden>
			<span class="lccl-prog__loader" aria-hidden="true"></span>
			<span class="screen-reader-text"><?php esc_html_e( 'Loading', 'lccl-de' ); ?></span>
		</div>
		<div
			class="lccl-prog__panel"
			id="lccl-prog-tab-panel"
			data-lccl-tab-panel
			role="tabpanel"
			aria-labelledby="lccl-prog-tab-<?php echo esc_attr( $tab ); ?>"
		>
			<?php if ( LCCL_DE_Admin_Programs::TAB_USERS === $tab ) : ?>
				<?php include LCCL_DE_PATH . 'templates/admin-users.php'; ?>
			<?php elseif ( LCCL_DE_Admin_Programs::TAB_SMS === $tab ) : ?>
				<?php include LCCL_DE_PATH . 'templates/admin-sms.php'; ?>
			<?php elseif ( LCCL_DE_Admin_Programs::TAB_GATEWAY === $tab ) : ?>
				<?php include LCCL_DE_PATH . 'templates/admin-gateway.php'; ?>
			<?php elseif ( '' !== $program ) : ?>
				<?php include LCCL_DE_PATH . 'templates/admin-program-workspace.php'; ?>
			<?php else : ?>
				<?php include LCCL_DE_PATH . 'templates/admin-programs-grid.php'; ?>
			<?php endif; ?>
		</div>
	</div>
</div>
