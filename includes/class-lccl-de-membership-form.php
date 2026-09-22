<?php
/**
 * Sample annual membership fee form (UI only, no payment or database).
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the public sample membership fee form.
 */
class LCCL_DE_Membership_Form {

	/**
	 * Shortcode tag.
	 */
	const SHORTCODE = 'lccl_membership_fee';

	/**
	 * USD to LKR rate used for the sample calculation.
	 */
	const RATE = 330.8;

	/**
	 * Main member international fee in USD.
	 */
	const PRINCIPAL_USD = 50;

	/**
	 * Additional family member international fee in USD.
	 */
	const FAMILY_USD = 25;

	/**
	 * District payment in LKR, charged once per member.
	 */
	const DISTRICT_LKR = 3500;

	/**
	 * Club payment in LKR, charged once for the membership.
	 */
	const CLUB_LKR = 6000;

	/**
	 * Hook the shortcode into WordPress and WPBakery.
	 */
	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'vc_before_init', array( __CLASS__, 'map' ) );
	}

	/**
	 * Default form heading.
	 *
	 * @return string
	 */
	public static function default_title() {
		return __( 'Annual Membership Fee', 'lccl-de' );
	}

	/**
	 * Default heading intro.
	 *
	 * @return string
	 */
	public static function default_intro() {
		return __( 'Please complete the details below to pay your annual membership fee to Lions Club of Colombo LEADS.', 'lccl-de' );
	}

	/**
	 * Membership type options.
	 *
	 * @return array<string,string>
	 */
	public static function types() {
		return array(
			'member' => __( 'Member', 'lccl-de' ),
			'family' => __( 'Family Membership', 'lccl-de' ),
		);
	}

	/**
	 * Family member count options, including the main member.
	 *
	 * @return array<string,string>
	 */
	public static function family_counts() {
		return array(
			'2' => __( '2 Family Members', 'lccl-de' ),
			'3' => __( '3 Family Members', 'lccl-de' ),
			'4' => __( '4 Family Members', 'lccl-de' ),
			'5' => __( '5 Family Members', 'lccl-de' ),
		);
	}

	/**
	 * Format a rupee amount with two decimals.
	 *
	 * @param float $amount Amount in LKR.
	 * @return string
	 */
	public static function format_lkr( $amount ) {
		return 'LKR ' . number_format( (float) $amount, 2, '.', ',' );
	}

	/**
	 * Format the displayed exchange rate.
	 *
	 * @param float $rate USD to LKR rate.
	 * @return string
	 */
	public static function format_rate( $rate ) {
		return number_format( (float) $rate, 2, '.', ',' );
	}

	/**
	 * Sample fee breakdown for the current membership type.
	 *
	 * @param string $type  member|family or empty.
	 * @param int    $count Family members including the main member.
	 * @return array<string,mixed>
	 */
	public static function breakdown( $type = '', $count = 2 ) {
		$is_family          = 'family' === $type;
		$members            = $is_family ? max( 2, (int) $count ) : 1;
		$additional         = $is_family ? max( 0, $members - 1 ) : 0;
		$international_main = self::PRINCIPAL_USD * self::RATE;
		$family_fee         = $additional * self::FAMILY_USD * self::RATE;
		$district           = $members * self::DISTRICT_LKR;
		$club               = self::CLUB_LKR;
		$total              = $international_main + $family_fee + $district + $club;

		return array(
			'is_family'          => $is_family,
			'members'            => $members,
			'additional'         => $additional,
			'rate'               => self::RATE,
			'international_main' => $international_main,
			'family_fee'         => $family_fee,
			'district'           => $district,
			'club'               => $club,
			'total'              => $total,
		);
	}

	/**
	 * Render the sample membership fee form.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'title' => self::default_title(),
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
			'lccl-de-membership-form',
			LCCL_DE_URL . 'assets/js/lccl-de-membership-form.js',
			array(),
			LCCL_DE_VERSION,
			true
		);

		$values = array();
		$fees   = self::breakdown();

		ob_start();
		include LCCL_DE_PATH . 'templates/membership-form.php';

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
				'name'        => __( 'LCCL Membership Fee', 'lccl-de' ),
				'base'        => self::SHORTCODE,
				'category'    => __( 'LCCL', 'lccl-de' ),
				'description' => __( 'Sample annual membership fee form. UI only; no payment is processed.', 'lccl-de' ),
				'icon'        => 'icon-wpb-ui-separator',
				'params'      => array(
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Title', 'lccl-de' ),
						'param_name'  => 'title',
						'value'       => self::default_title(),
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
