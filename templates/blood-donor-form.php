<?php
/**
 * Blood donor registration form markup.
 *
 * @package LCCL_Donations_And_Events
 *
 * @var array $atts    Shortcode attributes.
 * @var array $values  Previously submitted values, keyed by field name.
 * @var array $errors  Validation errors, keyed by field name.
 * @var bool  $success Whether the last submit succeeded.
 */

defined( 'ABSPATH' ) || exit;

$form = 'LCCL_DE_Blood_Donor_Form';
$val  = static function ( $key ) use ( $values ) {
	return isset( $values[ $key ] ) ? $values[ $key ] : '';
};
$err  = static function ( $key ) use ( $errors ) {
	return isset( $errors[ $key ] ) ? $errors[ $key ] : '';
};
$invalid = static function ( $key ) use ( $errors ) {
	return isset( $errors[ $key ] ) ? ' lccl-bdf__field--invalid' : '';
};
?>
<div class="lccl-bdf">
	<form
		class="lccl-bdf__form"
		method="post"
		action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
		novalidate
	>

		<?php if ( ! empty( $success ) ) : ?>
			<p class="lccl-bdf__banner lccl-bdf__banner--success" role="status">
				<span class="lccl-bdf__banner-mark" aria-hidden="true"></span>
				<span class="lccl-bdf__banner-copy">
					<strong class="lccl-bdf__banner-title"><?php esc_html_e( 'Thank you.', 'lccl-de' ); ?></strong>
					<span class="lccl-bdf__banner-text"><?php esc_html_e( 'Your registration has been received.', 'lccl-de' ); ?></span>
				</span>
			</p>
		<?php endif; ?>

		<?php if ( ! empty( $errors['form'] ) ) : ?>
			<p class="lccl-bdf__banner lccl-bdf__banner--error" role="alert">
				<?php echo esc_html( $errors['form'] ); ?>
			</p>
		<?php endif; ?>

		<p class="lccl-bdf__banner lccl-bdf__banner--error" data-lccl-notice="required" role="alert" hidden>
			<?php esc_html_e( 'Please fill in all required fields marked with an asterisk.', 'lccl-de' ); ?>
		</p>

		<header class="lccl-bdf__header">
			<h2 class="lccl-bdf__title"><?php echo esc_html( $atts['title'] ); ?></h2>
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

		<div class="lccl-bdf__grid">

			<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'first_name' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-bdf-first-name">
					<?php esc_html_e( 'First Name', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<input
					class="lccl-bdf__input"
					type="text"
					id="lccl-bdf-first-name"
					name="first_name"
					value="<?php echo esc_attr( $val( 'first_name' ) ); ?>"
					placeholder="<?php esc_attr_e( 'John', 'lccl-de' ); ?>"
					autocomplete="given-name"
					maxlength="100"
					required
				>
				<?php if ( $err( 'first_name' ) ) : ?>
					<p class="lccl-bdf__notice"><?php echo esc_html( $err( 'first_name' ) ); ?></p>
				<?php endif; ?>
			</div>

			<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'last_name' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-bdf-last-name">
					<?php esc_html_e( 'Last Name', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<input
					class="lccl-bdf__input"
					type="text"
					id="lccl-bdf-last-name"
					name="last_name"
					value="<?php echo esc_attr( $val( 'last_name' ) ); ?>"
					placeholder="<?php esc_attr_e( 'Perera', 'lccl-de' ); ?>"
					autocomplete="family-name"
					maxlength="100"
					required
				>
				<?php if ( $err( 'last_name' ) ) : ?>
					<p class="lccl-bdf__notice"><?php echo esc_html( $err( 'last_name' ) ); ?></p>
				<?php endif; ?>
			</div>

			<div class="lccl-bdf__field lccl-bdf__field--full<?php echo esc_attr( $invalid( 'address' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-bdf-address">
					<?php esc_html_e( 'Address', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<input
					class="lccl-bdf__input"
					type="text"
					id="lccl-bdf-address"
					name="address"
					value="<?php echo esc_attr( $val( 'address' ) ); ?>"
					placeholder="<?php esc_attr_e( 'No. 25, Main Street', 'lccl-de' ); ?>"
					autocomplete="street-address"
					maxlength="255"
					required
				>
				<?php if ( $err( 'address' ) ) : ?>
					<p class="lccl-bdf__notice"><?php echo esc_html( $err( 'address' ) ); ?></p>
				<?php endif; ?>
			</div>

			<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'city' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-bdf-city">
					<?php esc_html_e( 'City', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<input
					class="lccl-bdf__input"
					type="text"
					id="lccl-bdf-city"
					name="city"
					value="<?php echo esc_attr( $val( 'city' ) ); ?>"
					placeholder="<?php esc_attr_e( 'Colombo', 'lccl-de' ); ?>"
					autocomplete="address-level2"
					maxlength="100"
					required
				>
				<?php if ( $err( 'city' ) ) : ?>
					<p class="lccl-bdf__notice"><?php echo esc_html( $err( 'city' ) ); ?></p>
				<?php endif; ?>
			</div>

			<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'postal_code' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-bdf-postal-code">
					<?php esc_html_e( 'Postal Code', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<input
					class="lccl-bdf__input"
					type="text"
					id="lccl-bdf-postal-code"
					name="postal_code"
					value="<?php echo esc_attr( $val( 'postal_code' ) ); ?>"
					placeholder="<?php esc_attr_e( '00100', 'lccl-de' ); ?>"
					inputmode="numeric"
					autocomplete="postal-code"
					maxlength="5"
					pattern="[0-9]{5}"
					data-lccl-validate="postal"
					data-invalid-message="<?php echo esc_attr( LCCL_DE_Blood_Donor_Submissions::postal_error_message() ); ?>"
					required
				>
				<p
					class="lccl-bdf__notice"
					data-lccl-notice="postal_code"
					role="alert"
					<?php echo $err( 'postal_code' ) ? '' : 'hidden'; ?>
				><?php echo $err( 'postal_code' ) ? esc_html( $err( 'postal_code' ) ) : ''; ?></p>
			</div>

			<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'email' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-bdf-email">
					<?php esc_html_e( 'Email', 'lccl-de' ); ?>
				</label>
				<input
					class="lccl-bdf__input"
					type="email"
					id="lccl-bdf-email"
					name="email"
					value="<?php echo esc_attr( $val( 'email' ) ); ?>"
					placeholder="<?php esc_attr_e( 'name@example.com', 'lccl-de' ); ?>"
					autocomplete="email"
					maxlength="191"
					data-lccl-validate="email"
					data-invalid-message="<?php echo esc_attr( LCCL_DE_Blood_Donor_Submissions::email_error_message() ); ?>"
					data-required-message="<?php echo esc_attr( __( 'Please enter an email address so we can contact you by email.', 'lccl-de' ) ); ?>"
				>
				<p
					class="lccl-bdf__notice"
					data-lccl-notice="email"
					role="alert"
					<?php echo $err( 'email' ) ? '' : 'hidden'; ?>
				><?php echo $err( 'email' ) ? esc_html( $err( 'email' ) ) : ''; ?></p>
			</div>

			<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'phone' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-bdf-phone">
					<?php esc_html_e( 'Phone', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<input
					class="lccl-bdf__input"
					type="tel"
					id="lccl-bdf-phone"
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
				<p
					class="lccl-bdf__notice"
					data-lccl-notice="phone"
					role="alert"
					<?php echo $err( 'phone' ) ? '' : 'hidden'; ?>
				><?php echo $err( 'phone' ) ? esc_html( $err( 'phone' ) ) : ''; ?></p>
			</div>

			<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'district' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-bdf-district">
					<?php esc_html_e( 'District', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<select class="lccl-bdf__select" id="lccl-bdf-district" name="district" required>
					<option value=""><?php esc_html_e( 'Select your district', 'lccl-de' ); ?></option>
					<?php $form::render_options( $form::get_districts(), $val( 'district' ) ); ?>
				</select>
				<?php if ( $err( 'district' ) ) : ?>
					<p class="lccl-bdf__notice"><?php echo esc_html( $err( 'district' ) ); ?></p>
				<?php endif; ?>
			</div>

			<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'blood_bank' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-bdf-blood-bank">
					<?php esc_html_e( 'Preferred blood bank', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<?php
				/*
				 * Every district's banks are rendered as optgroups so the field
				 * still works without JavaScript. The script keeps only the
				 * group matching the selected district.
				 */
				?>
				<select
					class="lccl-bdf__select"
					id="lccl-bdf-blood-bank"
					name="blood_bank"
					aria-describedby="lccl-bdf-blood-bank-notice"
					data-locked-label="<?php esc_attr_e( 'Select your district first', 'lccl-de' ); ?>"
					data-ready-label="<?php esc_attr_e( 'Select your preferred blood bank', 'lccl-de' ); ?>"
					required
				>
					<option value=""><?php esc_html_e( 'Select your district first', 'lccl-de' ); ?></option>
					<?php foreach ( $form::get_blood_banks_by_district() as $district_name => $district_banks ) : ?>
						<optgroup
							label="<?php echo esc_attr( $district_name ); ?>"
							data-district="<?php echo esc_attr( $district_name ); ?>"
						>
							<?php $form::render_options( $district_banks, $val( 'blood_bank' ) ); ?>
						</optgroup>
					<?php endforeach; ?>
				</select>
				<p
					class="lccl-bdf__notice"
					id="lccl-bdf-blood-bank-notice"
					data-lccl-notice="blood_bank"
					role="status"
					<?php echo $err( 'blood_bank' ) ? '' : 'hidden'; ?>
				>
					<?php echo $err( 'blood_bank' ) ? esc_html( $err( 'blood_bank' ) ) : esc_html__( 'Please choose your district first, then pick a blood bank.', 'lccl-de' ); ?>
				</p>
			</div>

			<div class="lccl-bdf__field">
				<label class="lccl-bdf__label" for="lccl-bdf-donation-preference">
					<?php esc_html_e( 'How would you prefer to donate?', 'lccl-de' ); ?>
				</label>
				<select class="lccl-bdf__select" id="lccl-bdf-donation-preference" name="donation_preference">
					<option value=""><?php esc_html_e( 'Select your preferred option', 'lccl-de' ); ?></option>
					<?php $form::render_options( $form::get_donation_preferences(), $val( 'donation_preference' ) ); ?>
				</select>
			</div>

			<div class="lccl-bdf__field">
				<label class="lccl-bdf__label" for="lccl-bdf-donated-before">
					<?php esc_html_e( 'Have you donated blood before?', 'lccl-de' ); ?>
				</label>
				<select class="lccl-bdf__select" id="lccl-bdf-donated-before" name="donated_before">
					<option value=""><?php esc_html_e( 'Select an option', 'lccl-de' ); ?></option>
					<?php $form::render_options( $form::get_donation_history_options(), $val( 'donated_before' ) ); ?>
				</select>
			</div>

			<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'contact_method' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-bdf-contact-method">
					<?php esc_html_e( 'Preferred Contact Method', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<select class="lccl-bdf__select" id="lccl-bdf-contact-method" name="contact_method" required>
					<option value=""><?php esc_html_e( 'Select a contact method', 'lccl-de' ); ?></option>
					<?php $form::render_options( $form::get_contact_methods(), $val( 'contact_method' ) ); ?>
				</select>
				<?php if ( $err( 'contact_method' ) ) : ?>
					<p class="lccl-bdf__notice"><?php echo esc_html( $err( 'contact_method' ) ); ?></p>
				<?php endif; ?>
			</div>

			<div class="lccl-bdf__field lccl-bdf__field--full">
				<label class="lccl-bdf__checkbox">
					<input
						type="checkbox"
						name="notify_campaigns"
						value="1"
						<?php checked( (int) $val( 'notify_campaigns' ), 1 ); ?>
					>
					<span class="lccl-bdf__check" aria-hidden="true"></span>
					<span><?php esc_html_e( 'Notify me about upcoming Lions blood donation campaigns', 'lccl-de' ); ?></span>
				</label>
			</div>

		</div>

		<section class="lccl-bdf__consent">
			<h3 class="lccl-bdf__subtitle"><?php esc_html_e( 'Privacy &amp; Consent', 'lccl-de' ); ?></h3>

			<p class="lccl-bdf__privacy">
				<?php
				esc_html_e(
					'The information provided will be used by Colombo LEADS to facilitate blood donation by connecting registered donors with relevant blood banks and Lions blood donation campaigns. Your information will be handled securely and used only for the purposes stated. This registration does not constitute a medical eligibility assessment. Donor eligibility will be determined by the relevant blood bank. You may withdraw your consent to receive optional communications at any time.',
					'lccl-de'
				);
				?>
			</p>

			<label class="lccl-bdf__checkbox<?php echo esc_attr( $invalid( 'consent' ) ); ?>">
				<input
					type="checkbox"
					name="consent"
					value="1"
					<?php checked( (int) $val( 'consent' ), 1 ); ?>
					required
				>
				<span class="lccl-bdf__check" aria-hidden="true"></span>
				<span>
					<?php esc_html_e( 'I consent to Colombo LEADS collecting and using my personal information for the purposes stated above.', 'lccl-de' ); ?>
					<span class="lccl-bdf__req">*</span>
				</span>
			</label>
			<?php if ( $err( 'consent' ) ) : ?>
				<p class="lccl-bdf__notice"><?php echo esc_html( $err( 'consent' ) ); ?></p>
			<?php endif; ?>
		</section>

		<div class="lccl-bdf__hp" aria-hidden="true">
			<label for="lccl-bdf-hp"><?php esc_html_e( 'Website', 'lccl-de' ); ?></label>
			<input type="text" id="lccl-bdf-hp" name="lccl_de_hp" value="" tabindex="-1" autocomplete="off">
		</div>

		<input type="hidden" name="action" value="<?php echo esc_attr( LCCL_DE_Blood_Donor_Submissions::ACTION ); ?>">
		<input type="hidden" name="redirect_to" value="<?php echo esc_url( get_permalink() ? get_permalink() : home_url( '/' ) ); ?>">
		<?php wp_nonce_field( LCCL_DE_Blood_Donor_Submissions::ACTION, 'lccl_de_nonce' ); ?>

		<button class="lccl-bdf__submit" type="submit">
			<?php esc_html_e( 'Register', 'lccl-de' ); ?>
		</button>

	</form>
</div>
