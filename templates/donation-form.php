<?php
/**
 * Donation form markup with Commercial Bank of Ceylon (CBC) Paycenter gateway integration.
 *
 * @package LCCL_Donations_And_Events
 *
 * @var array $atts               Shortcode attributes.
 * @var array $values             Previously entered values, keyed by field name.
 * @var array $errors             Validation errors.
 * @var array|null $payment_success Payment success array if returned from gateway.
 * @var string $payment_notice    Payment cancel or notice message.
 * @var bool $gateway_configured  Whether Donations payment gateway profile is active.
 */

defined( 'ABSPATH' ) || exit;

$val = static function ( $key ) use ( $values ) {
	return isset( $values[ $key ] ) ? $values[ $key ] : '';
};
$err = static function ( $key ) use ( $errors ) {
	return isset( $errors[ $key ] ) ? $errors[ $key ] : '';
};
$invalid = static function ( $key ) use ( $errors ) {
	return isset( $errors[ $key ] ) ? ' lccl-bdf__field--invalid' : '';
};
$notice = static function ( $key ) use ( $err ) {
	$msg = $err( $key );
	printf(
		'<p class="lccl-bdf__notice" data-lccl-notice="%1$s" role="alert"%2$s>%3$s</p>',
		esc_attr( $key ),
		$msg ? '' : ' hidden',
		$msg ? esc_html( $msg ) : ''
	);
};

$payment_success    = isset( $payment_success ) ? $payment_success : null;
$payment_notice     = isset( $payment_notice ) ? $payment_notice : '';
$gateway_configured = isset( $gateway_configured ) ? (bool) $gateway_configured : false;
$currency           = isset( $currency ) && '' !== $currency ? strtoupper( $currency ) : 'LKR';
$causes             = isset( $values['causes'] ) && is_array( $values['causes'] ) ? $values['causes'] : array();
$amount             = $val( 'amount' );
$required_msg       = LCCL_DE_Donation_Form::required_field_message();
$total_display      = '' !== $amount ? LCCL_DE_Donation_Form::format_amount( $amount, $currency ) : '';
$presets_row1       = ( 'USD' === $currency ) ? array( 10, 25, 50 ) : array( 1000, 5000, 10000 );
$presets_row2       = ( 'USD' === $currency ) ? array( 100, 250 ) : array( 25000, 50000 );
?>
<div class="lccl-bdf lccl-bdf--donation">
	<div class="lccl-donation-layout">
		<form
			class="lccl-bdf__form lccl-donation__form"
			method="post"
			action="<?php echo esc_url( remove_query_arg( array( LCCL_DE_Donation_Form::QA_RETURN, LCCL_DE_Donation_Form::QA_CANCEL, LCCL_DE_Donation_Form::QA_ORDER_REF, 'reqid', 'ReqID', 'lccl_df_error', 'lccl_mpgs_return', 'lccl_mpgs_ref' ) ) ); ?>"
			data-required-message="<?php echo esc_attr( $required_msg ); ?>"
			data-amount-message="<?php echo esc_attr( LCCL_DE_Donation_Form::amount_error_message() ); ?>"
			data-email-message="<?php echo esc_attr( LCCL_DE_Donation_Form::email_error_message() ); ?>"
			data-phone-message="<?php echo esc_attr( LCCL_DE_Blood_Donor_Submissions::phone_error_message() ); ?>"
			novalidate
		>
			<?php wp_nonce_field( LCCL_DE_Donation_Form::NONCE_ACTION, LCCL_DE_Donation_Form::NONCE_FIELD ); ?>

			<?php if ( ! empty( $payment_success ) ) : ?>
				<p class="lccl-bdf__banner lccl-bdf__banner--success" role="status" style="margin-bottom: 24px;">
					<span class="lccl-bdf__banner-mark" aria-hidden="true"></span>
					<span class="lccl-bdf__banner-copy">
						<strong class="lccl-bdf__banner-title"><?php esc_html_e( 'Donation Received with Thanks!', 'lccl-de' ); ?></strong>
						<span class="lccl-bdf__banner-text">
							<?php
							printf(
								/* translators: 1: donor name, 2: amount formatted, 3: receipt number */
								esc_html__( 'Thank you, %1$s. Your donation of %2$s has been received successfully. Receipt: %3$s', 'lccl-de' ),
								'<strong>' . esc_html( $payment_success['donor_name'] ) . '</strong>',
								'<strong>' . esc_html( ( ! empty( $payment_success['currency'] ) ? $payment_success['currency'] : 'LKR' ) . ' ' . number_format( (float) $payment_success['amount'], 2 ) ) . '</strong>',
								'<code>' . esc_html( $payment_success['receipt'] ?: $payment_success['order_ref'] ) . '</code>'
							);
							?>
						</span>
					</span>
				</p>
			<?php endif; ?>

			<?php if ( ! empty( $payment_notice ) ) : ?>
				<p class="lccl-bdf__banner lccl-bdf__banner--error" role="alert" style="margin-bottom: 24px;">
					<?php echo esc_html( $payment_notice ); ?>
				</p>
			<?php endif; ?>

			<?php if ( ! empty( $errors ) ) : ?>
				<div class="lccl-bdf__banner lccl-bdf__banner--error" role="alert" style="margin-bottom: 24px;">
					<?php foreach ( $errors as $err_msg ) : ?>
						<p><?php echo esc_html( $err_msg ); ?></p>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<p class="lccl-bdf__banner lccl-bdf__banner--error" data-lccl-notice="required" role="alert" hidden>
				<?php esc_html_e( 'Please complete all fields marked with an *.', 'lccl-de' ); ?>
			</p>

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

			<div class="lccl-df">
				<div class="lccl-df__amount-row<?php echo esc_attr( $invalid( 'amount' ) ); ?>">
					<input
						class="lccl-df__amount-input"
						type="text"
						id="lccl-df-amount"
						name="amount"
						value="<?php echo esc_attr( $amount ); ?>"
						placeholder="<?php esc_attr_e( '0', 'lccl-de' ); ?>"
						inputmode="numeric"
						autocomplete="off"
						maxlength="12"
						data-lccl-validate="amount"
						data-invalid-message="<?php echo esc_attr( LCCL_DE_Donation_Form::amount_error_message() ); ?>"
						required
					>
					<div class="lccl-df__currency" aria-hidden="true">
						<span><?php echo esc_html( $currency ); ?></span>
					</div>
				</div>
				<?php $notice( 'amount' ); ?>

				<div class="lccl-df__presets" role="group" aria-label="<?php esc_attr_e( 'Suggested amounts', 'lccl-de' ); ?>">
					<div class="lccl-df__preset-row">
						<?php foreach ( $presets_row1 as $preset ) : ?>
							<button
								class="lccl-df__preset<?php echo ( (string) $preset === (string) $amount ) ? ' is-active' : ''; ?>"
								type="button"
								data-lccl-preset="<?php echo esc_attr( (string) $preset ); ?>"
							>
								<?php echo esc_html( number_format( $preset ) . ' ' . $currency ); ?>
							</button>
						<?php endforeach; ?>
					</div>
					<div class="lccl-df__preset-row">
						<?php foreach ( $presets_row2 as $preset ) : ?>
							<button
								class="lccl-df__preset<?php echo ( (string) $preset === (string) $amount ) ? ' is-active' : ''; ?>"
								type="button"
								data-lccl-preset="<?php echo esc_attr( (string) $preset ); ?>"
							>
								<?php echo esc_html( number_format( $preset ) . ' ' . $currency ); ?>
							</button>
						<?php endforeach; ?>
					</div>
					<div class="lccl-df__preset-row">
						<button class="lccl-df__preset lccl-df__preset--custom" type="button" data-lccl-preset="custom">
							<?php esc_html_e( 'CUSTOM VALUE', 'lccl-de' ); ?>
						</button>
					</div>
				</div>

				<div class="lccl-df__areas">
					<h3 class="lccl-df__areas-title"><?php esc_html_e( 'Areas you would like your donation to support', 'lccl-de' ); ?></h3>
					<p class="lccl-df__areas-lede"><?php esc_html_e( 'Please select the area(s) you would like your donation to support.', 'lccl-de' ); ?></p>
					<div class="lccl-df__causes" role="group" aria-label="<?php esc_attr_e( 'Areas you would like your donation to support', 'lccl-de' ); ?>">
						<?php foreach ( LCCL_DE_Donation_Form::causes() as $key => $label ) : ?>
							<label class="lccl-df__cause">
								<input type="checkbox" name="causes[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $causes, true ) ); ?>>
								<span class="lccl-bdf__check" aria-hidden="true"></span>
								<span><?php echo esc_html( $label ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="lccl-df__pair">
					<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'first_name' ) ); ?>">
						<label class="lccl-bdf__label" for="lccl-df-first-name">
							<?php esc_html_e( 'First Name', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
						</label>
						<input
							class="lccl-bdf__input"
							type="text"
							id="lccl-df-first-name"
							name="first_name"
							value="<?php echo esc_attr( $val( 'first_name' ) ); ?>"
							placeholder="<?php esc_attr_e( 'Saman', 'lccl-de' ); ?>"
							autocomplete="given-name"
							maxlength="100"
							required
						>
						<?php $notice( 'first_name' ); ?>
					</div>
					<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'last_name' ) ); ?>">
						<label class="lccl-bdf__label" for="lccl-df-last-name">
							<?php esc_html_e( 'Last Name', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
						</label>
						<input
							class="lccl-bdf__input"
							type="text"
							id="lccl-df-last-name"
							name="last_name"
							value="<?php echo esc_attr( $val( 'last_name' ) ); ?>"
							placeholder="<?php esc_attr_e( 'Perera', 'lccl-de' ); ?>"
							autocomplete="family-name"
							maxlength="100"
							required
						>
						<?php $notice( 'last_name' ); ?>
					</div>
				</div>

				<div class="lccl-df__pair">
					<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'email' ) ); ?>">
						<label class="lccl-bdf__label" for="lccl-df-email">
							<?php esc_html_e( 'Email', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
						</label>
						<input
							class="lccl-bdf__input"
							type="email"
							id="lccl-df-email"
							name="email"
							value="<?php echo esc_attr( $val( 'email' ) ); ?>"
							placeholder="<?php esc_attr_e( 'name@example.com', 'lccl-de' ); ?>"
							autocomplete="email"
							maxlength="191"
							data-lccl-validate="email"
							data-invalid-message="<?php echo esc_attr( LCCL_DE_Donation_Form::email_error_message() ); ?>"
							required
						>
						<?php $notice( 'email' ); ?>
					</div>
					<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'phone' ) ); ?>">
						<label class="lccl-bdf__label" for="lccl-df-phone">
							<?php esc_html_e( 'Mobile / WhatsApp Number', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
						</label>
						<input
							class="lccl-bdf__input"
							type="tel"
							id="lccl-df-phone"
							name="phone"
							value="<?php echo esc_attr( $val( 'phone' ) ); ?>"
							placeholder="<?php esc_attr_e( '+94712345678', 'lccl-de' ); ?>"
							inputmode="tel"
							autocomplete="tel"
							maxlength="<?php echo 0 === strpos( (string) $val( 'phone' ), '+' ) ? 12 : 10; ?>"
							data-lccl-validate="phone"
							data-invalid-message="<?php echo esc_attr( LCCL_DE_Blood_Donor_Submissions::phone_error_message() ); ?>"
							required
						>
						<?php $notice( 'phone' ); ?>
					</div>
				</div>

				<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'message' ) ); ?>">
					<label class="lccl-bdf__label" for="lccl-df-message">
						<?php esc_html_e( 'Message (Optional)', 'lccl-de' ); ?>
					</label>
					<textarea
						class="lccl-bdf__textarea lccl-df__message"
						id="lccl-df-message"
						name="message"
						placeholder="<?php esc_attr_e( 'If you would like to share any additional information, please enter it here.', 'lccl-de' ); ?>"
						maxlength="1000"
						rows="2"
					><?php echo esc_textarea( $val( 'message' ) ); ?></textarea>
					<?php $notice( 'message' ); ?>
				</div>

				<div class="lccl-df__total">
					<div class="lccl-df__total-label">
						<span><?php esc_html_e( 'Donation Total', 'lccl-de' ); ?></span>
					</div>
					<input
						class="lccl-df__total-input"
						type="text"
						id="lccl-df-total"
						value="<?php echo esc_attr( $total_display ); ?>"
						readonly
						tabindex="-1"
						aria-label="<?php esc_attr_e( 'Donation total', 'lccl-de' ); ?>"
					>
				</div>
			</div>

			<?php if ( ! $gateway_configured ) : ?>
				<div class="lccl-mf__notice lccl-mf__notice--warning" style="margin-bottom: 20px;">
					<p><?php esc_html_e( 'Online donations are currently being configured. Please check back shortly or contact the club administrator.', 'lccl-de' ); ?></p>
				</div>
			<?php endif; ?>

			<button
				class="lccl-bdf__submit lccl-df__submit"
				type="submit"
				id="lccl-df-submit-btn"
				<?php echo $gateway_configured ? '' : 'disabled aria-disabled="true"'; ?>
			>
				<span class="lccl-df__pay-label" data-loading-text="<?php esc_attr_e( 'Redirecting to payment...', 'lccl-de' ); ?>"><?php esc_html_e( 'DONATE NOW', 'lccl-de' ); ?></span>
				<span class="lccl-df__pay-spinner" aria-hidden="true" hidden></span>
			</button>

			<p class="lccl-mf__secure-note">
				<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
				<?php esc_html_e( 'Payments are processed securely via Commercial Bank of Ceylon (CBC) Paycenter.', 'lccl-de' ); ?>
			</p>
		</form>

		<div class="lccl-donation__image-panel">
			<img
				class="lccl-donation__image"
				src="<?php echo esc_url( function_exists( 'content_url' ) ? content_url( '/uploads/2026/09/donation.jpg' ) : 'https://www.colomboleads.org/wp-content/uploads/2026/09/donation.jpg' ); ?>"
				alt="<?php esc_attr_e( 'Donations - Lions Club of Colombo LEADS', 'lccl-de' ); ?>"
				loading="lazy"
			>
		</div>
	</div>
</div>
