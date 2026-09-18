<?php
/**
 * Hub-level reviewer accounts.
 *
 * @package LCCL_Donations_And_Events
 *
 * @var string        $action  list|add|edit
 * @var string        $message Flash code
 * @var string        $error   Flash error
 * @var WP_User|null  $edit    User being edited
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap lccl-prog">
	<h1 class="screen-reader-text"><?php esc_html_e( 'Reviewers', 'lccl-de' ); ?></h1>

	<nav class="lccl-prog__crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'lccl-de' ); ?>">
		<a href="<?php echo esc_url( LCCL_DE_Admin_Programs::url() ); ?>"><?php esc_html_e( 'LCCL Programs', 'lccl-de' ); ?></a>
		<span class="lccl-prog__crumbs-sep" aria-hidden="true">/</span>
		<span class="lccl-prog__crumbs-current"><?php esc_html_e( 'Reviewers', 'lccl-de' ); ?></span>
	</nav>

	<header class="lccl-prog__hero lccl-prog__hero--compact">
		<p class="lccl-prog__lede">
			<?php esc_html_e( 'These accounts can open every program dashboard on the site. They cannot reach wp-admin.', 'lccl-de' ); ?>
		</p>
	</header>

	<div class="lccl-prog__panel lccl-prog__panel--solo">
		<?php include LCCL_DE_PATH . 'templates/admin-users.php'; ?>
	</div>
</div>
