<?php
/**
 * Sample donation form markup. No payment or database write.
 *
 * @package LCCL_Donations_And_Events
 *
 * @var array $atts    Shortcode attributes.
 * @var array $values  Previously entered values, keyed by field name.
 * @var array $errors  Validation errors, keyed by field name.
 * @var bool  $success Whether a sample success state is shown.
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
$causes        = isset( $values['causes'] ) && is_array( $values['causes'] ) ? $values['causes'] : array();
$amount        = $val( 'amount' );
$required_msg  = LCCL_DE_Donation_Form::required_field_message();
$total_display = '' !== $amount ? LCCL_DE_Donation_Form::format_amount( $amount ) : '';
?>
<div class="lccl-bdf lccl-bdf--donation">
	<form
		class="lccl-bdf__form"
		method="post"
		action="#"
		data-required-message="<?php echo esc_attr( $required_msg ); ?>"
		data-amount-message="<?php echo esc_attr( LCCL_DE_Donation_Form::amount_error_message() ); ?>"
		data-email-message="<?php echo esc_attr( LCCL_DE_Donation_Form::email_error_message() ); ?>"
		novalidate
	>

		<p class="lccl-bdf__banner lccl-bdf__banner--success" data-lccl-notice="sample-success" role="status" hidden>
			<span class="lccl-bdf__banner-mark" aria-hidden="true"></span>
			<span class="lccl-bdf__banner-copy">
				<strong class="lccl-bdf__banner-title"><?php esc_html_e( 'Thank you.', 'lccl-de' ); ?></strong>
				<span class="lccl-bdf__banner-text"><?php esc_html_e( 'This is a sample form. No payment was processed.', 'lccl-de' ); ?></span>
			</span>
		</p>

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

			<div class="lccl-df__causes" role="group" aria-label="<?php esc_attr_e( 'Causes', 'lccl-de' ); ?>">
				<?php foreach ( LCCL_DE_Donation_Form::causes() as $key => $label ) : ?>
					<label class="lccl-df__cause">
						<input type="checkbox" name="causes[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $causes, true ) ); ?>>
						<span class="lccl-bdf__check" aria-hidden="true"></span>
						<span><?php echo esc_html( $label ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>

			<div class="lccl-df__names">
				<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'first_name' ) ); ?>">
					<label class="lccl-bdf__label" for="lccl-df-first-name">
						<?php esc_html_e( 'First Name:', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
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
						<?php esc_html_e( 'Last Name:', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
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

			<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'email' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-df-email">
					<?php esc_html_e( 'Email:', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
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

			<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'message' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-df-message">
					<?php esc_html_e( 'Message:', 'lccl-de' ); ?>
				</label>
				<p class="lccl-df__message-hint">
					<?php esc_html_e( '*If you have a contact person within the Lions Club, please provide his/her name here.', 'lccl-de' ); ?>
				</p>
				<textarea
					class="lccl-bdf__textarea lccl-df__message"
					id="lccl-df-message"
					name="message"
					maxlength="1000"
					rows="2"
				><?php echo esc_textarea( $val( 'message' ) ); ?></textarea>
				<?php $notice( 'message' ); ?>
			</div>

			<div class="lccl-df__total">
				<div class="lccl-df__total-label">
					<span><?php esc_html_e( 'Donation Total:', 'lccl-de' ); ?></span>
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

		<button class="lccl-bdf__submit lccl-df__submit" type="submit">
			<?php esc_html_e( 'DONATE NOW', 'lccl-de' ); ?>
		</button>
	</form>
</div>
