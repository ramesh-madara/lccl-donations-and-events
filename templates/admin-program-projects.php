<?php
/**
 * Join Our Projects workspace.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap lccl-prog">
	<h1 class="screen-reader-text"><?php esc_html_e( 'Join Our Projects', 'lccl-de' ); ?></h1>

	<nav class="lccl-prog__crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'lccl-de' ); ?>">
		<a href="<?php echo esc_url( LCCL_DE_Admin_Programs::url() ); ?>"><?php esc_html_e( 'LCCL Programs', 'lccl-de' ); ?></a>
		<span class="lccl-prog__crumbs-sep" aria-hidden="true">/</span>
		<span class="lccl-prog__crumbs-current"><?php esc_html_e( 'Join Our Projects', 'lccl-de' ); ?></span>
	</nav>

	<header class="lccl-prog__hero lccl-prog__hero--compact">
		<p class="lccl-prog__lede">
			<?php esc_html_e( 'Choose which emails and SMS go out after someone registers their interest. Place the [lccl_join_our_projects] shortcode on any page to show the registration form.', 'lccl-de' ); ?>
		</p>
		<div class="lccl-prog__dash-row">
			<a class="lccl-prog__dash-btn" href="<?php echo esc_url( LCCL_DE_Roles::projects_dashboard_url() ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Open dashboard', 'lccl-de' ); ?>
				<span class="lccl-prog__dash-btn-icon" aria-hidden="true">
					<svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M3 9.5 9.5 3M9.5 3H4.5M9.5 3v5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</span>
			</a>
		</div>
	</header>

	<div class="lccl-prog__panel lccl-prog__panel--solo">
		<?php include LCCL_DE_PATH . 'templates/admin-notifications.php'; ?>
	</div>
</div>
