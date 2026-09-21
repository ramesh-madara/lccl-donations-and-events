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
$required_msg = LCCL_DE_Donation_Form::required_field_message();
?>
<div class="lccl-bdf lccl-bdf--donation">
	<form
		class="lccl-bdf__form"
		method="post"
		action="#"
		data-required-message="<?php echo esc_attr( $required_msg ); ?>"
		data-amount-message="<?php echo esc_attr( LCCL_DE_Donation_Form::amount_error_message() ); ?>"
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

		<header class="lccl-bdf__header">
			<h2 class="lccl-bdf__title"><?php echo esc_html( $atts['title'] ); ?></h2>
			<?php if ( '' !== $atts['intro'] ) : ?>
				<p class="lccl-bdf__intro"><?php echo esc_html( $atts['intro'] ); ?></p>
			<?php endif; ?>
		</header>

		<p class="lccl-bdf__required-note">
			<?php
			printf(
				/* translators: %s: required field asterisk. */
				esc_html__( 'Fields marked with an %s are required', 'lccl-de' ),
				'<span class="lccl-bdf__req">*</span>'
			);
			?>
		</p>

		<div class="lccl-bdf__grid">

			<div class="lccl-bdf__field lccl-bdf__field--full<?php echo esc_attr( $invalid( 'amount' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-df-amount">
					<?php esc_html_e( 'Amount', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<div class="lccl-bdf__amount">
					<input
						class="lccl-bdf__input"
						type="text"
						id="lccl-df-amount"
						name="amount"
						value="<?php echo esc_attr( $val( 'amount' ) ); ?>"
						placeholder="<?php esc_attr_e( '5000', 'lccl-de' ); ?>"
						inputmode="decimal"
						autocomplete="off"
						maxlength="12"
						data-lccl-validate="amount"
						data-invalid-message="<?php echo esc_attr( LCCL_DE_Donation_Form::amount_error_message() ); ?>"
						required
					>
					<span class="lccl-bdf__amount-suffix" aria-hidden="true"><?php esc_html_e( 'LKR', 'lccl-de' ); ?></span>
				</div>
				<?php $notice( 'amount' ); ?>
			</div>

			<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'membership_id' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-df-membership-id">
					<?php esc_html_e( 'Membership ID', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<input
					class="lccl-bdf__input"
					type="text"
					id="lccl-df-membership-id"
					name="membership_id"
					value="<?php echo esc_attr( $val( 'membership_id' ) ); ?>"
					placeholder="<?php esc_attr_e( '123456', 'lccl-de' ); ?>"
					autocomplete="off"
					maxlength="50"
					required
				>
				<?php $notice( 'membership_id' ); ?>
			</div>

			<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'name' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-df-name">
					<?php esc_html_e( 'Name', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<input
					class="lccl-bdf__input"
					type="text"
					id="lccl-df-name"
					name="name"
					value="<?php echo esc_attr( $val( 'name' ) ); ?>"
					placeholder="<?php esc_attr_e( 'Saman Perera', 'lccl-de' ); ?>"
					autocomplete="name"
					maxlength="191"
					required
				>
				<?php $notice( 'name' ); ?>
			</div>

			<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'clubid' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-df-club-id">
					<?php esc_html_e( 'Club ID', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<input
					class="lccl-bdf__input"
					type="text"
					id="lccl-df-club-id"
					name="clubid"
					value="<?php echo esc_attr( $val( 'clubid' ) ); ?>"
					placeholder="<?php esc_attr_e( '306D6', 'lccl-de' ); ?>"
					autocomplete="off"
					maxlength="50"
					required
				>
				<?php $notice( 'clubid' ); ?>
			</div>

			<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'clubname' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-df-club-name">
					<?php esc_html_e( 'Club Name', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<input
					class="lccl-bdf__input"
					type="text"
					id="lccl-df-club-name"
					name="clubname"
					value="<?php echo esc_attr( $val( 'clubname' ) ); ?>"
					placeholder="<?php esc_attr_e( 'Lions Club of Colombo LEADS', 'lccl-de' ); ?>"
					autocomplete="organization"
					maxlength="191"
					required
				>
				<?php $notice( 'clubname' ); ?>
			</div>

			<div class="lccl-bdf__field lccl-bdf__field--full<?php echo esc_attr( $invalid( 'message' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-df-message">
					<?php esc_html_e( 'Message', 'lccl-de' ); ?>
				</label>
				<textarea
					class="lccl-bdf__textarea"
					id="lccl-df-message"
					name="message"
					placeholder="<?php esc_attr_e( 'Optional message for the club', 'lccl-de' ); ?>"
					maxlength="1000"
					rows="4"
				><?php echo esc_textarea( $val( 'message' ) ); ?></textarea>
				<?php $notice( 'message' ); ?>
			</div>

		</div>

		<button class="lccl-bdf__submit" type="submit">
			<?php esc_html_e( 'PAY', 'lccl-de' ); ?>
		</button>
	</form>
</div>
