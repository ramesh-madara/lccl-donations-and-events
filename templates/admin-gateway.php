<?php
/**
 * Payment Gateway settings tab — MPGS credentials.
 *
 * Included by templates/admin-programs.php when the 'gateway' tab is active.
 *
 * @package LCCL_Donations_And_Events
 *
 * @var string $tab     Current admin tab.
 * @var string $message Flash message code.
 * @var string $error   Flash error string.
 */

defined( 'ABSPATH' ) || exit;

$cfg     = LCCL_DE_Settings::get_mpgs();
$message = isset( $message ) ? $message : '';
$error   = isset( $error ) ? $error : '';
?>

<div class="lccl-prog__gateway">

	<?php if ( 'saved' === $message ) : ?>
		<div class="notice notice-success is-dismissible lccl-prog__flash">
			<p><?php esc_html_e( 'Payment gateway settings saved.', 'lccl-de' ); ?></p>
		</div>
	<?php elseif ( $error ) : ?>
		<div class="notice notice-error is-dismissible lccl-prog__flash">
			<p><?php echo esc_html( $error ); ?></p>
		</div>
	<?php endif; ?>

	<div class="lccl-prog__section-header">
		<h2 class="lccl-prog__section-title">
			<?php esc_html_e( 'Mastercard Payment Gateway (MPGS)', 'lccl-de' ); ?>
		</h2>
		<p class="lccl-prog__section-desc">
			<?php esc_html_e( 'Enter your MPGS Hosted Checkout credentials provided by your bank. The API Password is stored encrypted. Leave it blank to keep the current value.', 'lccl-de' ); ?>
		</p>
	</div>

	<?php if ( LCCL_DE_Settings::mpgs_is_configured() ) : ?>
		<div class="lccl-prog__gateway-badge lccl-prog__gateway-badge--ok">
			<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
			<?php esc_html_e( 'Gateway configured and active', 'lccl-de' ); ?>
		</div>
	<?php else : ?>
		<div class="lccl-prog__gateway-badge lccl-prog__gateway-badge--warn">
			<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
			<?php esc_html_e( 'Gateway not yet configured — payment form is disabled', 'lccl-de' ); ?>
		</div>
	<?php endif; ?>

	<form method="post" action="" class="lccl-prog__gateway-form">
		<?php wp_nonce_field( 'lccl_de_mpgs_save', 'lccl_de_mpgs_nonce' ); ?>
		<input type="hidden" name="lccl_de_mpgs_save" value="1">

		<table class="form-table lccl-prog__form-table" role="presentation">
			<tbody>

				<tr>
					<th scope="row">
						<label for="lccl-gw-url"><?php esc_html_e( 'Gateway URL', 'lccl-de' ); ?> <span class="lccl-prog__req">*</span></label>
					</th>
					<td>
						<input
							id="lccl-gw-url"
							name="gateway_url"
							type="url"
							class="regular-text"
							value="<?php echo esc_attr( $cfg['gateway_url'] ); ?>"
							placeholder="https://ap-gateway.mastercard.com/"
							required
						>
						<p class="description"><?php esc_html_e( 'Provided by your bank. Must end with a trailing slash.', 'lccl-de' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="lccl-gw-version"><?php esc_html_e( 'API Version', 'lccl-de' ); ?></label>
					</th>
					<td>
						<input
							id="lccl-gw-version"
							name="api_version"
							type="number"
							class="small-text"
							value="<?php echo esc_attr( (string) $cfg['api_version'] ); ?>"
							min="63"
							max="99"
						>
						<p class="description">
							<?php
							printf(
								/* translators: %d: recommended version */
								esc_html__( 'Recommended: %d or higher (modern Hosted Checkout). Must be 63+ to use showPaymentPage().', 'lccl-de' ),
								LCCL_DE_MPGS_Client::DEFAULT_API_VERSION
							);
							?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="lccl-gw-merchant"><?php esc_html_e( 'Merchant ID', 'lccl-de' ); ?> <span class="lccl-prog__req">*</span></label>
					</th>
					<td>
						<input
							id="lccl-gw-merchant"
							name="merchant_id"
							type="text"
							class="regular-text"
							value="<?php echo esc_attr( $cfg['merchant_id'] ); ?>"
							placeholder="<?php esc_attr_e( 'TEST123456789', 'lccl-de' ); ?>"
							autocomplete="off"
							required
						>
						<p class="description"><?php esc_html_e( 'Your unique Merchant ID from your bank.', 'lccl-de' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="lccl-gw-password"><?php esc_html_e( 'API Password', 'lccl-de' ); ?></label>
					</th>
					<td>
						<input
							id="lccl-gw-password"
							name="api_password"
							type="password"
							class="regular-text"
							value=""
							placeholder="<?php echo '' !== $cfg['api_password'] ? esc_attr__( '(saved — leave blank to keep)', 'lccl-de' ) : esc_attr__( 'Enter API password', 'lccl-de' ); ?>"
							autocomplete="new-password"
						>
						<p class="description">
							<?php
							if ( '' !== $cfg['api_password'] ) {
								esc_html_e( 'A password is saved. Leave blank to keep the current password, or enter a new value to update it. Stored encrypted with AES-256-GCM.', 'lccl-de' );
							} else {
								esc_html_e( 'Your API password from the MPGS Merchant Administration portal. Stored encrypted with AES-256-GCM.', 'lccl-de' );
							}
							?>
						</p>
					</td>
				</tr>

			</tbody>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary">
				<?php esc_html_e( 'Save Payment Gateway Settings', 'lccl-de' ); ?>
			</button>
		</p>
	</form>

	<hr class="lccl-prog__divider">

	<div class="lccl-prog__section-header">
		<h3 class="lccl-prog__section-title lccl-prog__section-title--sm">
			<?php esc_html_e( 'Integration Notes', 'lccl-de' ); ?>
		</h3>
	</div>

	<ul class="lccl-prog__gateway-notes">
		<li>
			<?php
			printf(
				/* translators: URL */
				wp_kses(
					__( 'Whitelist the <strong>return URL</strong> in your MPGS Merchant Administration portal under <em>Admin → Integration Settings → Hosted Checkout → Return URL</em>. The URL pattern is: <code>%s</code>', 'lccl-de' ),
					array( 'strong' => array(), 'em' => array(), 'code' => array() )
				),
				esc_html( add_query_arg( array( LCCL_DE_Membership_Form::QA_RETURN => '1', LCCL_DE_Membership_Form::QA_ORDER_REF => 'ORDER_REF' ), home_url( '/' ) ) )
			);
			?>
		</li>
		<li>
			<?php esc_html_e( 'Use MPGS test cards to verify the integration before going live. Do not use real card numbers in testing.', 'lccl-de' ); ?>
		</li>
		<li>
			<?php esc_html_e( 'All payment transactions are logged in the lccl_de_payments database table for audit purposes.', 'lccl-de' ); ?>
		</li>
	</ul>

</div>
