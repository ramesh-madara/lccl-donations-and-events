<?php
/**
 * Join Our Projects public form.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the public Join Our Projects registration form.
 */
class LCCL_DE_Join_Projects_Form {

	/**
	 * Shortcode tag.
	 */
	const SHORTCODE = 'lccl_join_our_projects';

	/**
	 * Hook the shortcode into WordPress and WPBakery.
	 */
	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'vc_before_init', array( __CLASS__, 'map' ) );
	}

	/**
	 * Default intro copy, matching the public form.
	 *
	 * @return string
	 */
	public static function default_intro() {
		return __( 'Your time, skills, resources and generosity can make a meaningful difference in the lives of others. Register your interest to support our community projects. You may volunteer your time, contribute professional skills, provide financial support, sponsor a project, or support us in other ways.', 'lccl-de' );
	}

	/**
	 * How would you like to support us?
	 *
	 * @return array<string,string>
	 */
	public static function support_ways() {
		return array(
			'volunteer-time'      => __( 'Volunteer My Time', 'lccl-de' ),
			'professional-skills' => __( 'Offer My Professional Skills', 'lccl-de' ),
			'financial-donation'  => __( 'Make a Financial Donation', 'lccl-de' ),
			'sponsor-project'     => __( 'Sponsor a Project / Activity', 'lccl-de' ),
			'donate-goods'        => __( 'Donate Goods / Equipment / Supplies', 'lccl-de' ),
			'provide-services'    => __( 'Provide Services / Resources', 'lccl-de' ),
			'fundraising'         => __( 'Support Fundraising Activities', 'lccl-de' ),
			'partner'             => __( 'Partner / Collaborate with the Club', 'lccl-de' ),
			'other'               => __( 'Other', 'lccl-de' ),
		);
	}

	/**
	 * Volunteer / skill areas shown when time or skills are selected.
	 *
	 * @return array<string,string>
	 */
	public static function volunteer_areas() {
		return array(
			'event-project'       => __( 'Event / Project Volunteer', 'lccl-de' ),
			'medical'             => __( 'Medical / Healthcare Services', 'lccl-de' ),
			'professional'        => __( 'Professional / Technical Skills', 'lccl-de' ),
			'it'                  => __( 'IT / Technology', 'lccl-de' ),
			'administration'      => __( 'Administration', 'lccl-de' ),
			'pr'                  => __( 'Public Relations / Communications', 'lccl-de' ),
			'photography'         => __( 'Photography / Videography', 'lccl-de' ),
			'fundraising'         => __( 'Fundraising', 'lccl-de' ),
			'event-coordination'  => __( 'Event Coordination', 'lccl-de' ),
			'transportation'      => __( 'Transportation / Logistics', 'lccl-de' ),
			'community-outreach'  => __( 'Community Outreach', 'lccl-de' ),
			'teaching'            => __( 'Teaching / Training', 'lccl-de' ),
			'other'               => __( 'Other', 'lccl-de' ),
		);
	}

	/**
	 * When the registrant is generally available.
	 *
	 * @return array<string,string>
	 */
	public static function availability() {
		return array(
			'weekdays'    => __( 'Weekdays', 'lccl-de' ),
			'weekends'    => __( 'Weekends', 'lccl-de' ),
			'evenings'    => __( 'Evenings', 'lccl-de' ),
			'occasional'  => __( 'Occasionally / Based on Project Requirements', 'lccl-de' ),
		);
	}

	/**
	 * How they prefer to contribute financially.
	 *
	 * @return array<string,string>
	 */
	public static function financial_support() {
		return array(
			'one-time'            => __( 'One-time donation', 'lccl-de' ),
			'recurring'           => __( 'Regular / recurring donation', 'lccl-de' ),
			'sponsor-project'     => __( 'Sponsor a specific project', 'lccl-de' ),
			'sponsor-beneficiary' => __( 'Sponsor a specific beneficiary / requirement', 'lccl-de' ),
			'corporate'           => __( 'Corporate / Organization sponsorship', 'lccl-de' ),
			'discuss'             => __( 'I would like to discuss the options', 'lccl-de' ),
		);
	}

	/**
	 * Estimated contribution amounts.
	 *
	 * @return array<string,string>
	 */
	public static function contribution_amounts() {
		return array(
			'below-5000'   => __( 'Below Rs. 5,000', 'lccl-de' ),
			'5000-10000'   => __( 'Rs. 5,000 – 10,000', 'lccl-de' ),
			'10000-25000'  => __( 'Rs. 10,000 – 25,000', 'lccl-de' ),
			'25000-50000'  => __( 'Rs. 25,000 – 50,000', 'lccl-de' ),
			'above-50000'  => __( 'Above Rs. 50,000', 'lccl-de' ),
			'discuss'      => __( 'Prefer to discuss', 'lccl-de' ),
		);
	}

	/**
	 * Areas you would like to support.
	 *
	 * @return array<string,string>
	 */
	public static function interest_areas() {
		return array(
			'any-suitable'          => __( 'Any Suitable Community Project', 'lccl-de' ),
			'health-medical'        => __( 'Health & Medical Care', 'lccl-de' ),
			'vision-eye'            => __( 'Vision & Eye Care', 'lccl-de' ),
			'diabetes'              => __( 'Diabetes Awareness & Screening', 'lccl-de' ),
			'blood-donation'        => __( 'Blood Donation', 'lccl-de' ),
			'childhood-cancer'      => __( 'Childhood Cancer Support', 'lccl-de' ),
			'youth-education'       => __( 'Youth & Education', 'lccl-de' ),
			'children-families'     => __( 'Support for Children & Families in Need', 'lccl-de' ),
			'hunger-food'           => __( 'Hunger & Food Assistance', 'lccl-de' ),
			'environment'           => __( 'Environment', 'lccl-de' ),
			'disaster-relief'       => __( 'Disaster Relief', 'lccl-de' ),
			'disabilities'          => __( 'Support for Persons with Disabilities', 'lccl-de' ),
			'senior-citizens'       => __( 'Senior Citizen Support', 'lccl-de' ),
			'humanitarian'          => __( 'Humanitarian Assistance', 'lccl-de' ),
			'community-development' => __( 'Community Development', 'lccl-de' ),
			'other'                 => __( 'Other', 'lccl-de' ),
		);
	}

	/**
	 * Project-specific support types.
	 *
	 * @return array<string,string>
	 */
	public static function project_types() {
		return array(
			'any-suitable'    => __( 'Any suitable community project', 'lccl-de' ),
			'health-medical'  => __( 'Health / Medical Project', 'lccl-de' ),
			'vision-cataract' => __( 'Vision / Cataract Project', 'lccl-de' ),
			'blood-donation'  => __( 'Blood Donation Project', 'lccl-de' ),
			'diabetes'        => __( 'Diabetes Project', 'lccl-de' ),
			'youth-education' => __( 'Youth / Education Project', 'lccl-de' ),
			'hunger-food'     => __( 'Hunger / Food Assistance', 'lccl-de' ),
			'environment'     => __( 'Environment Project', 'lccl-de' ),
			'humanitarian'    => __( 'Humanitarian Project', 'lccl-de' ),
			'disaster-relief' => __( 'Disaster Relief', 'lccl-de' ),
			'other'           => __( 'Other', 'lccl-de' ),
		);
	}

	/**
	 * I Am Registering As options.
	 *
	 * @return array<string,string>
	 */
	public static function registering_as_options() {
		return array(
			'individual'            => __( 'Individual', 'lccl-de' ),
			'company-organisation'  => __( 'Company / Organization', 'lccl-de' ),
			'community-group'       => __( 'Community / Social Group', 'lccl-de' ),
			'other'                 => __( 'Other', 'lccl-de' ),
		);
	}

	/**
	 * Whether the volunteer block should be open.
	 *
	 * @param array $values Sanitised values.
	 * @return bool
	 */
	public static function needs_volunteer( array $values ) {
		$ways = isset( $values['support_ways'] ) && is_array( $values['support_ways'] ) ? $values['support_ways'] : array();
		return in_array( 'volunteer-time', $ways, true ) || in_array( 'professional-skills', $ways, true );
	}

	/**
	 * Whether the financial block should be open.
	 *
	 * @param array $values Sanitised values.
	 * @return bool
	 */
	public static function needs_financial( array $values ) {
		$ways = isset( $values['support_ways'] ) && is_array( $values['support_ways'] ) ? $values['support_ways'] : array();
		return in_array( 'financial-donation', $ways, true );
	}

	/**
	 * Whether the organisation block should be open.
	 *
	 * @param array $values Sanitised values.
	 * @return bool
	 */
	public static function needs_organisation( array $values ) {
		return isset( $values['registering_as'] ) && 'company-organisation' === $values['registering_as'];
	}

	/**
	 * Output checkbox choices.
	 *
	 * @param string $name     Field name without [].
	 * @param array  $options  Key => label.
	 * @param array  $selected Selected keys.
	 */
	public static function render_choices( $name, array $options, array $selected ) {
		echo '<div class="lccl-bdf__choices">';
		foreach ( $options as $value => $label ) {
			$checked = in_array( $value, $selected, true );
			echo '<label class="lccl-bdf__choice">';
			echo '<span class="lccl-bdf__checkbox">';
			printf(
				'<input type="checkbox" name="%1$s[]" value="%2$s"%3$s>',
				esc_attr( $name ),
				esc_attr( $value ),
				checked( $checked, true, false )
			);
			echo '<span class="lccl-bdf__check" aria-hidden="true"></span>';
			echo '</span>';
			echo '<span>' . esc_html( $label ) . '</span>';
			echo '</label>';
		}
		echo '</div>';
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

		wp_enqueue_style(
			'lccl-de-blood-donor-form',
			LCCL_DE_URL . 'assets/css/lccl-de-blood-donor-form.css',
			array(),
			LCCL_DE_VERSION
		);

		wp_enqueue_script(
			'lccl-de-join-projects-form',
			LCCL_DE_URL . 'assets/js/lccl-de-join-projects-form.js',
			array(),
			LCCL_DE_VERSION,
			true
		);

		$flash   = LCCL_DE_Join_Projects_Submissions::consume_flash();
		$values  = $flash['values'];
		$errors  = $flash['errors'];
		$success = ! empty( $flash['success'] );

		ob_start();
		include LCCL_DE_PATH . 'templates/join-our-projects-form.php';

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
				'name'        => __( 'LCCL Join Our Projects', 'lccl-de' ),
				'base'        => self::SHORTCODE,
				'category'    => __( 'LCCL', 'lccl-de' ),
				'description' => __( 'Registration form for Join Our Projects.', 'lccl-de' ),
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
