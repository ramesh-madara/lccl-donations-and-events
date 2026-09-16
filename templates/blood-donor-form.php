<?php
/**
 * Blood donor registration form markup.
 *
 * @package LCCL_Donations_And_Events
 *
 * @var array $atts   Shortcode attributes.
 * @var array $values Previously submitted values, keyed by field name.
 * @var array $errors Validation errors, keyed by field name.
 */

defined( 'ABSPATH' ) || exit;

$form = 'LCCL_DE_Blood_Donor_Form';
$val  = static function ( $key ) use ( $values ) {
	return isset( $values[ $key ] ) ? $values[ $key ] : '';
};
?>
<div class="lccl-bdf">
	<form class="lccl-bdf__form" method="post" novalidate>

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

			<div class="lccl-bdf__field">
				<label class="lccl-bdf__label" for="lccl-bdf-first-name">
					<?php esc_html_e( 'First Name', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<input
					class="lccl-bdf__input"
					type="text"
					id="lccl-bdf-first-name"
					name="first_name"
					value="<?php echo esc_attr( $val( 'first_name' ) ); ?>"
					autocomplete="given-name"
					required
				>
			</div>

			<div class="lccl-bdf__field">
				<label class="lccl-bdf__label" for="lccl-bdf-last-name">
					<?php esc_html_e( 'Last Name', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<input
					class="lccl-bdf__input"
					type="text"
					id="lccl-bdf-last-name"
					name="last_name"
					value="<?php echo esc_attr( $val( 'last_name' ) ); ?>"
					autocomplete="family-name"
					required
				>
			</div>

			<div class="lccl-bdf__field lccl-bdf__field--full">
				<label class="lccl-bdf__label" for="lccl-bdf-address">
					<?php esc_html_e( 'Address', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<input
					class="lccl-bdf__input"
					type="text"
					id="lccl-bdf-address"
					name="address"
					value="<?php echo esc_attr( $val( 'address' ) ); ?>"
					autocomplete="street-address"
					required
				>
			</div>

			<div class="lccl-bdf__field">
				<label class="lccl-bdf__label" for="lccl-bdf-city">
					<?php esc_html_e( 'City', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<input
					class="lccl-bdf__input"
					type="text"
					id="lccl-bdf-city"
					name="city"
					value="<?php echo esc_attr( $val( 'city' ) ); ?>"
					autocomplete="address-level2"
					required
				>
			</div>

			<div class="lccl-bdf__field">
				<label class="lccl-bdf__label" for="lccl-bdf-postal-code">
					<?php esc_html_e( 'Postal Code', 'lccl-de' ); ?>
				</label>
				<input
					class="lccl-bdf__input"
					type="text"
					id="lccl-bdf-postal-code"
					name="postal_code"
					value="<?php echo esc_attr( $val( 'postal_code' ) ); ?>"
					inputmode="numeric"
					autocomplete="postal-code"
				>
			</div>

			<div class="lccl-bdf__field">
				<label class="lccl-bdf__label" for="lccl-bdf-email">
					<?php esc_html_e( 'Email', 'lccl-de' ); ?>
				</label>
				<input
					class="lccl-bdf__input"
					type="email"
					id="lccl-bdf-email"
					name="email"
					value="<?php echo esc_attr( $val( 'email' ) ); ?>"
					autocomplete="email"
				>
			</div>

			<div class="lccl-bdf__field">
				<label class="lccl-bdf__label" for="lccl-bdf-phone">
					<?php esc_html_e( 'Phone', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<input
					class="lccl-bdf__input"
					type="tel"
					id="lccl-bdf-phone"
					name="phone"
					value="<?php echo esc_attr( $val( 'phone' ) ); ?>"
					placeholder="+94 71 0000000"
					inputmode="tel"
					autocomplete="tel"
					required
				>
			</div>

			<div class="lccl-bdf__field">
				<label class="lccl-bdf__label" for="lccl-bdf-district">
					<?php esc_html_e( 'District', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<select class="lccl-bdf__select" id="lccl-bdf-district" name="district" required>
					<option value=""></option>
					<?php $form::render_options( $form::get_districts(), $val( 'district' ) ); ?>
				</select>
			</div>

			<div class="lccl-bdf__field">
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
					data-ready-label=""
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
					hidden
				>
					<?php esc_html_e( 'Please choose your district first, then pick a blood bank.', 'lccl-de' ); ?>
				</p>
			</div>

			<div class="lccl-bdf__field">
				<label class="lccl-bdf__label" for="lccl-bdf-donation-preference">
					<?php esc_html_e( 'How would you prefer to donate?', 'lccl-de' ); ?>
				</label>
				<select class="lccl-bdf__select" id="lccl-bdf-donation-preference" name="donation_preference">
					<option value=""></option>
					<?php $form::render_options( $form::get_donation_preferences(), $val( 'donation_preference' ) ); ?>
				</select>
			</div>

			<div class="lccl-bdf__field">
				<label class="lccl-bdf__label" for="lccl-bdf-donated-before">
					<?php esc_html_e( 'Have you donated blood before?', 'lccl-de' ); ?>
				</label>
				<select class="lccl-bdf__select" id="lccl-bdf-donated-before" name="donated_before">
					<option value=""></option>
					<?php $form::render_options( $form::get_donation_history_options(), $val( 'donated_before' ) ); ?>
				</select>
			</div>

			<div class="lccl-bdf__field">
				<label class="lccl-bdf__label" for="lccl-bdf-contact-method">
					<?php esc_html_e( 'Preferred Contact Method', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<select class="lccl-bdf__select" id="lccl-bdf-contact-method" name="contact_method" required>
					<option value=""></option>
					<?php $form::render_options( $form::get_contact_methods(), $val( 'contact_method' ) ); ?>
				</select>
			</div>

			<div class="lccl-bdf__field lccl-bdf__field--full">
				<label class="lccl-bdf__checkbox">
					<input
						type="checkbox"
						name="notify_campaigns"
						value="1"
						<?php checked( $val( 'notify_campaigns' ), '1' ); ?>
					>
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

			<label class="lccl-bdf__checkbox">
				<input
					type="checkbox"
					name="consent"
					value="1"
					<?php checked( $val( 'consent' ), '1' ); ?>
					required
				>
				<span>
					<?php esc_html_e( 'I consent to Colombo LEADS collecting and using my personal information for the purposes stated above.', 'lccl-de' ); ?>
					<span class="lccl-bdf__req">*</span>
				</span>
			</label>
		</section>

		<?php wp_nonce_field( 'lccl_de_blood_donor_register', 'lccl_de_nonce' ); ?>

		<button class="lccl-bdf__submit" type="submit">
			<?php esc_html_e( 'Register', 'lccl-de' ); ?>
		</button>

	</form>
</div>
