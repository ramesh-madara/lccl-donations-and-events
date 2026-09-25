<?php
/**
 * Project sponsorship form markup with CBC Paycenter gateway integration.
 *
 * @package LCCL_Donations_And_Events
 *
 * @var array       $atts              Shortcode attributes.
 * @var array       $values            Previously entered values, keyed by field name.
 * @var array       $errors            Validation errors array (flat, for global error display).
 * @var array|null  $payment_success   Payment success array if returned from gateway.
 * @var string      $payment_notice    Payment cancel or error notice message.
 * @var bool        $gateway_configured Whether the Fundraisers payment gateway profile is active.
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

$projects = LCCL_DE_Sponsorship_Form::projects();
$project  = $val( 'project' );
$project  = isset( $projects[ $project ] ) ? $project : 'spectacles';
$amount   = $val( 'amount' );
$amount   = '' !== $amount ? $amount : LCCL_DE_Sponsorship_Form::DEFAULT_AMOUNT;
$total    = LCCL_DE_Sponsorship_Form::format_amount( $amount );
?>
<div class="lccl-bdf lccl-bdf--sponsorship">
	<?php if ( '' !== $atts['banner_title'] || '' !== $atts['banner_intro'] ) : ?>
		<header class="lccl-ps__banner">
			<?php if ( '' !== $atts['banner_title'] ) : ?>
				<h2 class="lccl-ps__banner-title"><?php echo esc_html( $atts['banner_title'] ); ?></h2>
			<?php endif; ?>
			<?php if ( '' !== $atts['banner_intro'] ) : ?>
				<p class="lccl-ps__banner-intro"><?php echo esc_html( $atts['banner_intro'] ); ?></p>
			<?php endif; ?>
		</header>
	<?php endif; ?>

	<div class="lccl-ps">
		<form
			class="lccl-bdf__form lccl-ps__form"
			id="lccl-ps-form"
			method="post"
			action="<?php echo esc_url( remove_query_arg( array( LCCL_DE_Sponsorship_Form::QA_RETURN, LCCL_DE_Sponsorship_Form::QA_CANCEL, LCCL_DE_Sponsorship_Form::QA_ORDER_REF, 'reqid', 'ReqID', 'lccl_ps_error' ) ) ); ?>"
			data-required-message="<?php echo esc_attr( LCCL_DE_Sponsorship_Form::required_field_message() ); ?>"
			data-amount-message="<?php echo esc_attr( LCCL_DE_Sponsorship_Form::amount_error_message() ); ?>"
			data-email-message="<?php echo esc_attr( LCCL_DE_Sponsorship_Form::email_error_message() ); ?>"
			data-phone-message="<?php echo esc_attr( LCCL_DE_Sponsorship_Form::phone_error_message() ); ?>"
			novalidate
		>
			<?php wp_nonce_field( LCCL_DE_Sponsorship_Form::NONCE_ACTION, LCCL_DE_Sponsorship_Form::NONCE_FIELD ); ?>

			<?php if ( ! empty( $payment_success ) ) : ?>
				<p class="lccl-bdf__banner lccl-bdf__banner--success" role="status" style="margin-bottom: 24px;">
					<span class="lccl-bdf__banner-mark" aria-hidden="true"></span>
					<span class="lccl-bdf__banner-copy">
						<strong class="lccl-bdf__banner-title"><?php esc_html_e( 'Sponsorship Received with Thanks!', 'lccl-de' ); ?></strong>
						<span class="lccl-bdf__banner-text">
							<?php
							printf(
								/* translators: 1: sponsor name, 2: project title, 3: amount formatted, 4: receipt number */
								esc_html__( 'Thank you, %1$s. Your sponsorship of %2$s for "%3$s" was received successfully. Receipt: %4$s', 'lccl-de' ),
								'<strong>' . esc_html( $payment_success['sponsor_name'] ) . '</strong>',
								'<strong>' . esc_html( 'LKR ' . number_format( (float) $payment_success['amount'], 2 ) ) . '</strong>',
								esc_html( $payment_success['project_label'] ),
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
				<p class="lccl-bdf__banner lccl-bdf__banner--error" role="alert" style="margin-bottom: 24px;">
					<?php echo esc_html( implode( ' ', $errors ) ); ?>
				</p>
			<?php endif; ?>

			<p class="lccl-bdf__banner lccl-bdf__banner--error" data-lccl-notice="required" role="alert" hidden>
				<?php esc_html_e( 'Please complete all fields marked with an *.', 'lccl-de' ); ?>
			</p>

			<header class="lccl-bdf__header">
				<?php if ( '' !== $atts['title'] ) : ?>
					<h3 class="lccl-bdf__title"><?php echo esc_html( $atts['title'] ); ?></h3>
				<?php endif; ?>
				<p class="lccl-bdf__required-note">
					<?php
					printf(
						/* translators: %s: required field asterisk. */
						esc_html__( 'Fields marked with an %s are required', 'lccl-de' ),
						'<span class="lccl-bdf__req">*</span>'
					);
					?>
				</p>
				<?php if ( '' !== $atts['intro'] ) : ?>
					<p class="lccl-bdf__intro"><?php echo esc_html( $atts['intro'] ); ?></p>
				<?php endif; ?>
			</header>

			<div class="lccl-ps__fields">
				<div class="lccl-bdf__field lccl-ps__field--first<?php echo esc_attr( $invalid( 'project' ) ); ?>">
					<label class="lccl-bdf__label" for="lccl-ps-project">
						<?php esc_html_e( 'Project', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
					</label>
					<select class="lccl-bdf__select" id="lccl-ps-project" name="project" required>
						<?php foreach ( $projects as $key => $item ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $project, $key ); ?>>
								<?php echo esc_html( $item['title'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php $notice( 'project' ); ?>
				</div>

				<div class="lccl-df__amount-row<?php echo esc_attr( $invalid( 'amount' ) ); ?>">
					<input
						class="lccl-df__amount-input"
						type="text"
						id="lccl-ps-amount"
						name="amount"
						value="<?php echo esc_attr( $amount ); ?>"
						placeholder="<?php esc_attr_e( '0', 'lccl-de' ); ?>"
						inputmode="numeric"
						autocomplete="off"
						maxlength="12"
						data-lccl-validate="amount"
						data-invalid-message="<?php echo esc_attr( LCCL_DE_Sponsorship_Form::amount_error_message() ); ?>"
						required
					>
					<div class="lccl-df__currency" aria-hidden="true">
						<span><?php esc_html_e( 'LKR', 'lccl-de' ); ?></span>
					</div>
				</div>
				<?php $notice( 'amount' ); ?>

				<div class="lccl-df__presets" role="group" aria-label="<?php esc_attr_e( 'Suggested amounts', 'lccl-de' ); ?>">
					<div class="lccl-df__preset-row">
						<?php foreach ( array( 1000, 5000, 10000 ) as $preset ) : ?>
							<button
								class="lccl-df__preset<?php echo ( (string) $preset === (string) $amount ) ? ' is-active' : ''; ?>"
								type="button"
								data-lccl-preset="<?php echo esc_attr( (string) $preset ); ?>"
							>
								<?php echo esc_html( number_format( $preset ) . ' LKR' ); ?>
							</button>
						<?php endforeach; ?>
					</div>
					<div class="lccl-df__preset-row">
						<?php foreach ( array( 25000, 50000 ) as $preset ) : ?>
							<button
								class="lccl-df__preset<?php echo ( (string) $preset === (string) $amount ) ? ' is-active' : ''; ?>"
								type="button"
								data-lccl-preset="<?php echo esc_attr( (string) $preset ); ?>"
							>
								<?php echo esc_html( number_format( $preset ) . ' LKR' ); ?>
							</button>
						<?php endforeach; ?>
					</div>
					<div class="lccl-df__preset-row">
						<button class="lccl-df__preset lccl-df__preset--custom" type="button" data-lccl-preset="custom">
							<?php esc_html_e( 'CUSTOM VALUE', 'lccl-de' ); ?>
						</button>
					</div>
				</div>

				<div class="lccl-df__pair">
					<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'first_name' ) ); ?>">
						<label class="lccl-bdf__label" for="lccl-ps-first-name">
							<?php esc_html_e( 'First Name', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
						</label>
						<input
							class="lccl-bdf__input"
							type="text"
							id="lccl-ps-first-name"
							name="first_name"
							value="<?php echo esc_attr( $val( 'first_name' ) ); ?>"
							placeholder="<?php esc_attr_e( 'John', 'lccl-de' ); ?>"
							autocomplete="given-name"
							maxlength="100"
							required
						>
						<?php $notice( 'first_name' ); ?>
					</div>
					<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'last_name' ) ); ?>">
						<label class="lccl-bdf__label" for="lccl-ps-last-name">
							<?php esc_html_e( 'Last Name', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
						</label>
						<input
							class="lccl-bdf__input"
							type="text"
							id="lccl-ps-last-name"
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
						<label class="lccl-bdf__label" for="lccl-ps-email">
							<?php esc_html_e( 'Email', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
						</label>
						<input
							class="lccl-bdf__input"
							type="email"
							id="lccl-ps-email"
							name="email"
							value="<?php echo esc_attr( $val( 'email' ) ); ?>"
							placeholder="<?php esc_attr_e( 'name@example.com', 'lccl-de' ); ?>"
							autocomplete="email"
							maxlength="191"
							data-lccl-validate="email"
							data-invalid-message="<?php echo esc_attr( LCCL_DE_Sponsorship_Form::email_error_message() ); ?>"
							required
						>
						<?php $notice( 'email' ); ?>
					</div>
					<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'phone' ) ); ?>">
						<label class="lccl-bdf__label" for="lccl-ps-phone">
							<?php esc_html_e( 'Mobile / WhatsApp Number', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
						</label>
						<input
							class="lccl-bdf__input"
							type="tel"
							id="lccl-ps-phone"
							name="phone"
							value="<?php echo esc_attr( $val( 'phone' ) ); ?>"
							placeholder="<?php esc_attr_e( '+94712345678', 'lccl-de' ); ?>"
							inputmode="tel"
							autocomplete="tel"
							maxlength="<?php echo 0 === strpos( (string) $val( 'phone' ), '+' ) ? 12 : 10; ?>"
							data-lccl-validate="phone"
							data-invalid-message="<?php echo esc_attr( LCCL_DE_Sponsorship_Form::phone_error_message() ); ?>"
							required
						>
						<?php $notice( 'phone' ); ?>
					</div>
				</div>

				<div class="lccl-bdf__field">
					<label class="lccl-bdf__label" for="lccl-ps-message">
						<?php esc_html_e( 'Message (Optional)', 'lccl-de' ); ?>
					</label>
					<textarea
						class="lccl-bdf__textarea lccl-df__message"
						id="lccl-ps-message"
						name="message"
						placeholder="<?php esc_attr_e( 'If you would like to share any additional information, please enter it here.', 'lccl-de' ); ?>"
						maxlength="1000"
						rows="3"
					><?php echo esc_textarea( $val( 'message' ) ); ?></textarea>
				</div>

				<div class="lccl-df__total">
					<div class="lccl-df__total-label">
						<span><?php esc_html_e( 'Support Total', 'lccl-de' ); ?></span>
					</div>
					<input
						class="lccl-df__total-input"
						type="text"
						id="lccl-ps-total"
						value="<?php echo esc_attr( $total ); ?>"
						readonly
						tabindex="-1"
						aria-label="<?php esc_attr_e( 'Support total', 'lccl-de' ); ?>"
					>
				</div>
			</div>

			<button
				class="lccl-bdf__submit lccl-df__submit"
				id="lccl-ps-submit-btn"
				type="submit"
				<?php echo $gateway_configured ? '' : 'disabled'; ?>
			>
				<span class="lccl-df__pay-label" data-default-text="<?php esc_attr_e( 'Support This Project', 'lccl-de' ); ?>" data-loading-text="<?php esc_attr_e( 'Redirecting to payment...', 'lccl-de' ); ?>">
					<?php esc_html_e( 'Support This Project', 'lccl-de' ); ?>
				</span>
				<span class="lccl-df__pay-spinner" hidden aria-hidden="true"></span>
			</button>

			<?php if ( ! $gateway_configured ) : ?>
				<p class="lccl-bdf__notice" role="alert" style="margin-top:12px;">
					<?php esc_html_e( 'Online sponsorship payments are not currently available. Please contact the club administrator.', 'lccl-de' ); ?>
				</p>
			<?php else : ?>
				<p class="lccl-bdf__secure" style="margin-top:12px;font-size:13px;color:#666;">
					<?php esc_html_e( 'Payments are processed securely via Commercial Bank of Ceylon (CBC) Paycenter.', 'lccl-de' ); ?>
				</p>
			<?php endif; ?>
		</form>

		<div class="lccl-ps__image-panel">
			<img
				class="lccl-ps__image"
				src="<?php echo esc_url( function_exists( 'content_url' ) ? content_url( '/uploads/2026/09/donation_project.jpg' ) : 'https://www.colomboleads.org/wp-content/uploads/2026/09/donation_project.jpg' ); ?>"
				alt="<?php esc_attr_e( 'Project Sponsorship - Lions Club of Colombo LEADS', 'lccl-de' ); ?>"
				loading="lazy"
			>
		</div>

		<?php /* Hidden for now: Projects You Can Support section (kept for future use)
		<div class="lccl-ps__projects">
			<h3 class="lccl-ps__projects-title"><?php esc_html_e( 'Projects You Can Support', 'lccl-de' ); ?></h3>
			<p class="lccl-ps__projects-lede">
				<?php esc_html_e( 'Select a project below to support it. The selected project will automatically be shown in the payment form.', 'lccl-de' ); ?>
			</p>

			<?php foreach ( $projects as $key => $item ) : ?>
				<?php
				$value     = (float) $item['value'];
				$raised    = (float) $item['raised'];
				$remaining = max( 0, $value - $raised );
				$percent   = LCCL_DE_Sponsorship_Form::progress_percent( $value, $raised );
				?>
				<article class="lccl-ps__card<?php echo $key === $project ? ' is-selected' : ''; ?>" data-lccl-project-card="<?php echo esc_attr( $key ); ?>">
					<span class="lccl-ps__status"><?php echo esc_html( $item['label'] ); ?></span>
					<h4 class="lccl-ps__card-title"><?php echo esc_html( $item['title'] ); ?></h4>
					<p class="lccl-ps__card-summary"><?php echo esc_html( $item['summary'] ); ?></p>
					<div class="lccl-ps__details">
						<p>
							<strong><?php esc_html_e( 'Project Value:', 'lccl-de' ); ?></strong>
							<?php echo esc_html( LCCL_DE_Sponsorship_Form::format_rs( $value ) ); ?>
						</p>
						<p>
							<strong><?php esc_html_e( 'Raised:', 'lccl-de' ); ?></strong>
							<?php echo esc_html( LCCL_DE_Sponsorship_Form::format_rs( $raised ) ); ?>
						</p>
						<p>
							<strong><?php esc_html_e( 'Remaining:', 'lccl-de' ); ?></strong>
							<?php echo esc_html( LCCL_DE_Sponsorship_Form::format_rs( $remaining ) ); ?>
						</p>
					</div>
					<div class="lccl-ps__progress" aria-hidden="true">
						<span style="width: <?php echo esc_attr( (string) $percent ); ?>%;"></span>
					</div>
					<button class="lccl-ps__pick" type="button" data-lccl-select-project="<?php echo esc_attr( $key ); ?>">
						<?php esc_html_e( 'Support This Project', 'lccl-de' ); ?>
					</button>
				</article>
			<?php endforeach; ?>
		</div>
		*/ ?>
	</div>
</div>
