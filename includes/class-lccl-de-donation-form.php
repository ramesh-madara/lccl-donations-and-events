<?php
/**
 * Sample donation form (UI only, no payment or database).
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the public sample donation form.
 */
class LCCL_DE_Donation_Form {

	/**
	 * Shortcode tag.
	 */
	const SHORTCODE = 'lccl_donation_form';

	/**
	 * Hook the shortcode into WordPress and WPBakery.
	 */
	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'vc_before_init', array( __CLASS__, 'map' ) );
	}

	/**
	 * Default heading intro.
	 *
	 * @return string
	 */
	public static function default_intro() {
		return __( 'Please provide the information below to make a donation to the Lions Club of Colombo LEADS.', 'lccl-de' );
	}

	/**
	 * Message shown under an empty required field.
	 *
	 * @return string
	 */
	public static function required_field_message() {
		return LCCL_DE_Blood_Donor_Submissions::required_field_message();
	}

	/**
	 * Message shown when the amount is missing or not a positive number.
	 *
	 * @return string
	 */
	public static function amount_error_message() {
		return __( 'Please enter a valid donation amount of at least 1 LKR.', 'lccl-de' );
	}

	/**
	 * Render the sample donation form.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'title' => __( 'Donation to Lions Club of Colombo LEADS', 'lccl-de' ),
				'intro' => self::default_intro(),
			),
			$atts,
			self::SHORTCODE
		);

		wp_enqueue_style(
			'lccl-de-blood-donor-form',
			LCCL_DE_URL . 'assets/css/lccl-de-blood-donor-form.css',
			array(),
			LCCL_DE_VERSION
		);

		wp_enqueue_script(
			'lccl-de-donation-form',
			LCCL_DE_URL . 'assets/js/lccl-de-donation-form.js',
			array(),
			LCCL_DE_VERSION,
			true
		);

		$values  = array();
		$errors  = array();
		$success = false;

		ob_start();
		include LCCL_DE_PATH . 'templates/donation-form.php';

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
				'name'        => __( 'LCCL Donation Form', 'lccl-de' ),
				'base'        => self::SHORTCODE,
				'category'    => __( 'LCCL', 'lccl-de' ),
				'description' => __( 'Sample donation form. UI and validation only; no payment is processed.', 'lccl-de' ),
				'icon'        => 'icon-wpb-ui-separator',
				'params'      => array(
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Title', 'lccl-de' ),
						'param_name'  => 'title',
						'value'       => __( 'Donation to Lions Club of Colombo LEADS', 'lccl-de' ),
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
