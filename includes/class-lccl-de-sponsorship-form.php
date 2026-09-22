<?php
/**
 * Sample project sponsorship form (UI only, no payment or database).
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the public sample project sponsorship form.
 */
class LCCL_DE_Sponsorship_Form {

	/**
	 * Shortcode tag.
	 */
	const SHORTCODE = 'lccl_project_sponsorship';

	/**
	 * Default selected amount.
	 */
	const DEFAULT_AMOUNT = '5000';

	/**
	 * Hook the shortcode into WordPress and WPBakery.
	 */
	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'vc_before_init', array( __CLASS__, 'map' ) );
	}

	/**
	 * Default page banner heading.
	 *
	 * @return string
	 */
	public static function default_banner_title() {
		return '';
	}

	/**
	 * Default page banner intro.
	 *
	 * @return string
	 */
	public static function default_banner_intro() {
		return '';
	}

	/**
	 * Default payment form heading.
	 *
	 * @return string
	 */
	public static function default_title() {
		return __( 'Support a Project', 'lccl-de' );
	}

	/**
	 * Default payment form intro.
	 *
	 * @return string
	 */
	public static function default_intro() {
		return __( 'Please provide the information below to support your selected project.', 'lccl-de' );
	}

	/**
	 * Sample projects shown in the right-hand panel.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function projects() {
		return array(
			'spectacles' => array(
				'status'  => 'ongoing',
				'label'   => __( 'Ongoing', 'lccl-de' ),
				'title'   => __( '200 Spectacles for School Children', 'lccl-de' ),
				'summary' => __( 'Help provide 200 pairs of spectacles to school children who require vision correction.', 'lccl-de' ),
				'value'   => 1000000,
				'raised'  => 425000,
			),
			'cataract'   => array(
				'status'  => 'upcoming',
				'label'   => __( 'Upcoming', 'lccl-de' ),
				'title'   => __( 'Cataract Surgery Support', 'lccl-de' ),
				'summary' => __( 'Support cataract surgery for patients identified through our community health initiatives.', 'lccl-de' ),
				'value'   => 750000,
				'raised'  => 180000,
			),
			'health'     => array(
				'status'  => 'upcoming',
				'label'   => __( 'Upcoming', 'lccl-de' ),
				'title'   => __( 'Community Health Programme', 'lccl-de' ),
				'summary' => __( 'Support medical screening, consultations and essential health services for communities in need.', 'lccl-de' ),
				'value'   => 500000,
				'raised'  => 95000,
			),
		);
	}

	/**
	 * Format a rupee amount as used on the project cards.
	 *
	 * @param float $amount Amount in LKR.
	 * @return string
	 */
	public static function format_rs( $amount ) {
		return 'Rs. ' . number_format( (float) $amount, 2, '.', ',' );
	}

	/**
	 * Raised percentage for the progress bar.
	 *
	 * @param float $value  Project value.
	 * @param float $raised Amount raised.
	 * @return float
	 */
	public static function progress_percent( $value, $raised ) {
		$value = (float) $value;
		if ( $value <= 0 ) {
			return 0;
		}

		return min( 100, max( 0, round( ( (float) $raised / $value ) * 100, 1 ) ) );
	}

	/**
	 * Display amount with thousands separators and LKR.
	 *
	 * @param string $amount Raw amount.
	 * @return string
	 */
	public static function format_amount( $amount ) {
		$raw = preg_replace( '/[^\d.]/', '', (string) $amount );
		if ( '' === $raw || ! is_numeric( $raw ) ) {
			return '';
		}
		return number_format( (float) $raw ) . ' LKR';
	}

	/**
	 * Render the sample sponsorship form.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'banner_title' => self::default_banner_title(),
				'banner_intro' => self::default_banner_intro(),
				'title'        => self::default_title(),
				'intro'        => self::default_intro(),
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
			'lccl-de-sponsorship-form',
			LCCL_DE_URL . 'assets/js/lccl-de-sponsorship-form.js',
			array(),
			LCCL_DE_VERSION,
			true
		);

		$values = array();

		ob_start();
		include LCCL_DE_PATH . 'templates/sponsorship-form.php';

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
				'name'        => __( 'LCCL Project Sponsorship', 'lccl-de' ),
				'base'        => self::SHORTCODE,
				'category'    => __( 'LCCL', 'lccl-de' ),
				'description' => __( 'Sample project sponsorship form. UI only; no payment is processed.', 'lccl-de' ),
				'icon'        => 'icon-wpb-ui-separator',
				'params'      => array(
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Banner title', 'lccl-de' ),
						'param_name'  => 'banner_title',
						'value'       => self::default_banner_title(),
						'admin_label' => true,
					),
					array(
						'type'       => 'textarea',
						'heading'    => __( 'Banner intro', 'lccl-de' ),
						'param_name' => 'banner_intro',
						'value'      => self::default_banner_intro(),
					),
					array(
						'type'       => 'textfield',
						'heading'    => __( 'Form title', 'lccl-de' ),
						'param_name' => 'title',
						'value'      => self::default_title(),
					),
					array(
						'type'       => 'textarea',
						'heading'    => __( 'Form intro', 'lccl-de' ),
						'param_name' => 'intro',
						'value'      => self::default_intro(),
					),
				),
			)
		);
	}
}
