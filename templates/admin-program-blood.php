<?php
/**
 * Blood donation workspace: users and notifications tabs.
 *
 * @package LCCL_Donations_And_Events
 *
 * @var string        $tab      users|notifications
 * @var string        $action   list|add|edit
 * @var string        $message  Flash code
 * @var string        $error    Flash error
 * @var WP_User|null  $edit     User being edited
 * @var array         $settings Notification options
 * @var array         $log      Send log
 * @var bool          $smtp     Whether WP Mail SMTP is present
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap lccl-prog">
	<p class="lccl-prog__back">
		<a href="<?php echo esc_url( LCCL_DE_Admin_Programs::url() ); ?>"><?php esc_html_e( 'LCCL Programs', 'lccl-de' ); ?></a>
	</p>

	<header class="lccl-prog__hero lccl-prog__hero--compact">
		<div class="lccl-prog__hero-copy">
			<p class="lccl-prog__brand">LCCL</p>
			<h1 class="lccl-prog__title"><?php esc_html_e( 'LCCL Blood Donation', 'lccl-de' ); ?></h1>
			<p class="lccl-prog__lede">
				<?php esc_html_e( 'Manage who can review registrations, and which emails and SMS go out after someone signs up.', 'lccl-de' ); ?>
			</p>
		</div>
		<a class="lccl-prog__dash-btn" href="<?php echo esc_url( LCCL_DE_Roles::dashboard_url() ); ?>" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'Open dashboard', 'lccl-de' ); ?>
			<span class="lccl-prog__dash-btn-icon" aria-hidden="true">
				<svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M3 9.5 9.5 3M9.5 3H4.5M9.5 3v5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
			</span>
		</a>
	</header>

	<nav class="lccl-prog__tabs" role="tablist" data-lccl-tabs aria-label="<?php esc_attr_e( 'Blood donation settings', 'lccl-de' ); ?>">
		<a
			class="lccl-prog__tab<?php echo 'users' === $tab ? ' is-current' : ''; ?>"
			id="lccl-prog-tab-users"
			href="<?php echo esc_url( LCCL_DE_Admin_Programs::blood_url( array( 'tab' => 'users' ) ) ); ?>"
			role="tab"
			data-tab="users"
			aria-selected="<?php echo 'users' === $tab ? 'true' : 'false'; ?>"
			aria-controls="lccl-prog-tab-panel"
		>
			<?php esc_html_e( 'Users', 'lccl-de' ); ?>
		</a>
		<a
			class="lccl-prog__tab<?php echo 'notifications' === $tab ? ' is-current' : ''; ?>"
			id="lccl-prog-tab-notifications"
			href="<?php echo esc_url( LCCL_DE_Admin_Programs::blood_url( array( 'tab' => 'notifications' ) ) ); ?>"
			role="tab"
			data-tab="notifications"
			aria-selected="<?php echo 'notifications' === $tab ? 'true' : 'false'; ?>"
			aria-controls="lccl-prog-tab-panel"
		>
			<?php esc_html_e( 'Notifications', 'lccl-de' ); ?>
		</a>
	</nav>

	<div class="lccl-prog__stage">
		<div
			class="lccl-prog__panel"
			id="lccl-prog-tab-panel"
			role="tabpanel"
			data-lccl-tab-panel
			aria-labelledby="<?php echo 'notifications' === $tab ? 'lccl-prog-tab-notifications' : 'lccl-prog-tab-users'; ?>"
		>
			<?php
			if ( 'notifications' === $tab ) {
				include LCCL_DE_PATH . 'templates/admin-notifications.php';
			} else {
				include LCCL_DE_PATH . 'templates/admin-users.php';
			}
			?>
		</div>
		<div class="lccl-prog__panel-loader" data-lccl-tab-loader hidden>
			<span class="lccl-prog__loader" aria-hidden="true"></span>
			<span class="screen-reader-text"><?php esc_html_e( 'Loading', 'lccl-de' ); ?></span>
		</div>
	</div>
</div>
