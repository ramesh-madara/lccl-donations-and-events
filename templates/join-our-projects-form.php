<?php
/**
 * Join Our Projects registration form markup.
 *
 * @package LCCL_Donations_And_Events
 *
 * @var array $atts    Shortcode attributes.
 * @var array $values  Previously submitted values, keyed by field name.
 * @var array $errors  Validation errors, keyed by field name.
 * @var bool  $success Whether the last submit succeeded.
 */

defined( 'ABSPATH' ) || exit;

$form = 'LCCL_DE_Join_Projects_Form';
$val  = static function ( $key ) use ( $values ) {
	return isset( $values[ $key ] ) ? $values[ $key ] : '';
};
$list = static function ( $key ) use ( $values ) {
	return isset( $values[ $key ] ) && is_array( $values[ $key ] ) ? $values[ $key ] : array();
};
$err  = static function ( $key ) use ( $errors ) {
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
$required_msg = LCCL_DE_Blood_Donor_Submissions::required_field_message();
$show_volunteer    = $form::needs_volunteer( $values );
$show_financial    = $form::needs_financial( $values );
$show_organisation = $form::needs_organisation( $values );
?>
<div class="lccl-bdf lccl-bdf--join">
	<form
		class="lccl-bdf__form"
		method="post"
		action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
		data-required-message="<?php echo esc_attr( $required_msg ); ?>"
		data-choice-message="<?php esc_attr_e( 'Please select at least one support option and at least one project/service area.', 'lccl-de' ); ?>"
		data-lccl-form="join-projects"
		novalidate
	>

		<?php if ( ! empty( $success ) ) : ?>
			<p class="lccl-bdf__banner lccl-bdf__banner--success" role="status">
				<span class="lccl-bdf__banner-mark" aria-hidden="true"></span>
				<span class="lccl-bdf__banner-copy">
					<strong class="lccl-bdf__banner-title"><?php esc_html_e( 'Thank you for registering.', 'lccl-de' ); ?></strong>
					<span class="lccl-bdf__banner-text"><?php esc_html_e( 'Your registration has been successfully received.', 'lccl-de' ); ?></span>
				</span>
			</p>
		<?php endif; ?>

		<?php if ( ! empty( $errors['form'] ) ) : ?>
			<p class="lccl-bdf__banner lccl-bdf__banner--error" role="alert">
				<?php echo esc_html( $errors['form'] ); ?>
			</p>
		<?php endif; ?>

		<p class="lccl-bdf__banner lccl-bdf__banner--error" data-lccl-notice="required" role="alert" hidden>
			<?php esc_html_e( 'Please complete all fields marked with an *.', 'lccl-de' ); ?>
		</p>
		<p class="lccl-bdf__banner lccl-bdf__banner--error" data-lccl-notice="choices" role="alert"<?php echo empty( $errors['support_ways'] ) && empty( $errors['interest_areas'] ) ? ' hidden' : ''; ?>>
			<?php echo esc_html( ! empty( $errors['support_ways'] ) ? $errors['support_ways'] : ( ! empty( $errors['interest_areas'] ) ? $errors['interest_areas'] : '' ) ); ?>
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

		<section class="lccl-bdf__section">
			<h3 class="lccl-bdf__section-title"><?php esc_html_e( '1. Personal Information', 'lccl-de' ); ?></h3>
			<p class="lccl-bdf__section-lede"><?php esc_html_e( 'Please provide your contact details so our team can get in touch with you.', 'lccl-de' ); ?></p>

			<div class="lccl-bdf__grid">
				<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'full_name' ) ); ?>">
					<label class="lccl-bdf__label" for="lccl-jpf-full-name">
						<?php esc_html_e( 'Full Name', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
					</label>
					<input
						class="lccl-bdf__input"
						type="text"
						id="lccl-jpf-full-name"
						name="full_name"
						value="<?php echo esc_attr( $val( 'full_name' ) ); ?>"
						autocomplete="name"
						maxlength="191"
						required
					>
					<?php $notice( 'full_name' ); ?>
				</div>

				<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'email' ) ); ?>">
					<label class="lccl-bdf__label" for="lccl-jpf-email">
						<?php esc_html_e( 'Email Address', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
					</label>
					<input
						class="lccl-bdf__input"
						type="email"
						id="lccl-jpf-email"
						name="email"
						value="<?php echo esc_attr( $val( 'email' ) ); ?>"
						autocomplete="email"
						maxlength="191"
						data-lccl-validate="email"
						data-invalid-message="<?php esc_attr_e( 'Please enter a valid email address.', 'lccl-de' ); ?>"
						required
					>
					<?php $notice( 'email' ); ?>
				</div>

				<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'phone' ) ); ?>">
					<label class="lccl-bdf__label" for="lccl-jpf-phone">
						<?php esc_html_e( 'Mobile / WhatsApp Number', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
					</label>
					<input
						class="lccl-bdf__input"
						type="tel"
						id="lccl-jpf-phone"
						name="phone"
						value="<?php echo esc_attr( $val( 'phone' ) ); ?>"
						autocomplete="tel"
						inputmode="tel"
						data-lccl-validate="phone"
						data-invalid-message="<?php echo esc_attr( LCCL_DE_Blood_Donor_Submissions::phone_error_message() ); ?>"
						required
					>
					<?php $notice( 'phone' ); ?>
				</div>

				<div class="lccl-bdf__field">
					<label class="lccl-bdf__label" for="lccl-jpf-city"><?php esc_html_e( 'City / Area', 'lccl-de' ); ?></label>
					<input class="lccl-bdf__input" type="text" id="lccl-jpf-city" name="city" value="<?php echo esc_attr( $val( 'city' ) ); ?>" autocomplete="address-level2" maxlength="100">
				</div>

				<div class="lccl-bdf__field">
					<label class="lccl-bdf__label" for="lccl-jpf-occupation"><?php esc_html_e( 'Occupation / Profession', 'lccl-de' ); ?></label>
					<input class="lccl-bdf__input" type="text" id="lccl-jpf-occupation" name="occupation" value="<?php echo esc_attr( $val( 'occupation' ) ); ?>" autocomplete="organization-title" maxlength="191">
				</div>

				<div class="lccl-bdf__field">
					<label class="lccl-bdf__label" for="lccl-jpf-organisation"><?php esc_html_e( 'Organization / Company', 'lccl-de' ); ?></label>
					<input class="lccl-bdf__input" type="text" id="lccl-jpf-organisation" name="organisation" value="<?php echo esc_attr( $val( 'organisation' ) ); ?>" autocomplete="organization" maxlength="191">
				</div>
			</div>
		</section>

		<section class="lccl-bdf__section" data-lccl-section="support">
			<h3 class="lccl-bdf__section-title"><?php esc_html_e( '2. How Would You Like to Support Us?', 'lccl-de' ); ?></h3>
			<p class="lccl-bdf__section-lede"><?php esc_html_e( 'Select all that apply.', 'lccl-de' ); ?></p>
			<?php $form::render_choices( 'support_ways', $form::support_ways(), $list( 'support_ways' ) ); ?>

			<div class="lccl-bdf__conditional" data-lccl-conditional="volunteer"<?php echo $show_volunteer ? '' : ' hidden'; ?>>
				<h4 class="lccl-bdf__conditional-title"><?php esc_html_e( 'Volunteer Your Time & Skills', 'lccl-de' ); ?></h4>
				<p class="lccl-bdf__section-lede"><?php esc_html_e( 'Tell us how you would like to participate in projects.', 'lccl-de' ); ?></p>
				<label class="lccl-bdf__label"><?php esc_html_e( 'Volunteer / Skill Areas', 'lccl-de' ); ?></label>
				<?php $form::render_choices( 'volunteer_areas', $form::volunteer_areas(), $list( 'volunteer_areas' ) ); ?>

				<div class="lccl-bdf__field lccl-bdf__field--full lccl-bdf__field--idea">
					<label class="lccl-bdf__label" for="lccl-jpf-skills"><?php esc_html_e( 'Your Skills / Expertise', 'lccl-de' ); ?></label>
					<textarea class="lccl-bdf__textarea" id="lccl-jpf-skills" name="skills" rows="4" maxlength="2000" placeholder="<?php esc_attr_e( 'For example: doctor, nurse, accountant, engineer, IT professional, teacher, photographer, driver, event organizer, etc.', 'lccl-de' ); ?>"><?php echo esc_textarea( $val( 'skills' ) ); ?></textarea>
				</div>

				<div class="lccl-bdf__field lccl-bdf__field--full lccl-bdf__field--idea">
					<label class="lccl-bdf__label"><?php esc_html_e( 'When Are You Generally Available?', 'lccl-de' ); ?></label>
					<?php $form::render_choices( 'availability', $form::availability(), $list( 'availability' ) ); ?>
				</div>
			</div>

			<div class="lccl-bdf__conditional" data-lccl-conditional="financial"<?php echo $show_financial ? '' : ' hidden'; ?>>
				<h4 class="lccl-bdf__conditional-title"><?php esc_html_e( 'Financial Support', 'lccl-de' ); ?></h4>
				<p class="lccl-bdf__section-lede"><?php esc_html_e( 'Financial contributions can help us plan and deliver community projects. This information is optional and does not constitute a payment.', 'lccl-de' ); ?></p>
				<label class="lccl-bdf__label"><?php esc_html_e( 'How Would You Prefer to Contribute?', 'lccl-de' ); ?></label>
				<?php $form::render_choices( 'financial_support', $form::financial_support(), $list( 'financial_support' ) ); ?>

				<div class="lccl-bdf__field lccl-bdf__field--idea<?php echo esc_attr( $invalid( 'contribution_amount' ) ); ?>">
					<label class="lccl-bdf__label" for="lccl-jpf-amount">
						<?php esc_html_e( 'Estimated Contribution', 'lccl-de' ); ?>
						<span class="lccl-bdf__optional"><?php esc_html_e( '(Optional)', 'lccl-de' ); ?></span>
					</label>
					<select class="lccl-bdf__select" id="lccl-jpf-amount" name="contribution_amount">
						<option value=""><?php esc_html_e( 'Please select', 'lccl-de' ); ?></option>
						<?php LCCL_DE_Blood_Donor_Form::render_options( $form::contribution_amounts(), $val( 'contribution_amount' ) ); ?>
					</select>
					<?php $notice( 'contribution_amount' ); ?>
				</div>
			</div>
		</section>

		<section class="lccl-bdf__section" data-lccl-section="areas">
			<h3 class="lccl-bdf__section-title"><?php esc_html_e( '3. Areas You Would Like to Support', 'lccl-de' ); ?></h3>
			<p class="lccl-bdf__section-lede"><?php esc_html_e( 'Select all project or service areas that interest you.', 'lccl-de' ); ?></p>
			<?php $form::render_choices( 'interest_areas', $form::interest_areas(), $list( 'interest_areas' ) ); ?>
		</section>

		<section class="lccl-bdf__section">
			<h3 class="lccl-bdf__section-title"><?php esc_html_e( '4. Project-Specific Support', 'lccl-de' ); ?></h3>
			<p class="lccl-bdf__section-lede"><?php esc_html_e( 'If there is a particular type of project you would like to support, please let us know.', 'lccl-de' ); ?></p>
			<?php $form::render_choices( 'project_types', $form::project_types(), $list( 'project_types' ) ); ?>

			<div class="lccl-bdf__field lccl-bdf__field--full lccl-bdf__field--idea">
				<label class="lccl-bdf__label" for="lccl-jpf-specific-idea"><?php esc_html_e( 'Specific Project or Idea', 'lccl-de' ); ?></label>
				<textarea class="lccl-bdf__textarea" id="lccl-jpf-specific-idea" name="specific_idea" rows="4" maxlength="2000" placeholder="<?php esc_attr_e( 'Tell us if you have a particular project, beneficiary group, activity or idea you would like to support.', 'lccl-de' ); ?>"><?php echo esc_textarea( $val( 'specific_idea' ) ); ?></textarea>
			</div>
		</section>

		<section class="lccl-bdf__section">
			<h3 class="lccl-bdf__section-title"><?php esc_html_e( '5. About Your Registration', 'lccl-de' ); ?></h3>
			<p class="lccl-bdf__section-lede"><?php esc_html_e( 'This helps us understand whether you are registering individually or on behalf of an organization.', 'lccl-de' ); ?></p>

			<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'registering_as' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-jpf-registering-as"><?php esc_html_e( 'I Am Registering As', 'lccl-de' ); ?></label>
				<select class="lccl-bdf__select" id="lccl-jpf-registering-as" name="registering_as">
					<option value=""><?php esc_html_e( 'Please select', 'lccl-de' ); ?></option>
					<?php LCCL_DE_Blood_Donor_Form::render_options( $form::registering_as_options(), $val( 'registering_as' ) ); ?>
				</select>
				<?php $notice( 'registering_as' ); ?>
			</div>

			<div class="lccl-bdf__conditional" data-lccl-conditional="organisation"<?php echo $show_organisation ? '' : ' hidden'; ?>>
				<h4 class="lccl-bdf__conditional-title"><?php esc_html_e( 'Organization / Corporate Support', 'lccl-de' ); ?></h4>
				<p class="lccl-bdf__section-lede"><?php esc_html_e( 'If you represent an organization, please provide some additional information.', 'lccl-de' ); ?></p>
				<div class="lccl-bdf__grid">
					<div class="lccl-bdf__field">
						<label class="lccl-bdf__label" for="lccl-jpf-company-name"><?php esc_html_e( 'Organization Name', 'lccl-de' ); ?></label>
						<input class="lccl-bdf__input" type="text" id="lccl-jpf-company-name" name="company_name" value="<?php echo esc_attr( $val( 'company_name' ) ); ?>" maxlength="191">
					</div>
					<div class="lccl-bdf__field">
						<label class="lccl-bdf__label" for="lccl-jpf-designation"><?php esc_html_e( 'Position / Designation', 'lccl-de' ); ?></label>
						<input class="lccl-bdf__input" type="text" id="lccl-jpf-designation" name="designation" value="<?php echo esc_attr( $val( 'designation' ) ); ?>" maxlength="191">
					</div>
					<div class="lccl-bdf__field lccl-bdf__field--full">
						<label class="lccl-bdf__label" for="lccl-jpf-company-support"><?php esc_html_e( 'How Could Your Organization Support Community Projects?', 'lccl-de' ); ?></label>
						<textarea class="lccl-bdf__textarea" id="lccl-jpf-company-support" name="company_support" rows="4" maxlength="2000" placeholder="<?php esc_attr_e( 'For example: sponsorship, employee volunteering, equipment, professional services, venue, transportation, food, medical supplies, educational materials, etc.', 'lccl-de' ); ?>"><?php echo esc_textarea( $val( 'company_support' ) ); ?></textarea>
					</div>
				</div>
			</div>
		</section>

		<section class="lccl-bdf__section">
			<h3 class="lccl-bdf__section-title"><?php esc_html_e( '6. Additional Message', 'lccl-de' ); ?></h3>
			<p class="lccl-bdf__section-lede"><?php esc_html_e( 'Anything else you would like our team to know?', 'lccl-de' ); ?></p>
			<div class="lccl-bdf__field lccl-bdf__field--full">
				<textarea class="lccl-bdf__textarea" id="lccl-jpf-message" name="message" rows="4" maxlength="2000" placeholder="<?php esc_attr_e( 'Please share any additional information, suggestions or questions.', 'lccl-de' ); ?>"><?php echo esc_textarea( $val( 'message' ) ); ?></textarea>
			</div>
		</section>

		<section class="lccl-bdf__consent">
			<label class="lccl-bdf__checkbox<?php echo esc_attr( $invalid( 'consent' ) ); ?>">
				<input type="checkbox" name="consent" value="1" <?php checked( (int) $val( 'consent' ), 1 ); ?> required>
				<span class="lccl-bdf__check" aria-hidden="true"></span>
				<span>
					<?php esc_html_e( 'I agree to be contacted by the Lions Club of Colombo LEADS regarding community service projects, volunteering opportunities, donations, sponsorships and related activities.', 'lccl-de' ); ?>
					<span class="lccl-bdf__req">*</span>
				</span>
			</label>
			<?php $notice( 'consent' ); ?>
		</section>

		<div class="lccl-bdf__hp" aria-hidden="true">
			<label for="lccl-jpf-hp"><?php esc_html_e( 'Website', 'lccl-de' ); ?></label>
			<input type="text" id="lccl-jpf-hp" name="lccl_de_hp" value="" tabindex="-1" autocomplete="off">
		</div>

		<input type="hidden" name="action" value="<?php echo esc_attr( LCCL_DE_Join_Projects_Submissions::ACTION ); ?>">
		<input type="hidden" name="redirect_to" value="<?php echo esc_url( get_permalink() ? get_permalink() : home_url( '/' ) ); ?>">
		<?php wp_nonce_field( LCCL_DE_Join_Projects_Submissions::ACTION, 'lccl_de_nonce' ); ?>

		<div class="lccl-bdf__actions">
			<button class="lccl-bdf__submit" type="submit">
				<?php esc_html_e( 'Register Your Interest', 'lccl-de' ); ?>
			</button>
			<p class="lccl-bdf__foot-note">
				<?php esc_html_e( 'Your information will be used to respond to your registration and coordinate relevant community service opportunities.', 'lccl-de' ); ?>
			</p>
		</div>
	</form>
</div>
