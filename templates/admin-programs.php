<?php
/**
 * LCCL Programs hub: program cards and reviewer accounts.
 *
 * @package LCCL_Donations_And_Events
 *
 * @var string       $tab     programs|users
 * @var string       $action  list|add|edit
 * @var string       $message Flash code
 * @var string       $error   Flash error
 * @var WP_User|null $edit    User being edited
 */

defined( 'ABSPATH' ) || exit;

$tab = isset( $tab ) && 'users' === $tab ? 'users' : 'programs';
?>
<div class="wrap lccl-prog">
	<header class="lccl-prog__hero lccl-prog__hero--compact">
		<p class="lccl-prog__brand">LCCL</p>
		<h1 class="lccl-prog__title"><?php esc_html_e( 'LCCL Programs', 'lccl-de' ); ?></h1>
		<p class="lccl-prog__lede">
			<?php esc_html_e( 'Choose a program to manage notifications, or open User Management to add reviewer accounts. Reviewers can open every program dashboard and cannot reach wp-admin.', 'lccl-de' ); ?>
		</p>
	</header>

	<nav class="lccl-prog__tabs" role="tablist" data-lccl-tabs aria-label="<?php esc_attr_e( 'LCCL Programs', 'lccl-de' ); ?>">
		<a
			class="lccl-prog__tab<?php echo 'programs' === $tab ? ' is-current' : ''; ?>"
			id="lccl-prog-tab-programs"
			href="<?php echo esc_url( LCCL_DE_Admin_Programs::url() ); ?>"
			role="tab"
			data-tab="programs"
			aria-selected="<?php echo 'programs' === $tab ? 'true' : 'false'; ?>"
			aria-controls="lccl-prog-tab-panel"
		>
			<?php esc_html_e( 'Programs', 'lccl-de' ); ?>
		</a>
		<a
			class="lccl-prog__tab<?php echo 'users' === $tab ? ' is-current' : ''; ?>"
			id="lccl-prog-tab-users"
			href="<?php echo esc_url( LCCL_DE_Admin_Programs::users_url() ); ?>"
			role="tab"
			data-tab="users"
			aria-selected="<?php echo 'users' === $tab ? 'true' : 'false'; ?>"
			aria-controls="lccl-prog-tab-panel"
		>
			<?php esc_html_e( 'User Management', 'lccl-de' ); ?>
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
			<?php if ( 'users' === $tab ) : ?>
				<?php include LCCL_DE_PATH . 'templates/admin-users.php'; ?>
			<?php else : ?>
				<?php include LCCL_DE_PATH . 'templates/admin-programs-grid.php'; ?>
			<?php endif; ?>
		</div>
	</div>
</div>
