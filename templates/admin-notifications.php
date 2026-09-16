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
				<th scope="row"><?php esc_html_e( 'Admin email addresses', 'lccl-de' ); ?></th>
				<td>
					<div id="lccl-de-admin-emails">
						<?php
						$emails = ! empty( $settings['admin_addresses'] ) && is_array( $settings['admin_addresses'] )
							? $settings['admin_addresses']
							: array( '' );
						foreach ( $emails as $index => $email ) :
							?>
							<p class="lccl-de-admin-email-row" style="display:flex;gap:8px;align-items:center;margin:0 0 8px;">
								<input
									type="email"
									class="regular-text"
									name="admin_addresses[]"
									value="<?php echo esc_attr( $email ); ?>"
									<?php echo 0 === $index ? 'id="lccl-de-admin-address"' : ''; ?>
									autocomplete="email"
								>
								<button type="button" class="button" data-remove-admin-email><?php esc_html_e( 'Remove', 'lccl-de' ); ?></button>
							</p>
						<?php endforeach; ?>
					</div>
					<p style="margin:8px 0 0;">
						<button type="button" class="button" id="lccl-de-add-admin-email"><?php esc_html_e( 'Add email', 'lccl-de' ); ?></button>
					</p>
					<p class="description"><?php esc_html_e( 'Each address receives a staff copy when someone registers.', 'lccl-de' ); ?></p>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save notifications', 'lccl-de' ); ?></button>
		</p>
	</form>
</div>
