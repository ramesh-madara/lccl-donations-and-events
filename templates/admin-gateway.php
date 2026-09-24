<?php
/**
 * Payment Gateway settings tab — Multi-client CBC Paycenter Web 4.0 credentials.
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
	$profiles = LCCL_DE_Settings::get_all_paycenter_profiles();
}

if ( empty( $subtab ) ) {
	$subtab = isset( $_GET['subtab'] ) ? sanitize_key( wp_unslash( $_GET['subtab'] ) ) : LCCL_DE_Settings::PROFILE_DONATIONS; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$subtab = LCCL_DE_Settings::normalize_paycenter_profile( $subtab );
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
			<?php esc_html_e( 'Commercial Bank of Ceylon (CBC) Paycenter Web 4.0', 'lccl-de' ); ?>
		</h2>
		<p class="lccl-prog__section-desc">
			<?php esc_html_e( 'Configure dedicated CBC Paycenter (Bancstac) client accounts for different payment streams. Switch between the accounts below to manage credentials.', 'lccl-de' ); ?>
		</p>
	</div>

	<!-- Subtab Navigation -->
	<div class="lccl-gw-subnav" role="tablist" aria-label="<?php esc_attr_e( 'Merchant Accounts', 'lccl-de' ); ?>">
		<?php foreach ( $profiles as $p_key => $p_data ) : ?>
			<?php
			$is_active       = ( $p_key === $subtab );
			$has_credentials = ! empty( $p_data['has_credentials'] );
			$is_enabled      = ! empty( $p_data['enabled'] );
			$is_configured   = ! empty( $p_data['is_configured'] );
			$subtab_url      = LCCL_DE_Admin_Programs::gateway_url( array( 'subtab' => $p_key ) );

			if ( $is_configured ) {
				$dot_class = 'lccl-gw-subnav__dot--ok';
				$dot_title = __( 'Active and accepting payments', 'lccl-de' );
			} elseif ( $has_credentials && ! $is_enabled ) {
				$dot_class = 'lccl-gw-subnav__dot--paused';
				$dot_title = __( 'Temporarily turned off', 'lccl-de' );
			} else {
				$dot_class = 'lccl-gw-subnav__dot--empty';
				$dot_title = __( 'Not yet configured', 'lccl-de' );
			}
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
					class="lccl-gw-subnav__dot <?php echo esc_attr( $dot_class ); ?>"
					title="<?php echo esc_attr( $dot_title ); ?>"
					aria-hidden="true"
				></span>
				<span class="lccl-gw-subnav__label"><?php echo esc_html( $p_data['label'] ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>

	<!-- Subtab Panels -->
	<div class="lccl-gw-panels">
		<?php foreach ( $profiles as $p_key => $p_data ) : ?>
			<?php
			$is_active       = ( $p_key === $subtab );
			$has_credentials = ! empty( $p_data['has_credentials'] );
			$is_enabled      = ! empty( $p_data['enabled'] );
			$is_configured   = ! empty( $p_data['is_configured'] );
			$cfg             = $p_data['config'];
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
							<?php esc_html_e( 'Account active & accepting payments', 'lccl-de' ); ?>
						</div>
					<?php elseif ( $has_credentials && ! $is_enabled ) : ?>
						<div class="lccl-prog__gateway-badge lccl-prog__gateway-badge--warn">
							<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="10" y1="15" x2="10" y2="9"/><line x1="14" y1="15" x2="14" y2="9"/></svg>
							<?php esc_html_e( 'Payment route temporarily turned off', 'lccl-de' ); ?>
						</div>
					<?php else : ?>
						<div class="lccl-prog__gateway-badge lccl-prog__gateway-badge--muted">
							<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
							<?php esc_html_e( 'Account not yet configured', 'lccl-de' ); ?>
						</div>
					<?php endif; ?>
				</div>

				<form method="post" action="<?php echo esc_url( LCCL_DE_Admin_Programs::gateway_url( array( 'subtab' => $p_key ) ) ); ?>" class="lccl-prog__gateway-form">
					<?php wp_nonce_field( 'lccl_de_paycenter_save', 'lccl_de_paycenter_nonce' ); ?>
					<input type="hidden" name="lccl_de_paycenter_save" value="1">
					<input type="hidden" name="gateway_profile" value="<?php echo esc_attr( $p_key ); ?>">

					<table class="form-table lccl-prog__form-table" role="presentation">
						<tbody>

							<tr>
								<th scope="row">
									<label for="lccl-pc-enabled-<?php echo esc_attr( $p_key ); ?>"><?php esc_html_e( 'Payment Route Status', 'lccl-de' ); ?></label>
								</th>
								<td>
									<label class="lccl-gw-switch">
										<input
											id="lccl-pc-enabled-<?php echo esc_attr( $p_key ); ?>"
											name="paycenter_enabled"
											type="checkbox"
											value="1"
											<?php checked( ! empty( $cfg['enabled'] ) ); ?>
										>
										<span class="lccl-gw-switch__slider" aria-hidden="true"></span>
										<span class="lccl-gw-switch__text">
											<?php echo ! empty( $cfg['enabled'] ) ? esc_html__( 'Enabled (accepting payments)', 'lccl-de' ) : esc_html__( 'Turned off (payments paused)', 'lccl-de' ); ?>
										</span>
									</label>
									<p class="description">
										<?php esc_html_e( 'Turn this off to temporarily disable payments through this client account. When turned off, checkout forms using this route will display a notice and disable payment submission.', 'lccl-de' ); ?>
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="lccl-pc-label-<?php echo esc_attr( $p_key ); ?>"><?php esc_html_e( 'Account Display Name', 'lccl-de' ); ?></label>
								</th>
								<td>
									<input
										id="lccl-pc-label-<?php echo esc_attr( $p_key ); ?>"
										name="paycenter_label"
										type="text"
										class="regular-text"
										value="<?php echo esc_attr( $cfg['label'] ); ?>"
										placeholder="<?php echo esc_attr( $p_data['default_label'] ); ?>"
									>
									<p class="description"><?php esc_html_e( 'Custom name to identify this client configuration in admin screens.', 'lccl-de' ); ?></p>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="lccl-pc-endpoint-<?php echo esc_attr( $p_key ); ?>"><?php esc_html_e( 'API Endpoint', 'lccl-de' ); ?> <span class="lccl-prog__req">*</span></label>
								</th>
								<td>
									<input
										id="lccl-pc-endpoint-<?php echo esc_attr( $p_key ); ?>"
										name="paycenter_endpoint"
										type="url"
										class="large-text"
										value="<?php echo esc_attr( $cfg['endpoint'] ); ?>"
										placeholder="https://paycorp-cbc.prod.aws.paycorp.lk/rest/service/proxy/"
										required
									>
									<p class="description"><?php esc_html_e( 'Base endpoint URL from Bancstac / Commercial Bank of Ceylon. Must use HTTPS.', 'lccl-de' ); ?></p>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="lccl-pc-client-id-<?php echo esc_attr( $p_key ); ?>"><?php esc_html_e( 'Client ID', 'lccl-de' ); ?> <span class="lccl-prog__req">*</span></label>
								</th>
								<td>
									<input
										id="lccl-pc-client-id-<?php echo esc_attr( $p_key ); ?>"
										name="paycenter_client_id"
										type="text"
										class="regular-text"
										value="<?php echo esc_attr( $cfg['client_id'] ); ?>"
										placeholder="<?php esc_attr_e( 'Enter Client ID (e.g. 14000190)', 'lccl-de' ); ?>"
										autocomplete="off"
										required
									>
									<p class="description"><?php esc_html_e( 'The numeric Client ID provided by Bancstac for this account.', 'lccl-de' ); ?></p>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="lccl-pc-auth-token-<?php echo esc_attr( $p_key ); ?>"><?php esc_html_e( 'Auth Token', 'lccl-de' ); ?></label>
								</th>
								<td>
									<input
										id="lccl-pc-auth-token-<?php echo esc_attr( $p_key ); ?>"
										name="paycenter_auth_token"
										type="password"
										class="regular-text"
										value=""
										placeholder="<?php echo '' !== $cfg['auth_token'] ? esc_attr__( '(saved — leave blank to keep)', 'lccl-de' ) : esc_attr__( 'Enter Auth Token provided by Bancstac', 'lccl-de' ); ?>"
										autocomplete="new-password"
									>
									<p class="description">
										<?php
										if ( '' !== $cfg['auth_token'] ) {
											esc_html_e( 'Auth Token is saved. Leave blank to keep current token. Stored encrypted with AES-256-GCM.', 'lccl-de' );
										} else {
											esc_html_e( 'The Auth Token provided by Bancstac / CBC for this account. Stored encrypted with AES-256-GCM.', 'lccl-de' );
										}
										?>
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="lccl-pc-hmac-secret-<?php echo esc_attr( $p_key ); ?>"><?php esc_html_e( 'HMAC Secret', 'lccl-de' ); ?></label>
								</th>
								<td>
									<input
										id="lccl-pc-hmac-secret-<?php echo esc_attr( $p_key ); ?>"
										name="paycenter_hmac_secret"
										type="password"
										class="regular-text"
										value=""
										placeholder="<?php echo '' !== $cfg['hmac_secret'] ? esc_attr__( '(saved — leave blank to keep)', 'lccl-de' ) : esc_attr__( 'Enter HMAC Secret provided by Bancstac', 'lccl-de' ); ?>"
										autocomplete="new-password"
									>
									<p class="description">
										<?php
										if ( '' !== $cfg['hmac_secret'] ) {
											esc_html_e( 'HMAC Secret is saved. Leave blank to keep current secret. Stored encrypted with AES-256-GCM.', 'lccl-de' );
										} else {
											esc_html_e( 'The HMAC Secret provided by Bancstac / CBC for SHA-256 request signing. Stored encrypted with AES-256-GCM.', 'lccl-de' );
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
					__( 'Whitelist the <strong>Return URL</strong> with Bancstac / CBC: <code>%s</code>', 'lccl-de' ),
					array( 'strong' => array(), 'code' => array() )
				),
				esc_html( add_query_arg( array( LCCL_DE_Membership_Form::QA_RETURN => '1', LCCL_DE_Membership_Form::QA_ORDER_REF => 'ORDER_REF' ), home_url( '/' ) ) )
			);
			?>
		</li>
		<li>
			<?php esc_html_e( 'Each merchant account operates independently. You can configure individual Client IDs, Auth Tokens, and HMAC Secrets for public donations, member fees, and future campaigns.', 'lccl-de' ); ?>
		</li>
		<li>
			<?php esc_html_e( 'Amount and currency are verified server-side in PAYMENT_COMPLETE against the stored DB record before marking any payment as paid.', 'lccl-de' ); ?>
		</li>
		<li>
			<?php esc_html_e( 'The clientRef (order reference) is cross-checked in the PAYMENT_COMPLETE response as an additional anti-tampering measure.', 'lccl-de' ); ?>
		</li>
		<li>
			<?php esc_html_e( 'All API calls enforce TLS 1.2+ HTTPS and verify SSL certificates. Raw credentials and authorization secrets are encrypted at rest with AES-256-GCM.', 'lccl-de' ); ?>
		</li>
	</ul>

</div>

<script>
( function() {
	function switchSubtab( key, updateUrl ) {
		var root = document.querySelector( '[data-lccl-gateway-root]' );
		if ( ! root ) {
			return;
		}
		var tabLinks = root.querySelectorAll( '[data-gw-subtab-link]' );
		var panels   = root.querySelectorAll( '[data-gw-panel]' );

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

	// Delegated click handler on document so it works across AJAX and full page loads.
	document.addEventListener( 'click', function( event ) {
		var link = event.target.closest ? event.target.closest( '[data-gw-subtab-link]' ) : null;
		if ( ! link ) {
			return;
		}
		event.preventDefault();
		var key = link.getAttribute( 'data-gw-subtab-link' );
		switchSubtab( key, true );
	} );

	// Dynamically update switch label when user toggles.
	document.addEventListener( 'change', function( event ) {
		var sw = event.target && event.target.closest ? event.target.closest( '.lccl-gw-switch input' ) : null;
		if ( ! sw ) {
			return;
		}
		var text = sw.closest( '.lccl-gw-switch' ).querySelector( '.lccl-gw-switch__text' );
		if ( text ) {
			text.textContent = sw.checked
				? '<?php echo esc_js( __( 'Enabled (accepting payments)', 'lccl-de' ) ); ?>'
				: '<?php echo esc_js( __( 'Turned off (payments paused)', 'lccl-de' ) ); ?>';
		}
	} );
} )();
</script>
