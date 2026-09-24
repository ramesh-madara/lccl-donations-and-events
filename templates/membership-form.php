<?php
/**
 * Annual membership fee form.
 *
 * @package LCCL_Donations_And_Events
 *
 * @var array       $atts            Shortcode attributes (title, intro).
 * @var array       $values          Previously entered values, keyed by field name.
 * @var array       $fees            Calculated fee breakdown.
 * @var array       $errors          Validation error messages to display.
 * @var array|null  $payment_success Payment success payload from gateway return.
 * @var string|null $payment_notice  Payment notice/error string.
 */

defined( 'ABSPATH' ) || exit;

$val = static function ( $key ) use ( $values ) {
	return isset( $values[ $key ] ) ? $values[ $key ] : '';
};

$type  = $val( 'membership_type' );
$count = $val( 'family_count' );
$count = '' !== $count ? $count : '2';

$gateway_configured = LCCL_DE_Paycenter_Client::is_configured();
?>
<div class="lccl-bdf lccl-bdf--membership">
	<div class="lccl-membership-layout">
		<form
			class="lccl-bdf__form lccl-membership__form"
			method="post"
			action="<?php echo esc_url( get_permalink() ); ?>"
			data-rate="<?php echo esc_attr( (string) LCCL_DE_Membership_Form::RATE ); ?>"
			data-principal-usd="<?php echo esc_attr( (string) LCCL_DE_Membership_Form::PRINCIPAL_USD ); ?>"
			data-family-usd="<?php echo esc_attr( (string) LCCL_DE_Membership_Form::FAMILY_USD ); ?>"
			data-district="<?php echo esc_attr( (string) LCCL_DE_Membership_Form::DISTRICT_LKR ); ?>"
			data-club="<?php echo esc_attr( (string) LCCL_DE_Membership_Form::CLUB_LKR ); ?>"
			novalidate
		>
			<?php wp_nonce_field( LCCL_DE_Membership_Form::NONCE_ACTION, LCCL_DE_Membership_Form::NONCE_FIELD ); ?>

			<?php if ( ! empty( $payment_success ) ) : ?>
				<p class="lccl-bdf__banner lccl-bdf__banner--success" role="status">
					<span class="lccl-bdf__banner-mark" aria-hidden="true"></span>
					<span class="lccl-bdf__banner-copy">
						<strong class="lccl-bdf__banner-title"><?php esc_html_e( 'Thank you for your payment.', 'lccl-de' ); ?></strong>
						<span class="lccl-bdf__banner-text">
							<?php
							printf(
								/* translators: 1: member name, 2: amount formatted, 3: receipt number */
								esc_html__( 'Thank you, %1$s. Your annual membership fee payment of %2$s has been received successfully. Receipt: %3$s', 'lccl-de' ),
								'<strong>' . esc_html( $payment_success['member_name'] ) . '</strong>',
								'<strong>' . esc_html( 'LKR ' . number_format( (float) $payment_success['amount'], 2 ) ) . '</strong>',
								'<code>' . esc_html( $payment_success['receipt'] ?: $payment_success['order_ref'] ) . '</code>'
							);
							?>
						</span>
					</span>
				</p>
			<?php endif; ?>

			<?php if ( ! empty( $payment_notice ) ) : ?>
				<p class="lccl-bdf__banner lccl-bdf__banner--error" role="alert">
					<?php echo esc_html( $payment_notice ); ?>
				</p>
			<?php endif; ?>

			<?php if ( ! empty( $errors ) ) : ?>
				<div class="lccl-mf__notice lccl-mf__notice--error" role="alert">
					<?php foreach ( $errors as $err ) : ?>
						<p><?php echo esc_html( $err ); ?></p>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

		<?php if ( '' !== $atts['title'] || '' !== $atts['intro'] ) : ?>
			<header class="lccl-bdf__header">
				<?php if ( '' !== $atts['title'] ) : ?>
					<h2 class="lccl-bdf__title"><?php echo esc_html( $atts['title'] ); ?></h2>
				<?php endif; ?>
				<?php if ( '' !== $atts['intro'] ) : ?>
					<p class="lccl-bdf__intro"><?php echo esc_html( $atts['intro'] ); ?></p>
				<?php endif; ?>
			</header>
		<?php endif; ?>

		<div class="lccl-mf">
			<div class="lccl-mf__section">
				<h3 class="lccl-mf__section-title"><?php esc_html_e( 'Membership Type', 'lccl-de' ); ?></h3>
				<div class="lccl-bdf__field lccl-mf__field--first">
					<label class="lccl-bdf__label" for="lccl-mf-type">
						<?php esc_html_e( 'Select Membership Type', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
					</label>
					<select class="lccl-bdf__select" id="lccl-mf-type" name="membership_type" required>
						<option value=""><?php esc_html_e( 'Select membership type', 'lccl-de' ); ?></option>
						<?php foreach ( LCCL_DE_Membership_Form::types() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $type, $key ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="lccl-mf__family" data-lccl-family<?php echo 'family' === $type ? '' : ' hidden'; ?>>
					<div class="lccl-bdf__field lccl-mf__field--first">
						<label class="lccl-bdf__label" for="lccl-mf-count">
							<?php esc_html_e( 'Number of Family Members (Including Main Member)', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
						</label>
						<select class="lccl-bdf__select" id="lccl-mf-count" name="family_count">
							<?php foreach ( LCCL_DE_Membership_Form::family_counts() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $count, $key ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<p class="lccl-mf__note">
						<?php esc_html_e( 'The main member is responsible for the payment of the annual membership fees for all family members included in the membership.', 'lccl-de' ); ?>
					</p>
				</div>
			</div>

			<div class="lccl-mf__section">
				<h3 class="lccl-mf__section-title"><?php esc_html_e( 'Member Information', 'lccl-de' ); ?></h3>
				<div class="lccl-df__pair">
					<div class="lccl-bdf__field lccl-mf__field--first">
						<label class="lccl-bdf__label" for="lccl-mf-first-name">
							<?php esc_html_e( 'First Name', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
						</label>
						<input
							class="lccl-bdf__input"
							type="text"
							id="lccl-mf-first-name"
							name="first_name"
							value="<?php echo esc_attr( $val( 'first_name' ) ); ?>"
							placeholder="<?php esc_attr_e( 'Saman', 'lccl-de' ); ?>"
							autocomplete="given-name"
							maxlength="100"
							required
						>
					</div>
					<div class="lccl-bdf__field lccl-mf__field--first">
						<label class="lccl-bdf__label" for="lccl-mf-last-name">
							<?php esc_html_e( 'Last Name', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
						</label>
						<input
							class="lccl-bdf__input"
							type="text"
							id="lccl-mf-last-name"
							name="last_name"
							value="<?php echo esc_attr( $val( 'last_name' ) ); ?>"
							placeholder="<?php esc_attr_e( 'Perera', 'lccl-de' ); ?>"
							autocomplete="family-name"
							maxlength="100"
							required
						>
					</div>
				</div>

				<div class="lccl-df__pair">
					<div class="lccl-bdf__field">
						<label class="lccl-bdf__label" for="lccl-mf-email">
							<?php esc_html_e( 'Email', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
						</label>
						<input
							class="lccl-bdf__input"
							type="email"
							id="lccl-mf-email"
							name="email"
							value="<?php echo esc_attr( $val( 'email' ) ); ?>"
							placeholder="<?php esc_attr_e( 'name@example.com', 'lccl-de' ); ?>"
							autocomplete="email"
							maxlength="191"
							required
						>
					</div>
					<div class="lccl-bdf__field">
						<label class="lccl-bdf__label" for="lccl-mf-phone">
							<?php esc_html_e( 'Mobile / WhatsApp Number', 'lccl-de' ); ?>
						</label>
						<input
							class="lccl-bdf__input"
							type="tel"
							id="lccl-mf-phone"
							name="phone"
							value="<?php echo esc_attr( $val( 'phone' ) ); ?>"
							placeholder="<?php esc_attr_e( '07X XXX XXXX', 'lccl-de' ); ?>"
							inputmode="tel"
							autocomplete="tel"
							maxlength="12"
						>
					</div>
				</div>
			</div>

			<div class="lccl-mf__section">
				<h3 class="lccl-mf__section-title"><?php esc_html_e( 'Fee Calculation', 'lccl-de' ); ?></h3>
				<div class="lccl-mf__info">
					<p>
						<strong><?php esc_html_e( 'Exchange Rate:', 'lccl-de' ); ?></strong>
						<?php
						printf(
							/* translators: %s: formatted LKR rate */
							esc_html__( '1 USD = LKR %s', 'lccl-de' ),
							'<span data-lccl-rate>' . esc_html( LCCL_DE_Membership_Form::format_rate( $fees['rate'] ) ) . '</span>'
						);
						?>
					</p>
					<p>
						<strong><?php esc_html_e( 'Main Member Fee:', 'lccl-de' ); ?></strong>
						<?php esc_html_e( 'USD 50.00', 'lccl-de' ); ?>
					</p>
					<p>
						<strong><?php esc_html_e( 'Additional Family Member Fee:', 'lccl-de' ); ?></strong>
						<?php esc_html_e( 'USD 25.00 per additional family member', 'lccl-de' ); ?>
					</p>
				</div>

				<table class="lccl-mf__table">
					<tbody>
						<tr>
							<td><?php esc_html_e( 'Lions International Membership Fee', 'lccl-de' ); ?></td>
							<td data-lccl-intl><?php echo esc_html( LCCL_DE_Membership_Form::format_lkr( $fees['international_main'] ) ); ?></td>
						</tr>
						<tr data-lccl-family-line<?php echo ! empty( $fees['is_family'] ) ? '' : ' hidden'; ?>>
							<td><?php esc_html_e( 'Additional Family Member Fee', 'lccl-de' ); ?></td>
							<td data-lccl-family-fee><?php echo esc_html( LCCL_DE_Membership_Form::format_lkr( $fees['family_fee'] ) ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'District Payment', 'lccl-de' ); ?></td>
							<td data-lccl-district><?php echo esc_html( LCCL_DE_Membership_Form::format_lkr( $fees['district'] ) ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Club Payment', 'lccl-de' ); ?></td>
							<td data-lccl-club><?php echo esc_html( LCCL_DE_Membership_Form::format_lkr( $fees['club'] ) ); ?></td>
						</tr>
					</tbody>
				</table>

				<div class="lccl-df__total">
					<div class="lccl-df__total-label">
						<span><?php esc_html_e( 'Total Amount', 'lccl-de' ); ?></span>
					</div>
					<div class="lccl-mf__total-value" aria-live="polite">
						<span><?php esc_html_e( 'LKR', 'lccl-de' ); ?></span>
						<span data-lccl-total><?php echo esc_html( number_format( (float) $fees['total'], 2, '.', ',' ) ); ?></span>
					</div>
				</div>
			</div>
		</div>

		<?php if ( ! $gateway_configured ) : ?>
			<div class="lccl-mf__notice lccl-mf__notice--warning">
				<p><?php esc_html_e( 'Online payment is currently being configured. Please check back shortly or contact the club administrator.', 'lccl-de' ); ?></p>
			</div>
		<?php endif; ?>

		<button
			class="lccl-bdf__submit lccl-df__submit lccl-mf__pay-btn"
			type="submit"
			id="lccl-mf-submit-btn"
			<?php echo $gateway_configured ? '' : 'disabled aria-disabled="true"'; ?>
		>
			<span class="lccl-mf__pay-label" data-loading-text="<?php esc_attr_e( 'Redirecting to payment...', 'lccl-de' ); ?>"><?php esc_html_e( 'Pay Membership Fee', 'lccl-de' ); ?></span>
			<span class="lccl-mf__pay-spinner" aria-hidden="true" hidden></span>
		</button>

		<p class="lccl-mf__secure-note">
			<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
			<?php esc_html_e( 'Payments are processed securely via Commercial Bank of Ceylon (CBC) Paycenter.', 'lccl-de' ); ?>
		</p>
		</form>

		<div class="lccl-membership__image-panel">
			<img
				class="lccl-membership__image"
				src="<?php echo esc_url( function_exists( 'content_url' ) ? content_url( '/uploads/2026/09/membership-1.jpg' ) : 'https://www.colomboleads.org/wp-content/uploads/2026/09/membership-1.jpg' ); ?>"
				alt="<?php esc_attr_e( 'Annual Membership Fee - Lions Club of Colombo LEADS', 'lccl-de' ); ?>"
				loading="lazy"
			>
		</div>
	</div>
</div>
