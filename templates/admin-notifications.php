<?php
/**
 * wp-admin notification switches.
 *
 * @package LCCL_Donations_And_Events
 *
 * @var array  $settings Current option values.
 * @var string $message  Flash code.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Blood donation notifications', 'lccl-de' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'These go out after someone submits the public registration form. Uncheck a row to stop that message. Saving a registration is never blocked if a send fails.', 'lccl-de' ); ?>
	</p>

	<?php if ( 'saved' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Notification settings saved.', 'lccl-de' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . LCCL_DE_Settings::PAGE ) ); ?>" style="max-width: 640px;">
		<?php wp_nonce_field( LCCL_DE_Settings::NONCE ); ?>
		<input type="hidden" name="lccl_de_notify_save" value="1">

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Donor SMS', 'lccl-de' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="donor_sms" value="1" <?php checked( ! empty( $settings['donor_sms'] ) ); ?>>
						<?php esc_html_e( 'Send an SMS to the person who registered (Dialog e-SMS).', 'lccl-de' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Donor email', 'lccl-de' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="donor_email" value="1" <?php checked( ! empty( $settings['donor_email'] ) ); ?>>
						<?php esc_html_e( 'Email the person who registered, if they entered an email address.', 'lccl-de' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Admin email', 'lccl-de' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="admin_email" value="1" <?php checked( ! empty( $settings['admin_email'] ) ); ?>>
						<?php esc_html_e( 'Email a staff copy when someone registers.', 'lccl-de' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="lccl-de-admin-address"><?php esc_html_e( 'Admin email address', 'lccl-de' ); ?></label></th>
				<td>
					<input type="email" class="regular-text" id="lccl-de-admin-address" name="admin_address" value="<?php echo esc_attr( $settings['admin_address'] ); ?>" required>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save notifications', 'lccl-de' ); ?></button>
		</p>
	</form>
</div>
