<?php
/**
 * Free Spectacles programme registration form.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the public Free Spectacles registration form.
 */
class LCCL_DE_Spectacles_Form {

	/**
	 * Shortcode tag.
	 */
	const SHORTCODE = 'lccl_spectacles_registration';

	/**
	 * Hook the shortcode into WordPress and WPBakery.
	 */
	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'vc_before_init', array( __CLASS__, 'map' ) );
	}

	/**
	 * Districts of Sri Lanka, in the order used on the saved registration form.
	 *
	 * @return array
	 */
	public static function districts() {
		$districts = array(
			'Colombo',
			'Gampaha',
			'Kalutara',
			'Kandy',
			'Matale',
			'Nuwara Eliya',
			'Galle',
			'Matara',
			'Hambantota',
			'Jaffna',
			'Kilinochchi',
			'Mannar',
			'Vavuniya',
			'Mullaitivu',
			'Batticaloa',
			'Ampara',
			'Trincomalee',
			'Kurunegala',
			'Puttalam',
			'Anuradhapura',
			'Polonnaruwa',
			'Badulla',
			'Monaragala',
			'Ratnapura',
			'Kegalle',
		);

		return apply_filters( 'lccl_de_spectacles_districts', array_combine( $districts, $districts ) );
	}

	/**
	 * Age options, 3–19 years.
	 *
	 * @return array
	 */
	public static function ages() {
		$ages = array();
		for ( $i = 3; $i <= 19; $i++ ) {
			$ages[ (string) $i ] = sprintf(
				/* translators: %d: age in years. */
				_n( '%d year', '%d years', $i, 'lccl-de' ),
				$i
			);
		}

		return $ages;
	}

	/**
	 * Gender options.
	 *
	 * @return array
	 */
	public static function genders() {
		return array(
			'male'   => __( 'Male', 'lccl-de' ),
			'female' => __( 'Female', 'lccl-de' ),
		);
	}

	/**
	 * Intro copy shown under the registration heading.
	 *
	 * @return string
	 */
	public static function default_intro() {
		return __( 'Please provide the information below to register a school child who may require free spectacles. Our team will review the information and contact the parent or guardian regarding the next steps.', 'lccl-de' );
	}

	/**
	 * Year options.
	 *
	 * Stored keys stay grade-1 … grade-13 so existing rows keep matching.
	 *
	 * @return array
	 */
	public static function grades() {
		$grades = array();
		for ( $i = 1; $i <= 13; $i++ ) {
			$grades[ 'grade-' . $i ] = sprintf(
				/* translators: %d: school year number. */
				__( 'Year %d', 'lccl-de' ),
				$i
			);
		}

		return $grades;
	}

	/**
	 * Relationship to child.
	 *
	 * @return array
	 */
	public static function relationships() {
		return array(
			'mother'   => __( 'Mother', 'lccl-de' ),
			'father'   => __( 'Father', 'lccl-de' ),
			'guardian' => __( 'Guardian', 'lccl-de' ),
			'other'    => __( 'Other', 'lccl-de' ),
		);
	}

	/**
	 * Yes / No / Not Sure.
	 *
	 * @return array
	 */
	public static function yes_no_unsure() {
		return array(
			'yes'      => __( 'Yes', 'lccl-de' ),
			'no'       => __( 'No', 'lccl-de' ),
			'not-sure' => __( 'Not Sure', 'lccl-de' ),
		);
	}

	/**
	 * Yes / No.
	 *
	 * @return array
	 */
	public static function yes_no() {
		return array(
			'yes' => __( 'Yes', 'lccl-de' ),
			'no'  => __( 'No', 'lccl-de' ),
		);
	}

	/**
	 * Last eye examination timing.
	 *
	 * @return array
	 */
	public static function last_eye_exams() {
		return array(
			'within-6-months' => __( 'Within the last 6 months', 'lccl-de' ),
			'6-12-months'     => __( '6–12 months', 'lccl-de' ),
			'more-than-1-year' => __( 'More than 1 year ago', 'lccl-de' ),
			'never'           => __( 'Never', 'lccl-de' ),
			'not-sure'        => __( 'Not Sure', 'lccl-de' ),
		);
	}

	/**
	 * Vision difficulty checkboxes.
	 *
	 * @return array
	 */
	public static function vision_difficulties() {
		return array(
			'blackboard'       => __( 'Difficulty seeing the blackboard at school', 'lccl-de' ),
			'reading'          => __( 'Difficulty reading books or schoolwork', 'lccl-de' ),
			'distance'         => __( 'Difficulty seeing things from a distance', 'lccl-de' ),
			'close'            => __( 'Difficulty seeing things up close', 'lccl-de' ),
			'blurred'          => __( 'Blurred or unclear vision', 'lccl-de' ),
			'headaches'        => __( 'Frequent headaches or eye strain', 'lccl-de' ),
			'night'            => __( 'Difficulty seeing at night', 'lccl-de' ),
			'none-recommended' => __( 'No specific difficulty, but an eye examination is recommended', 'lccl-de' ),
			'other'            => __( 'Other', 'lccl-de' ),
		);
	}

	/**
	 * School letter submission options.
	 *
	 * @return array
	 */
	public static function school_letters() {
		return array(
			'submitted-herewith'   => __( 'Submitted Herewith', 'lccl-de' ),
			'submitted-separately' => __( 'Submitted Separately', 'lccl-de' ),
		);
	}

	/**
	 * School letter Word templates.
	 *
	 * @return array
	 */
	public static function letter_templates() {
		return array(
			'https://colomboleads.org/school-letter-individual.docx' => __( 'Download – Individual Student Letter', 'lccl-de' ),
			'https://colomboleads.org/school-letter-multiple.docx'   => __( 'Download – Multiple Student Letter', 'lccl-de' ),
		);
	}

	/**
	 * Output option tags for a select field.
	 *
	 * @param array  $options  Value => label pairs.
	 * @param string $selected Currently selected value.
	 */
	public static function render_options( array $options, $selected = '' ) {
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $selected, $value, false ),
				esc_html( $label )
			);
		}
	}

	/**
	 * Render the registration form.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'title' => __( 'Registration Form', 'lccl-de' ),
				'intro' => self::default_intro(),
			),
			$atts,
			self::SHORTCODE
		);

		$legacy_intro = 'Please provide the information below to register your child for the free spectacles programme.';
		if ( '' === $atts['intro'] || $atts['intro'] === $legacy_intro ) {
			$atts['intro'] = self::default_intro();
		}

		wp_enqueue_style(
			'lccl-de-blood-donor-form',
			LCCL_DE_URL . 'assets/css/lccl-de-blood-donor-form.css',
			array(),
			LCCL_DE_VERSION
		);

		wp_enqueue_script(
			'lccl-de-spectacles-form',
			LCCL_DE_URL . 'assets/js/lccl-de-spectacles-form.js',
			array(),
			LCCL_DE_VERSION,
			true
		);

		$flash   = LCCL_DE_Spectacles_Submissions::consume_flash();
		$values  = $flash['values'];
		$errors  = $flash['errors'];
		$success = ! empty( $flash['success'] );

		ob_start();
		include LCCL_DE_PATH . 'templates/spectacles-registration-form.php';

		return ob_get_clean();
	}

	/**
	 * Expose the form as a WPBakery element.
	 */
	public static function map() {
		if ( ! function_exists( 'vc_map' ) ) {
			return;
		}

		vc_map(
			array(
				'name'        => __( 'LCCL Free Spectacles Registration', 'lccl-de' ),
				'base'        => self::SHORTCODE,
				'category'    => __( 'LCCL', 'lccl-de' ),
				'description' => __( 'Registration form for the free spectacles programme.', 'lccl-de' ),
				'icon'        => 'icon-wpb-ui-separator',
				'params'      => array(
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Title', 'lccl-de' ),
						'param_name'  => 'title',
						'value'       => __( 'Registration Form', 'lccl-de' ),
						'admin_label' => true,
					),
					array(
						'type'       => 'textarea',
						'heading'    => __( 'Intro text', 'lccl-de' ),
						'param_name' => 'intro',
						'value'      => self::default_intro(),
					),
				),
			)
		);
	}
}
