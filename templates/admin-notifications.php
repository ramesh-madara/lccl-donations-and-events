<?php
/**
 * Programme notification switches (tab content).
 *
 * @package LCCL_Donations_And_Events
 *
 * @var array  $settings      Current option values.
 * @var string $message       Flash code.
 * @var string $error         Flash error.
 * @var array  $log           Send log.
 * @var bool   $smtp          Whether WP Mail SMTP is present.
 * @var string $program       blood-donation|our-projects.
 * @var string $notify_action Form action URL.
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $program ) ) {
	$program = LCCL_DE_Admin_Programs::PROGRAM_BLOOD;
}

if ( empty( $notify_action ) ) {
	$notify_action = LCCL_DE_Admin_Programs::notify_url( $program );
}

$is_projects   = LCCL_DE_Admin_Programs::PROGRAM_PROJECTS === $program;
$is_spectacles = LCCL_DE_Admin_Programs::PROGRAM_SPECTACLES === $program;
?>
<?php if ( 'saved' === $message ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Notification settings saved.', 'lccl-de' ); ?></p></div>
<?php elseif ( 'test-ok' === $message ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Test email was accepted by WordPress. Check the inbox (and spam).', 'lccl-de' ); ?></p></div>
<?php elseif ( 'test-fail' === $message ) : ?>
	<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Test email was not sent. See the log below for the server error.', 'lccl-de' ); ?></p></div>
<?php elseif ( 'error' === $message && ! empty( $error ) ) : ?>
	<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
<?php endif; ?>

<div class="lccl-prog__section">
	<p class="lccl-prog__hint">
		<?php esc_html_e( 'These go out after someone submits the public registration form. Uncheck a row to stop that message. Saving a registration is never blocked if a send fails.', 'lccl-de' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( $notify_action ); ?>" data-lccl-notify-form novalidate>
		<?php wp_nonce_field( LCCL_DE_Settings::NONCE ); ?>
		<input type="hidden" name="lccl_de_notify_save" value="1">
		<input type="hidden" name="lccl_de_notify_program" value="<?php echo esc_attr( $program ); ?>">

		<label class="lccl-prog__check">
			<input type="checkbox" name="donor_sms" value="1" <?php checked( ! empty( $settings['donor_sms'] ) ); ?>>
			<span><?php esc_html_e( 'Send an SMS to the person who registered (Dialog e-SMS).', 'lccl-de' ); ?></span>
		</label>
		<p class="lccl-prog__hint">
			<?php
			if ( ! empty( $settings['sms_ready'] ) ) {
				esc_html_e( 'Uses the shared Dialog login on the SMS tab.', 'lccl-de' );
			} else {
				esc_html_e( 'SMS cannot be sent until the shared Dialog username and password are saved on the SMS tab.', 'lccl-de' );
			}
			?>
			<a href="<?php echo esc_url( LCCL_DE_Admin_Programs::sms_url() ); ?>" data-tab="sms"><?php esc_html_e( 'Open SMS', 'lccl-de' ); ?></a>
		</p>
		<label class="lccl-prog__check">
			<input type="checkbox" name="donor_email" value="1" <?php checked( ! empty( $settings['donor_email'] ) ); ?>>
			<span><?php esc_html_e( 'Email the person who registered, if they entered an email address.', 'lccl-de' ); ?></span>
		</label>
		<label class="lccl-prog__check">
			<input type="checkbox" name="admin_email" value="1" <?php checked( ! empty( $settings['admin_email'] ) ); ?>>
			<span><?php esc_html_e( 'Email a staff copy when someone registers.', 'lccl-de' ); ?></span>
		</label>

		<p>
			<label for="lccl-de-admin-address"><strong><?php esc_html_e( 'Staff email addresses', 'lccl-de' ); ?></strong></label>
		</p>
		<div id="lccl-de-admin-emails">
			<?php
			$emails = ! empty( $settings['admin_addresses'] ) && is_array( $settings['admin_addresses'] )
				? $settings['admin_addresses']
				: array( '' );
			foreach ( $emails as $index => $email ) :
				?>
				<p class="lccl-de-admin-email-row">
					<input
						type="email"
						name="admin_addresses[]"
						value="<?php echo esc_attr( $email ); ?>"
						<?php echo 0 === $index ? 'id="lccl-de-admin-address"' : ''; ?>
						autocomplete="email"
					>
					<button type="button" class="button" data-remove-admin-email><?php esc_html_e( 'Remove', 'lccl-de' ); ?></button>
				</p>
			<?php endforeach; ?>
		</div>
		<p>
			<button type="button" class="button" id="lccl-de-add-admin-email"><?php esc_html_e( 'Add email', 'lccl-de' ); ?></button>
		</p>
		<p class="lccl-prog__hint"><?php esc_html_e( 'Each address receives a staff copy when someone registers.', 'lccl-de' ); ?></p>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save notifications', 'lccl-de' ); ?></button>
		</p>
	</form>
</div>

<div class="lccl-prog__section">
	<h2><?php esc_html_e( 'Send a test email', 'lccl-de' ); ?></h2>
	<p class="lccl-prog__hint">
		<?php
		if ( $is_projects || $is_spectacles ) {
			esc_html_e( 'Sends the live registrant and/or staff templates with sample registration data, using the same wp_mail path as a real signup.', 'lccl-de' );
		} else {
			esc_html_e( 'Sends the live donor and/or staff templates with sample registration data, using the same wp_mail path as a real signup.', 'lccl-de' );
		}
		?>
	</p>

	<?php if ( ! empty( $smtp ) ) : ?>
		<p class="lccl-prog__hint">
			<?php esc_html_e( 'WP Mail SMTP is active. Mail goes through that plugin. Confirm the From mailbox matches the SMTP login if a test does not arrive.', 'lccl-de' ); ?>
		</p>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( $notify_action ); ?>" data-lccl-test-email-form novalidate>
		<?php wp_nonce_field( LCCL_DE_Settings::NONCE ); ?>
		<input type="hidden" name="lccl_de_notify_test" value="1">
		<input type="hidden" name="lccl_de_notify_program" value="<?php echo esc_attr( $program ); ?>">
		<p>
			<label for="lccl-de-test-email"><?php esc_html_e( 'Send test to', 'lccl-de' ); ?></label><br>
			<input
				type="email"
				id="lccl-de-test-email"
				name="test_email"
				value="<?php echo esc_attr( isset( $settings['admin_addresses'][0] ) ? $settings['admin_addresses'][0] : '' ); ?>"
				required
			>
		</p>
		<p>
			<strong><?php esc_html_e( 'Template', 'lccl-de' ); ?></strong>
		</p>
		<label class="lccl-prog__check">
			<input type="radio" name="test_kind" value="donor">
			<span><?php echo ( $is_projects || $is_spectacles ) ? esc_html__( 'Registrant confirmation email', 'lccl-de' ) : esc_html__( 'Donor confirmation email', 'lccl-de' ); ?></span>
		</label>
		<label class="lccl-prog__check">
			<input type="radio" name="test_kind" value="staff">
			<span><?php esc_html_e( 'Staff notification email', 'lccl-de' ); ?></span>
		</label>
		<label class="lccl-prog__check">
			<input type="radio" name="test_kind" value="both" checked>
			<span><?php esc_html_e( 'Both templates', 'lccl-de' ); ?></span>
		</label>
		<p>
			<button type="submit" class="button"><?php esc_html_e( 'Send test email', 'lccl-de' ); ?></button>
		</p>
	</form>
</div>

<div class="lccl-prog__section">
	<h2><?php esc_html_e( 'Recent send log', 'lccl-de' ); ?></h2>
	<p class="lccl-prog__hint">
		<?php esc_html_e( 'Newest first for this programme. A registration that shows a thank-you still writes a row here even if mail or SMS failed.', 'lccl-de' ); ?>
	</p>
	<?php if ( empty( $log ) ) : ?>
		<p><?php esc_html_e( 'No sends recorded yet. Submit a test or a registration on this server.', 'lccl-de' ); ?></p>
	<?php else : ?>
		<table class="lccl-prog__table lccl-prog__log">
			<thead>
				<tr>
					<th><?php esc_html_e( 'When', 'lccl-de' ); ?></th>
					<th><?php esc_html_e( 'Type', 'lccl-de' ); ?></th>
					<th><?php esc_html_e( 'Result', 'lccl-de' ); ?></th>
					<th><?php esc_html_e( 'To', 'lccl-de' ); ?></th>
					<th><?php esc_html_e( 'Detail', 'lccl-de' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $log as $row ) : ?>
					<tr>
						<td><?php echo esc_html( ! empty( $row['at'] ) ? wp_date( 'Y-m-d H:i:s', (int) $row['at'] ) : '—' ); ?></td>
						<td><?php echo esc_html( isset( $row['kind'] ) ? $row['kind'] : '' ); ?></td>
						<td><?php echo ! empty( $row['ok'] ) ? esc_html__( 'Sent', 'lccl-de' ) : esc_html__( 'Failed / skipped', 'lccl-de' ); ?></td>
						<td><?php echo esc_html( isset( $row['to'] ) ? $row['to'] : '' ); ?></td>
						<td><?php echo esc_html( isset( $row['detail'] ) ? $row['detail'] : '' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
