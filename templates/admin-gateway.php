<?php
/**
 * Payment Gateway settings tab — Multi-merchant MPGS credentials.
 *
 * Included by templates/admin-programs.php when the 'gateway' tab is active.
 *
 * @package LCCL_Donations_And_Events
 *
 * @var string $tab      Current admin tab.
 * @var string $subtab   Active gateway subtab.
 * @var array  $profiles Array of all 4 profile configurations.
 * @var string $message  Flash message code.
 * @var string $error    Flash error string.
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $profiles ) || ! is_array( $profiles ) ) {
	$profiles = LCCL_DE_Settings::get_all_mpgs_profiles();
}

if ( empty( $subtab ) ) {
	$subtab = isset( $_GET['subtab'] ) ? sanitize_key( wp_unslash( $_GET['subtab'] ) ) : LCCL_DE_Settings::PROFILE_DONATIONS; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$subtab = LCCL_DE_Settings::normalize_mpgs_profile( $subtab );
}

$message = isset( $message ) ? $message : '';
$error   = isset( $error ) ? $error : '';
?>

<div class="lccl-prog__gateway" data-lccl-gateway-root>

	<?php if ( 'saved' === $message ) : ?>
		<div class="notice notice-success is-dismissible lccl-prog__flash">
			<p><?php esc_html_e( 'Payment gateway settings saved successfully.', 'lccl-de' ); ?></p>
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
			<?php esc_html_e( 'Configure dedicated merchant accounts for different payment streams. Switch between the accounts below to manage credentials.', 'lccl-de' ); ?>
		</p>
	</div>

	<!-- Subtab Navigation -->
	<div class="lccl-gw-subnav" role="tablist" aria-label="<?php esc_attr_e( 'Merchant Accounts', 'lccl-de' ); ?>">
		<?php foreach ( $profiles as $p_key => $p_data ) : ?>
			<?php
			$is_active     = ( $p_key === $subtab );
			$is_configured = ! empty( $p_data['is_configured'] );
			$subtab_url    = LCCL_DE_Admin_Programs::gateway_url( array( 'subtab' => $p_key ) );
			?>
			<a
				href="<?php echo esc_url( $subtab_url ); ?>"
				class="lccl-gw-subnav__item<?php echo $is_active ? ' is-active' : ''; ?>"
				role="tab"
				id="lccl-gw-tab-<?php echo esc_attr( $p_key ); ?>"
				aria-controls="lccl-gw-panel-<?php echo esc_attr( $p_key ); ?>"
				aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
				data-gw-subtab-link="<?php echo esc_attr( $p_key ); ?>"
			>
				<span
					class="lccl-gw-subnav__dot <?php echo $is_configured ? 'lccl-gw-subnav__dot--ok' : 'lccl-gw-subnav__dot--empty'; ?>"
					title="<?php echo $is_configured ? esc_attr__( 'Configured and ready', 'lccl-de' ) : esc_attr__( 'Not yet configured', 'lccl-de' ); ?>"
					aria-hidden="true"
				></span>
				<span class="lccl-gw-subnav__label"><?php echo esc_html( $p_data['label'] ); ?></span>
				<?php if ( LCCL_DE_Settings::PROFILE_DONATIONS === $p_key ) : ?>
					<span class="lccl-gw-subnav__badge"><?php esc_html_e( 'Donations', 'lccl-de' ); ?></span>
				<?php elseif ( LCCL_DE_Settings::PROFILE_MEMBERSHIP === $p_key ) : ?>
					<span class="lccl-gw-subnav__badge"><?php esc_html_e( 'Members', 'lccl-de' ); ?></span>
				<?php endif; ?>
			</a>
		<?php endforeach; ?>
	</div>

	<!-- Subtab Panels -->
	<div class="lccl-gw-panels">
		<?php foreach ( $profiles as $p_key => $p_data ) : ?>
			<?php
			$is_active     = ( $p_key === $subtab );
			$is_configured = ! empty( $p_data['is_configured'] );
			$cfg           = $p_data['config'];
			?>
			<div
				class="lccl-gw-panel<?php echo $is_active ? ' is-active' : ''; ?>"
				id="lccl-gw-panel-<?php echo esc_attr( $p_key ); ?>"
				role="tabpanel"
				aria-labelledby="lccl-gw-tab-<?php echo esc_attr( $p_key ); ?>"
				data-gw-panel="<?php echo esc_attr( $p_key ); ?>"
				<?php echo $is_active ? '' : 'hidden'; ?>
			>
				<div class="lccl-gw-panel__header">
					<div class="lccl-gw-panel__title-wrap">
						<h3 class="lccl-gw-panel__title"><?php echo esc_html( $p_data['label'] ); ?></h3>
						<p class="lccl-gw-panel__desc"><?php echo esc_html( $p_data['description'] ); ?></p>
					</div>

					<?php if ( $is_configured ) : ?>
						<div class="lccl-prog__gateway-badge lccl-prog__gateway-badge--ok">
							<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
							<?php esc_html_e( 'Account configured and active', 'lccl-de' ); ?>
						</div>
					<?php else : ?>
						<div class="lccl-prog__gateway-badge lccl-prog__gateway-badge--warn">
							<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
							<?php esc_html_e( 'Account not yet configured', 'lccl-de' ); ?>
						</div>
					<?php endif; ?>
				</div>

				<form method="post" action="<?php echo esc_url( LCCL_DE_Admin_Programs::gateway_url( array( 'subtab' => $p_key ) ) ); ?>" class="lccl-prog__gateway-form">
					<?php wp_nonce_field( 'lccl_de_mpgs_save', 'lccl_de_mpgs_nonce' ); ?>
					<input type="hidden" name="lccl_de_mpgs_save" value="1">
					<input type="hidden" name="gateway_profile" value="<?php echo esc_attr( $p_key ); ?>">

					<table class="form-table lccl-prog__form-table" role="presentation">
						<tbody>

							<tr>
								<th scope="row">
									<label for="lccl-gw-label-<?php echo esc_attr( $p_key ); ?>"><?php esc_html_e( 'Account Display Name', 'lccl-de' ); ?></label>
								</th>
								<td>
									<input
										id="lccl-gw-label-<?php echo esc_attr( $p_key ); ?>"
										name="profile_label"
										type="text"
										class="regular-text"
										value="<?php echo esc_attr( $cfg['label'] ); ?>"
										placeholder="<?php echo esc_attr( $p_data['default_label'] ); ?>"
									>
									<p class="description"><?php esc_html_e( 'Custom name to identify this merchant configuration in admin screens.', 'lccl-de' ); ?></p>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="lccl-gw-url-<?php echo esc_attr( $p_key ); ?>"><?php esc_html_e( 'Gateway URL', 'lccl-de' ); ?> <span class="lccl-prog__req">*</span></label>
								</th>
								<td>
									<input
										id="lccl-gw-url-<?php echo esc_attr( $p_key ); ?>"
										name="gateway_url"
										type="url"
										class="regular-text"
										value="<?php echo esc_attr( $cfg['gateway_url'] ); ?>"
										placeholder="https://cbcmpgs.gateway.mastercard.com/"
										required
									>
									<p class="description"><?php esc_html_e( 'Provided by your bank (e.g. Commercial Bank MPGS endpoint: https://cbcmpgs.gateway.mastercard.com/). Must end with a trailing slash.', 'lccl-de' ); ?></p>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="lccl-gw-version-<?php echo esc_attr( $p_key ); ?>"><?php esc_html_e( 'API Version', 'lccl-de' ); ?></label>
								</th>
								<td>
									<input
										id="lccl-gw-version-<?php echo esc_attr( $p_key ); ?>"
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
											esc_html__( 'Recommended: %d or higher for modern Hosted Checkout.', 'lccl-de' ),
											LCCL_DE_MPGS_Client::DEFAULT_API_VERSION
										);
										?>
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="lccl-gw-merchant-<?php echo esc_attr( $p_key ); ?>"><?php esc_html_e( 'Merchant ID', 'lccl-de' ); ?> <span class="lccl-prog__req">*</span></label>
								</th>
								<td>
									<input
										id="lccl-gw-merchant-<?php echo esc_attr( $p_key ); ?>"
										name="merchant_id"
										type="text"
										class="regular-text"
										value="<?php echo esc_attr( $cfg['merchant_id'] ); ?>"
										placeholder="<?php esc_attr_e( 'e.g. COLOMLEADLKR', 'lccl-de' ); ?>"
										autocomplete="off"
										required
									>
									<p class="description"><?php esc_html_e( 'Your unique Merchant ID from your bank for this account.', 'lccl-de' ); ?></p>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="lccl-gw-password-<?php echo esc_attr( $p_key ); ?>"><?php esc_html_e( 'API Password', 'lccl-de' ); ?></label>
								</th>
								<td>
									<input
										id="lccl-gw-password-<?php echo esc_attr( $p_key ); ?>"
										name="api_password"
										type="password"
										class="regular-text"
										value=""
										placeholder="<?php echo '' !== $cfg['api_password'] ? esc_attr__( '(saved — leave blank to keep)', 'lccl-de' ) : esc_attr__( 'Enter 32-character API password', 'lccl-de' ); ?>"
										autocomplete="new-password"
									>
									<p class="description">
										<?php
										if ( '' !== $cfg['api_password'] ) {
											esc_html_e( 'Password is saved. Leave blank to keep current password. Stored encrypted with AES-256-GCM.', 'lccl-de' );
										} else {
											esc_html_e( 'The 32-character API password generated from the MPGS Merchant Admin portal. Stored encrypted with AES-256-GCM.', 'lccl-de' );
										}
										?>
									</p>
								</td>
							</tr>

						</tbody>
					</table>

					<p class="submit">
						<button type="submit" class="button button-primary">
							<?php
							printf(
								/* translators: %s: profile title */
								esc_html__( 'Save %s Settings', 'lccl-de' ),
								esc_html( $p_data['label'] )
							);
							?>
						</button>
					</p>
				</form>
			</div>
		<?php endforeach; ?>
	</div>

	<hr class="lccl-prog__divider">

	<div class="lccl-prog__section-header">
		<h3 class="lccl-prog__section-title lccl-prog__section-title--sm">
			<?php esc_html_e( 'Integration Notes & Return Whitelist', 'lccl-de' ); ?>
		</h3>
	</div>

	<ul class="lccl-prog__gateway-notes">
		<li>
			<?php
			printf(
				/* translators: URL */
				wp_kses(
					__( 'Whitelist the <strong>Return URL</strong> in your bank MPGS Merchant Administration portal (<em>Admin → Integration Settings → Hosted Checkout → Return URL</em>): <code>%s</code>', 'lccl-de' ),
					array( 'strong' => array(), 'em' => array(), 'code' => array() )
				),
				esc_html( add_query_arg( array( LCCL_DE_Membership_Form::QA_RETURN => '1', LCCL_DE_Membership_Form::QA_ORDER_REF => 'ORDER_REF' ), home_url( '/' ) ) )
			);
			?>
		</li>
		<li>
			<?php esc_html_e( 'Each merchant account operates independently. You can configure individual credentials for public donations, member fees, and future campaigns.', 'lccl-de' ); ?>
		</li>
		<li>
			<?php esc_html_e( 'All payment attempts, success indicators, and bank receipt numbers are recorded in the plugin audit log.', 'lccl-de' ); ?>
		</li>
	</ul>

</div>

<script>
( function() {
	var root = document.querySelector( '[data-lccl-gateway-root]' );
	if ( ! root ) {
		return;
	}

	var tabLinks = root.querySelectorAll( '[data-gw-subtab-link]' );
	var panels   = root.querySelectorAll( '[data-gw-panel]' );

	function switchSubtab( key, updateUrl ) {
		Array.prototype.forEach.call( tabLinks, function( link ) {
			var isCurrent = link.getAttribute( 'data-gw-subtab-link' ) === key;
			link.classList.toggle( 'is-active', isCurrent );
			link.setAttribute( 'aria-selected', isCurrent ? 'true' : 'false' );
		} );

		Array.prototype.forEach.call( panels, function( panel ) {
			var isCurrent = panel.getAttribute( 'data-gw-panel' ) === key;
			panel.classList.toggle( 'is-active', isCurrent );
			if ( isCurrent ) {
				panel.removeAttribute( 'hidden' );
			} else {
				panel.setAttribute( 'hidden', 'hidden' );
			}
		} );

		if ( updateUrl && window.history && window.history.replaceState ) {
			var url = new URL( window.location.href );
			url.searchParams.set( 'subtab', key );
			window.history.replaceState( null, '', url.toString() );
		}
	}

	Array.prototype.forEach.call( tabLinks, function( link ) {
		link.addEventListener( 'click', function( event ) {
			event.preventDefault();
			var key = link.getAttribute( 'data-gw-subtab-link' );
			switchSubtab( key, true );
		} );
	} );
} )();
</script>
