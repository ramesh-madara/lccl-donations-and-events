<?php
/**
 * Free Spectacles registration form markup.
 *
 * @package LCCL_Donations_And_Events
 *
 * @var array $atts    Shortcode attributes.
 * @var array $values  Previously submitted values, keyed by field name.
 * @var array $errors  Validation errors, keyed by field name.
 * @var bool  $success Whether the last submit succeeded.
 */

defined( 'ABSPATH' ) || exit;

$form = 'LCCL_DE_Spectacles_Form';
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

$show_condition = 'yes' === $val( 'eye_condition' );
$show_other     = in_array( 'other', $list( 'vision_difficulties' ), true );
$show_letter    = 'submitted-herewith' === $val( 'school_letter' );
$required_msg   = LCCL_DE_Blood_Donor_Submissions::required_field_message();
$max_dob        = current_time( 'Y-m-d' );
$min_dob        = gmdate( 'Y-m-d', strtotime( $max_dob . ' -25 years' ) );
?>
<div class="lccl-bdf lccl-bdf--spectacles">
	<form
		class="lccl-bdf__form"
		method="post"
		action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
		enctype="multipart/form-data"
		data-required-message="<?php echo esc_attr( $required_msg ); ?>"
		data-choice-message="<?php esc_attr_e( 'Please select at least one vision difficulty.', 'lccl-de' ); ?>"
		data-letter-max="<?php echo (int) LCCL_DE_Spectacles_Submissions::LETTER_MAX_BYTES; ?>"
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

			<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'child_first_name' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-spf-child-first">
					<?php esc_html_e( 'Child\'s First Name', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<input
					class="lccl-bdf__input"
					type="text"
					id="lccl-spf-child-first"
					name="child_first_name"
					value="<?php echo esc_attr( $val( 'child_first_name' ) ); ?>"
					placeholder="<?php esc_attr_e( 'John', 'lccl-de' ); ?>"
					autocomplete="given-name"
					maxlength="100"
					required
				>
				<?php $notice( 'child_first_name' ); ?>
			</div>

			<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'child_last_name' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-spf-child-last">
					<?php esc_html_e( 'Child\'s Last Name', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<input
					class="lccl-bdf__input"
					type="text"
					id="lccl-spf-child-last"
					name="child_last_name"
					value="<?php echo esc_attr( $val( 'child_last_name' ) ); ?>"
					placeholder="<?php esc_attr_e( 'Perera', 'lccl-de' ); ?>"
					autocomplete="family-name"
					maxlength="100"
					required
				>
				<?php $notice( 'child_last_name' ); ?>
			</div>

			<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'dob' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-spf-dob">
					<?php esc_html_e( 'Date of Birth', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<input
					class="lccl-bdf__input"
					type="date"
					id="lccl-spf-dob"
					name="dob"
					value="<?php echo esc_attr( $val( 'dob' ) ); ?>"
					placeholder="<?php esc_attr_e( 'YYYY-MM-DD', 'lccl-de' ); ?>"
					min="<?php echo esc_attr( $min_dob ); ?>"
					max="<?php echo esc_attr( $max_dob ); ?>"
					data-lccl-validate="dob"
					data-invalid-message="<?php echo esc_attr( LCCL_DE_Spectacles_Submissions::dob_error_message() ); ?>"
					required
				>
				<?php $notice( 'dob' ); ?>
			</div>

			<div class="lccl-bdf__field">
				<label class="lccl-bdf__label" for="lccl-spf-age"><?php esc_html_e( 'Age', 'lccl-de' ); ?></label>
				<select class="lccl-bdf__select" id="lccl-spf-age" name="age">
					<option value=""><?php esc_html_e( 'Select age', 'lccl-de' ); ?></option>
					<?php $form::render_options( $form::ages(), $val( 'age' ) ); ?>
				</select>
			</div>

			<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'gender' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-spf-gender">
					<?php esc_html_e( 'Gender', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<select class="lccl-bdf__select" id="lccl-spf-gender" name="gender" required>
					<option value=""><?php esc_html_e( 'Select gender', 'lccl-de' ); ?></option>
					<?php $form::render_options( $form::genders(), $val( 'gender' ) ); ?>
				</select>
				<?php $notice( 'gender' ); ?>
			</div>

			<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'grade' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-spf-grade">
					<?php esc_html_e( 'Year', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<select class="lccl-bdf__select" id="lccl-spf-grade" name="grade" required>
					<option value=""><?php esc_html_e( 'Select year', 'lccl-de' ); ?></option>
					<?php $form::render_options( $form::grades(), $val( 'grade' ) ); ?>
				</select>
				<?php $notice( 'grade' ); ?>
			</div>

			<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'school_name' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-spf-school">
					<?php esc_html_e( 'School Name', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<input
					class="lccl-bdf__input"
					type="text"
					id="lccl-spf-school"
					name="school_name"
					value="<?php echo esc_attr( $val( 'school_name' ) ); ?>"
					placeholder="<?php esc_attr_e( 'Dharmapala Vidyalaya', 'lccl-de' ); ?>"
					autocomplete="organization"
					maxlength="191"
					required
				>
				<?php $notice( 'school_name' ); ?>
			</div>

			<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'school_area' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-spf-school-area">
					<?php esc_html_e( 'School Area', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<input
					class="lccl-bdf__input"
					type="text"
					id="lccl-spf-school-area"
					name="school_area"
					value="<?php echo esc_attr( $val( 'school_area' ) ); ?>"
					placeholder="<?php esc_attr_e( 'Pannipitiya', 'lccl-de' ); ?>"
					maxlength="191"
					required
				>
				<?php $notice( 'school_area' ); ?>
			</div>

			<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'district' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-spf-district">
					<?php esc_html_e( 'District', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<select class="lccl-bdf__select" id="lccl-spf-district" name="district" required>
					<option value=""><?php esc_html_e( 'Select your district', 'lccl-de' ); ?></option>
					<?php $form::render_options( $form::districts(), $val( 'district' ) ); ?>
				</select>
				<?php $notice( 'district' ); ?>
			</div>

			<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'guardian_name' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-spf-guardian">
					<?php esc_html_e( 'Parent / Guardian Name', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<input
					class="lccl-bdf__input"
					type="text"
					id="lccl-spf-guardian"
					name="guardian_name"
					value="<?php echo esc_attr( $val( 'guardian_name' ) ); ?>"
					placeholder="<?php esc_attr_e( 'Saman Perera', 'lccl-de' ); ?>"
					autocomplete="name"
					maxlength="191"
					required
				>
				<?php $notice( 'guardian_name' ); ?>
			</div>

			<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'phone' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-spf-phone">
					<?php esc_html_e( 'Mobile / WhatsApp Number', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<input
					class="lccl-bdf__input"
					type="tel"
					id="lccl-spf-phone"
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
				<p class="lccl-bdf__notice" data-lccl-notice="phone" role="alert"<?php echo $err( 'phone' ) ? '' : ' hidden'; ?>><?php echo $err( 'phone' ) ? esc_html( $err( 'phone' ) ) : ''; ?></p>
			</div>

			<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'city' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-spf-city">
					<?php esc_html_e( 'City / Area', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<input
					class="lccl-bdf__input"
					type="text"
					id="lccl-spf-city"
					name="city"
					value="<?php echo esc_attr( $val( 'city' ) ); ?>"
					placeholder="<?php esc_attr_e( 'Colombo', 'lccl-de' ); ?>"
					autocomplete="address-level2"
					maxlength="100"
					required
				>
				<?php $notice( 'city' ); ?>
			</div>

			<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'relationship' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-spf-relationship">
					<?php esc_html_e( 'Relationship to Child', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<select class="lccl-bdf__select" id="lccl-spf-relationship" name="relationship" required>
					<option value=""><?php esc_html_e( 'Select a relationship', 'lccl-de' ); ?></option>
					<?php $form::render_options( $form::relationships(), $val( 'relationship' ) ); ?>
				</select>
				<?php $notice( 'relationship' ); ?>
			</div>

			<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'email' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-spf-email"><?php esc_html_e( 'Email', 'lccl-de' ); ?></label>
				<input
					class="lccl-bdf__input"
					type="email"
					id="lccl-spf-email"
					name="email"
					value="<?php echo esc_attr( $val( 'email' ) ); ?>"
					placeholder="<?php esc_attr_e( 'name@example.com', 'lccl-de' ); ?>"
					autocomplete="email"
					maxlength="191"
					data-lccl-validate="email"
					data-invalid-message="<?php echo esc_attr( LCCL_DE_Blood_Donor_Submissions::email_error_message() ); ?>"
				>
				<p class="lccl-bdf__notice" data-lccl-notice="email" role="alert"<?php echo $err( 'email' ) ? '' : ' hidden'; ?>><?php echo $err( 'email' ) ? esc_html( $err( 'email' ) ) : ''; ?></p>
			</div>

			<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'eye_exam' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-spf-eye-exam">
					<?php esc_html_e( 'Has the child had an eye examination previously?', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<select class="lccl-bdf__select" id="lccl-spf-eye-exam" name="eye_exam" required>
					<option value=""><?php esc_html_e( 'Select an option', 'lccl-de' ); ?></option>
					<?php $form::render_options( $form::yes_no_unsure(), $val( 'eye_exam' ) ); ?>
				</select>
				<?php $notice( 'eye_exam' ); ?>
			</div>

			<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'wear_spectacles' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-spf-wear">
					<?php esc_html_e( 'Does the child currently wear spectacles?', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<select class="lccl-bdf__select" id="lccl-spf-wear" name="wear_spectacles" required>
					<option value=""><?php esc_html_e( 'Select an option', 'lccl-de' ); ?></option>
					<?php $form::render_options( $form::yes_no(), $val( 'wear_spectacles' ) ); ?>
				</select>
				<?php $notice( 'wear_spectacles' ); ?>
			</div>

			<div class="lccl-bdf__field<?php echo esc_attr( $invalid( 'difficulty_seeing' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-spf-difficulty">
					<?php esc_html_e( 'Does the child have difficulty seeing clearly?', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<select class="lccl-bdf__select" id="lccl-spf-difficulty" name="difficulty_seeing" required>
					<option value=""><?php esc_html_e( 'Select an option', 'lccl-de' ); ?></option>
					<?php $form::render_options( $form::yes_no_unsure(), $val( 'difficulty_seeing' ) ); ?>
				</select>
				<?php $notice( 'difficulty_seeing' ); ?>
			</div>

			<div class="lccl-bdf__field">
				<label class="lccl-bdf__label" for="lccl-spf-last-exam"><?php esc_html_e( 'When was the child\'s last eye examination?', 'lccl-de' ); ?></label>
				<select class="lccl-bdf__select" id="lccl-spf-last-exam" name="last_eye_exam">
					<option value=""><?php esc_html_e( 'Select an option', 'lccl-de' ); ?></option>
					<?php $form::render_options( $form::last_eye_exams(), $val( 'last_eye_exam' ) ); ?>
				</select>
			</div>

			<div class="lccl-bdf__field lccl-bdf__field--full<?php echo esc_attr( $invalid( 'eye_condition' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-spf-condition">
					<?php esc_html_e( 'Does the child have any known eye condition or vision-related problem?', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<select class="lccl-bdf__select" id="lccl-spf-condition" name="eye_condition" required>
					<option value=""><?php esc_html_e( 'Select an option', 'lccl-de' ); ?></option>
					<?php $form::render_options( $form::yes_no_unsure(), $val( 'eye_condition' ) ); ?>
				</select>
				<?php $notice( 'eye_condition' ); ?>
			</div>

			<div class="lccl-bdf__field lccl-bdf__field--full<?php echo esc_attr( $invalid( 'eye_condition_details' ) ); ?>" data-lccl-show-when="eye_condition:yes"<?php echo $show_condition ? '' : ' hidden'; ?>>
				<label class="lccl-bdf__label" for="lccl-spf-condition-details">
					<?php esc_html_e( 'Please provide details', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<textarea
					class="lccl-bdf__textarea"
					id="lccl-spf-condition-details"
					name="eye_condition_details"
					rows="4"
					placeholder="<?php esc_attr_e( 'Short-sightedness diagnosed last year', 'lccl-de' ); ?>"
					<?php echo $show_condition ? 'required' : ''; ?>
				><?php echo esc_textarea( $val( 'eye_condition_details' ) ); ?></textarea>
				<?php $notice( 'eye_condition_details' ); ?>
			</div>

			<div class="lccl-bdf__field lccl-bdf__field--full<?php echo esc_attr( $invalid( 'vision_difficulties' ) ); ?>">
				<span class="lccl-bdf__label">
					<?php esc_html_e( 'What vision difficulties does the child experience?', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</span>
				<p class="lccl-bdf__hint"><?php esc_html_e( 'Please select all that apply.', 'lccl-de' ); ?></p>
				<div class="lccl-bdf__choices">
					<?php foreach ( $form::vision_difficulties() as $key => $label ) : ?>
						<label class="lccl-bdf__checkbox">
							<input type="checkbox" name="vision_difficulties[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $list( 'vision_difficulties' ), true ) ); ?>>
							<span class="lccl-bdf__check" aria-hidden="true"></span>
							<span><?php echo esc_html( $label ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
				<?php $notice( 'vision_difficulties' ); ?>
			</div>

			<div class="lccl-bdf__field lccl-bdf__field--full<?php echo esc_attr( $invalid( 'vision_other' ) ); ?>" data-lccl-show-when="vision_other"<?php echo $show_other ? '' : ' hidden'; ?>>
				<label class="lccl-bdf__label" for="lccl-spf-vision-other">
					<?php esc_html_e( 'Please specify the other vision difficulty', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<input
					class="lccl-bdf__input"
					type="text"
					id="lccl-spf-vision-other"
					name="vision_other"
					value="<?php echo esc_attr( $val( 'vision_other' ) ); ?>"
					placeholder="<?php esc_attr_e( 'Please describe the other difficulty', 'lccl-de' ); ?>"
					maxlength="255"
					<?php echo $show_other ? 'required' : ''; ?>
				>
				<?php $notice( 'vision_other' ); ?>
			</div>

			<div class="lccl-bdf__field lccl-bdf__field--full<?php echo esc_attr( $invalid( 'school_letter' ) ); ?>">
				<label class="lccl-bdf__label" for="lccl-spf-letter">
					<?php esc_html_e( 'School Letter', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<select class="lccl-bdf__select" id="lccl-spf-letter" name="school_letter" required>
					<option value=""><?php esc_html_e( 'Select how the letter will be submitted', 'lccl-de' ); ?></option>
					<?php $form::render_options( $form::school_letters(), $val( 'school_letter' ) ); ?>
				</select>
				<?php $notice( 'school_letter' ); ?>
			</div>

			<div class="lccl-bdf__field lccl-bdf__field--full<?php echo esc_attr( $invalid( 'school_letter_file' ) ); ?>" data-lccl-show-when="school_letter:submitted-herewith"<?php echo $show_letter ? '' : ' hidden'; ?>>
				<label class="lccl-bdf__label" for="lccl-spf-letter-file">
					<?php esc_html_e( 'Upload School Letter', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
				</label>
				<input
					class="lccl-bdf__file"
					type="file"
					id="lccl-spf-letter-file"
					name="school_letter_file"
					accept=".pdf,.jpg,.jpeg,.png"
					data-invalid-type="<?php echo esc_attr( LCCL_DE_Spectacles_Submissions::letter_type_message() ); ?>"
					data-invalid-size="<?php echo esc_attr( LCCL_DE_Spectacles_Submissions::letter_size_message() ); ?>"
					<?php echo $show_letter ? 'required' : ''; ?>
				>
				<p class="lccl-bdf__hint"><?php esc_html_e( 'Accepted file formats: PDF, JPG, JPEG and PNG. Maximum file size: 10 MB.', 'lccl-de' ); ?></p>
				<?php $notice( 'school_letter_file' ); ?>
			</div>

			<div class="lccl-bdf__field lccl-bdf__field--full lccl-bdf__templates">
				<span class="lccl-bdf__subtitle"><?php esc_html_e( 'School Letter Templates', 'lccl-de' ); ?></span>
				<p class="lccl-bdf__hint"><?php esc_html_e( "Schools may use either template depending on whether the letter is for an individual student or multiple students. The letter should be prepared on the school's official letterhead and signed by the Principal or an authorized officer.", 'lccl-de' ); ?></p>
				<div class="lccl-bdf__template-links">
					<?php foreach ( $form::letter_templates() as $url => $label ) : ?>
						<a href="<?php echo esc_url( $url ); ?>" download target="_blank" rel="noopener noreferrer"><?php echo esc_html( $label ); ?></a>
					<?php endforeach; ?>
				</div>
			</div>

		</div>

		<section class="lccl-bdf__consent">
			<label class="lccl-bdf__checkbox<?php echo esc_attr( $invalid( 'consent' ) ); ?>">
				<input type="checkbox" name="consent" value="1" <?php checked( (int) $val( 'consent' ), 1 ); ?> required>
				<span class="lccl-bdf__check" aria-hidden="true"></span>
				<span>
					<?php esc_html_e( 'I confirm that I am the parent, legal guardian or authorized representative of the child named above, and I give permission for the Lions Club of Colombo LEADS to contact me regarding this programme and use the information provided to assess and coordinate the provision of spectacles.', 'lccl-de' ); ?>
					<span class="lccl-bdf__req">*</span>
				</span>
			</label>
			<?php $notice( 'consent' ); ?>
		</section>

		<div class="lccl-bdf__hp" aria-hidden="true">
			<label for="lccl-spf-hp"><?php esc_html_e( 'Website', 'lccl-de' ); ?></label>
			<input type="text" id="lccl-spf-hp" name="lccl_de_hp" value="" tabindex="-1" autocomplete="off">
		</div>

		<input type="hidden" name="action" value="<?php echo esc_attr( LCCL_DE_Spectacles_Submissions::ACTION ); ?>">
		<input type="hidden" name="redirect_to" value="<?php echo esc_url( get_permalink() ? get_permalink() : home_url( '/' ) ); ?>">
		<?php wp_nonce_field( LCCL_DE_Spectacles_Submissions::ACTION, 'lccl_de_nonce' ); ?>

		<button class="lccl-bdf__submit" type="submit">
			<?php esc_html_e( 'Register', 'lccl-de' ); ?>
		</button>
	</form>
</div>
