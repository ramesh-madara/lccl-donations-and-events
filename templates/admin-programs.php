<?php
/**
 * LCCL Programs hub.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap lccl-prog">
	<header class="lccl-prog__hero">
		<p class="lccl-prog__brand">LCCL</p>
		<h1 class="lccl-prog__title"><?php esc_html_e( 'LCCL Programs', 'lccl-de' ); ?></h1>
		<p class="lccl-prog__lede"><?php esc_html_e( 'Choose a programme to manage people, notifications, and related settings.', 'lccl-de' ); ?></p>
	</header>

	<div class="lccl-prog__grid">
		<a class="lccl-prog__card" href="<?php echo esc_url( LCCL_DE_Admin_Programs::blood_url() ); ?>">
			<span class="lccl-prog__card-kicker"><?php esc_html_e( 'Programme', 'lccl-de' ); ?></span>
			<span class="lccl-prog__card-title"><?php esc_html_e( 'LCCL Blood Donation', 'lccl-de' ); ?></span>
			<span class="lccl-prog__card-text"><?php esc_html_e( 'Reviewer accounts, donor alerts, and the public registration dashboard.', 'lccl-de' ); ?></span>
			<span class="lccl-prog__card-go"><?php esc_html_e( 'Open', 'lccl-de' ); ?></span>
		</a>
	</div>
</div>
