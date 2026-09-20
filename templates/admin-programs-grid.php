<?php
/**
 * Program cards on the LCCL Programs hub.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="lccl-prog__grid">
	<a class="lccl-prog__card" href="<?php echo esc_url( LCCL_DE_Admin_Programs::blood_url() ); ?>" data-program="<?php echo esc_attr( LCCL_DE_Admin_Programs::PROGRAM_BLOOD ); ?>">
		<span class="lccl-prog__card-kicker"><?php esc_html_e( 'Program', 'lccl-de' ); ?></span>
		<span class="lccl-prog__card-title"><?php esc_html_e( 'LCCL Blood Donation', 'lccl-de' ); ?></span>
		<span class="lccl-prog__card-text"><?php esc_html_e( 'Donor alerts and the public registration dashboard.', 'lccl-de' ); ?></span>
		<span class="lccl-prog__card-go"><?php esc_html_e( 'Open', 'lccl-de' ); ?></span>
	</a>
	<a class="lccl-prog__card" href="<?php echo esc_url( LCCL_DE_Admin_Programs::projects_url() ); ?>" data-program="<?php echo esc_attr( LCCL_DE_Admin_Programs::PROGRAM_PROJECTS ); ?>">
		<span class="lccl-prog__card-kicker"><?php esc_html_e( 'Program', 'lccl-de' ); ?></span>
		<span class="lccl-prog__card-title"><?php esc_html_e( 'Join Our Projects', 'lccl-de' ); ?></span>
		<span class="lccl-prog__card-text"><?php esc_html_e( 'Public sign-up form and the registrations dashboard.', 'lccl-de' ); ?></span>
		<span class="lccl-prog__card-go"><?php esc_html_e( 'Open', 'lccl-de' ); ?></span>
	</a>
	<a class="lccl-prog__card" href="<?php echo esc_url( LCCL_DE_Admin_Programs::spectacles_url() ); ?>" data-program="<?php echo esc_attr( LCCL_DE_Admin_Programs::PROGRAM_SPECTACLES ); ?>">
		<span class="lccl-prog__card-kicker"><?php esc_html_e( 'Program', 'lccl-de' ); ?></span>
		<span class="lccl-prog__card-title"><?php esc_html_e( 'Free Spectacles', 'lccl-de' ); ?></span>
		<span class="lccl-prog__card-text"><?php esc_html_e( 'Child registrations and the spectacles review dashboard.', 'lccl-de' ); ?></span>
		<span class="lccl-prog__card-go"><?php esc_html_e( 'Open', 'lccl-de' ); ?></span>
	</a>
</div>
