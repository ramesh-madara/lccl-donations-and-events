<?php
/**
 * Blood donation programme registration form.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the public blood donor registration form.
 */
class LCCL_DE_Blood_Donor_Form {

	/**
	 * Shortcode tag.
	 */
	const SHORTCODE = 'lccl_blood_donor_form';

	/**
	 * Hook the shortcode into WordPress and WPBakery.
	 */
	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'vc_before_init', array( __CLASS__, 'map' ) );
	}

	/**
	 * Districts of Sri Lanka, used for the district selector.
	 *
	 * @return array
	 */
	public static function get_districts() {
		$districts = array(
			'Ampara',
			'Anuradhapura',
			'Badulla',
			'Batticaloa',
			'Colombo',
			'Galle',
			'Gampaha',
			'Hambantota',
			'Jaffna',
			'Kalutara',
			'Kandy',
			'Kegalle',
			'Kilinochchi',
			'Kurunegala',
			'Mannar',
			'Matale',
			'Matara',
			'Monaragala',
			'Mullaitivu',
			'Nuwara Eliya',
			'Polonnaruwa',
			'Puttalam',
			'Ratnapura',
			'Trincomalee',
			'Vavuniya',
		);

		return apply_filters( 'lccl_de_districts', array_combine( $districts, $districts ) );
	}

	/**
	 * Blood banks grouped by district.
	 *
	 * Derived from the National Blood Transfusion Service network of
	 * hospital based blood banks. NBTS organises these into geographic
	 * clusters rather than districts, so the cluster listing has been
	 * remapped onto the district each facility sits in.
	 *
	 * Labels use the Ministry of Health institution list (31 Dec 2024)
	 * in the form "Place - Hospital name Blood Bank". Base Hospital
	 * Type A and Type B are both shown as "Base Hospital".
	 *
	 * @return array District name => array of bank key => bank label.
	 */
	public static function get_blood_banks_by_district() {
		$banks = array(
			'Ampara'       => array(
				'akkarepattu'    => 'Akkaraipattu - Base Hospital Akkaraipattu Blood Bank',
				'ampara'         => 'Ampara - District General Hospital Ampara Blood Bank',
				'kalmunai-north' => 'Kalmunai North - Base Hospital Kalmunai North Blood Bank',
				'kalmunai-south' => 'Kalmunai South - Ashraff Memorial Hospital Blood Bank',
				'mahaoya'        => 'Mahaoya - Base Hospital Mahaoya Blood Bank',
				'pothuvil'       => 'Pottuvil - Base Hospital Pottuvil Blood Bank',
				'sammanthurai'   => 'Sammanthurai - Base Hospital Sammanthurai Blood Bank',
			),
			'Anuradhapura' => array(
				'anuradhapura'  => 'Anuradhapura - Teaching Hospital Anuradhapura Blood Bank',
				'medawachchiya' => 'Medawachchiya - Base Hospital Medawachchiya Blood Bank',
				'padaviya'      => 'Padaviya - Base Hospital Padaviya Blood Bank',
				'thambuttegama' => 'Thambuttegama - Base Hospital Thambuttegama Blood Bank',
			),
			'Badulla'      => array(
				'badulla'       => 'Badulla - Teaching Hospital Badulla Blood Bank',
				'diyatalawa'    => 'Diyatalawa - Base Hospital Diyatalawa Blood Bank',
				'mahiyanganaya' => 'Mahiyanganaya - Base Hospital Mahiyanganaya Blood Bank',
				'welimada'      => 'Welimada - Base Hospital Welimada Blood Bank',
			),
			'Batticaloa'   => array(
				'batticaloa'     => 'Batticaloa - Teaching Hospital Batticaloa Blood Bank',
				'kalawanchikudi' => 'Kaluwanchikudy - Base Hospital Kaluwanchikudy Blood Bank',
				'kattankudy'     => 'Kattankudy - Base Hospital Kattankudy Blood Bank',
				'valachchenai'   => 'Valaichchenai - Base Hospital Valaichchenai Blood Bank',
			),
			'Colombo'      => array(
				'nbc-narahenpita'     => 'Narahenpita - National Blood Center',
				'nhsl'                => 'Colombo - National Hospital of Sri Lanka Blood Bank',
				'accident-service'    => 'Colombo - Accident Service Blood Bank, National Hospital',
				'apeksha-maharagama'  => 'Maharagama - Apeksha Hospital Blood Bank',
				'army-hospital'       => 'Colombo - Army Hospital Blood Bank',
				'awissawella'         => 'Avissawella - District General Hospital Avissawella Blood Bank',
				'cebh-mulleriyawa'    => 'Mulleriyawa - Colombo East Base Hospital Blood Bank',
				'cshw'                => 'Colombo - Castle Street Hospital for Women Blood Bank',
				'csth-kalubowila'     => 'Kalubowila - Colombo South Teaching Hospital Blood Bank',
				'dmh'                 => 'Colombo - De Soysa Maternity Hospital Blood Bank',
				'homagama'            => 'Homagama - Base Hospital Homagama Blood Bank',
				'idh-angoda'          => 'Angoda - National Institute of Infectious Diseases Blood Bank',
				'kdu'                 => 'Werahera - University Hospital KDU Blood Bank',
				'lrh'                 => 'Colombo - Lady Ridgeway Hospital for Children Blood Bank',
				'nindt-maligawaththa' => 'Maligawatta - National Institute for Nephrology, Dialysis and Transplantation Blood Bank',
				'sjgh'                => 'Sri Jayewardenepura - Sri Jayewardenepura General Hospital Blood Bank',
			),
			'Galle'        => array(
				'balapitiya' => 'Balapitiya - Base Hospital Balapitiya Blood Bank',
				'elpitiya'   => 'Elpitiya - Base Hospital Elpitiya Blood Bank',
				'karapitiya' => 'Karapitiya - National Hospital Galle Blood Bank',
				'mahamodara' => 'Mahamodara - German Sri Lanka Friendship Hospital for Women Blood Bank',
				'udugama'    => 'Udugama - Base Hospital Udugama Blood Bank',
			),
			'Gampaha'      => array(
				'cnth-ragama'   => 'Ragama - Colombo North Teaching Hospital Blood Bank',
				'gampaha'       => 'Gampaha - District General Hospital Gampaha Blood Bank',
				'kiribathgoda'  => 'Kiribathgoda - Base Hospital Kiribathgoda Blood Bank',
				'meerigama'     => 'Mirigama - Base Hospital Mirigama Blood Bank',
				'minuwangoda'   => 'Minuwangoda - Base Hospital Minuwangoda Blood Bank',
				'negombo'       => 'Negombo - District General Hospital Negombo Blood Bank',
				'wathupitiwala' => 'Wathupitiwala - Base Hospital Wathupitiwala Blood Bank',
				'welisara'      => 'Welisara - National Hospital for Respiratory Diseases Blood Bank',
			),
			'Hambantota'   => array(
				'hambantota'    => 'Hambantota - District General Hospital Hambantota Blood Bank',
				'tangalle'      => 'Tangalle - Base Hospital Tangalle Blood Bank',
				'tissamaharama' => 'Tissamaharama - Base Hospital Tissamaharama Blood Bank',
				'walasmulla'    => 'Walasmulla - Base Hospital Walasmulla Blood Bank',
			),
			'Jaffna'       => array(
				'chavakachcheri' => 'Chavakachcheri - Base Hospital Chavakachcheri Blood Bank',
				'jaffna'         => 'Jaffna - Teaching Hospital Jaffna Blood Bank',
				'kayts'          => 'Kayts - Base Hospital Kayts Blood Bank',
				'point-pedro'    => 'Point Pedro - Base Hospital Point Pedro Blood Bank',
				'tellippalai'    => 'Tellipalai - Base Hospital Tellipalai Blood Bank',
			),
			'Kalutara'     => array(
				'horana'      => 'Horana - District General Hospital Horana Blood Bank',
				'kalutara'    => 'Kalutara - Teaching Hospital Kalutara Blood Bank',
				'kethumathie' => 'Panadura - Kethumathi Women\'s Hospital Blood Bank',
				'panadura'    => 'Panadura - Base Hospital Panadura Blood Bank',
			),
			'Kandy'        => array(
				'kandy'        => 'Kandy - National Hospital Kandy Blood Bank',
				'gampola'      => 'Gampola - Base Hospital Gampola Blood Bank',
				'nawalapitiya' => 'Nawalapitiya - District General Hospital Nawalapitiya Blood Bank',
				'peradeniya'   => 'Peradeniya - Teaching Hospital Peradeniya Blood Bank',
				'theldeniya'   => 'Teldeniya - Base Hospital Teldeniya Blood Bank',
			),
			'Kegalle'      => array(
				'karawanella' => 'Karawanella - Base Hospital Karawanella Blood Bank',
				'kegalle'     => 'Kegalle - District General Hospital Kegalle Blood Bank',
				'mawanella'   => 'Mawanella - Base Hospital Mawanella Blood Bank',
				'warakapola'  => 'Warakapola - Base Hospital Warakapola Blood Bank',
			),
			'Kilinochchi'  => array(
				'kilinochchi' => 'Kilinochchi - District General Hospital Kilinochchi Blood Bank',
			),
			'Kurunegala'   => array(
				'dambadeniya'  => 'Dambadeniya - Base Hospital Dambadeniya Blood Bank',
				'galgamuwa'    => 'Galgamuwa - Base Hospital Galgamuwa Blood Bank',
				'kuliyapitiya' => 'Kuliyapitiya - Teaching Hospital Kuliyapitiya Blood Bank',
				'kurunegala'   => 'Kurunegala - Teaching Hospital Kurunegala Blood Bank',
				'nikaweratiya' => 'Nikaweratiya - Base Hospital Nikaweratiya Blood Bank',
			),
			'Mannar'       => array(
				'mannar' => 'Mannar - District General Hospital Mannar Blood Bank',
			),
			'Matale'       => array(
				'dambulla' => 'Dambulla - Base Hospital Dambulla Blood Bank',
				'matale'   => 'Matale - District General Hospital Matale Blood Bank',
			),
			'Matara'       => array(
				'deniyaya'      => 'Deniyaya - Base Hospital Deniyaya Blood Bank',
				'kamburugamuwa' => 'Kamburugamuwa - District General Hospital Kamburugamuwa Blood Bank',
				'kamburupitiya' => 'Kamburupitiya - Base Hospital Kamburupitiya Blood Bank',
				'matara'        => 'Matara - District General Hospital Matara Blood Bank',
			),
			'Monaragala'   => array(
				'bibila'     => 'Bibile - Professor Senaka Bibile Memorial Hospital Blood Bank',
				'monaragala' => 'Monaragala - District General Hospital Monaragala Blood Bank',
				'wellawaya'  => 'Wellawaya - Base Hospital Wellawaya Blood Bank',
			),
			'Mullaitivu'   => array(
				'mullaitivu' => 'Mullaitivu - District General Hospital Mullaitivu Blood Bank',
			),
			'Nuwara Eliya' => array(
				'dikkoya'        => 'Dickoya - Base Hospital Dickoya Blood Bank',
				'nuwara-eliya'   => 'Nuwara Eliya - District General Hospital Nuwara Eliya Blood Bank',
				'rikillagaskada' => 'Rikillagaskada - Base Hospital Rikillagaskada Blood Bank',
			),
			'Polonnaruwa'  => array(
				'dehiattakandiya' => 'Dehiattakandiya - Base Hospital Dehiattakandiya Blood Bank',
				'medirigiriya'    => 'Medirigiriya - Base Hospital Medirigiriya Blood Bank',
				'polonnaruwa'     => 'Polonnaruwa - District General Hospital Polonnaruwa Blood Bank',
			),
			'Puttalam'     => array(
				'chilaw'    => 'Chilaw - District General Hospital Chilaw Blood Bank',
				'kalpitiya' => 'Kalpitiya - Base Hospital Kalpitiya Blood Bank',
				'marawila'  => 'Marawila - Base Hospital Marawila Blood Bank',
				'puttalam'  => 'Puttalam - Base Hospital Puttalam Blood Bank',
			),
			'Ratnapura'    => array(
				'balangoda'    => 'Balangoda - Base Hospital Balangoda Blood Bank',
				'eheliyagoda'  => 'Eheliyagoda - Base Hospital Eheliyagoda Blood Bank',
				'embilipitiya' => 'Embilipitiya - District General Hospital Embilipitiya Blood Bank',
				'kahawatta'    => 'Kahawatta - Base Hospital Kahawatta Blood Bank',
				'ratnapura'    => 'Ratnapura - Teaching Hospital Ratnapura Blood Bank',
			),
			'Trincomalee'  => array(
				'kantale'     => 'Kanthale - Base Hospital Kanthale Blood Bank',
				'kinniya'     => 'Kinniya - Base Hospital Kinniya Blood Bank',
				'muththur'    => 'Mutur - Base Hospital Mutur Blood Bank',
				'trincomalee' => 'Trincomalee - District General Hospital Trincomalee Blood Bank',
			),
			'Vavuniya'     => array(
				'chettikulam' => 'Cheddikulam - Base Hospital Cheddikulam Blood Bank',
				'vavuniya'    => 'Vavuniya - District General Hospital Vavuniya Blood Bank',
			),
		);

		return apply_filters( 'lccl_de_blood_banks_by_district', $banks );
	}

	/**
	 * Blood banks for a single district, or every bank when no district is given.
	 *
	 * @param string $district District name.
	 * @return array Bank key => bank label.
	 */
	public static function get_blood_banks( $district = '' ) {
		$grouped = self::get_blood_banks_by_district();

		if ( '' !== $district ) {
			$banks = isset( $grouped[ $district ] ) ? $grouped[ $district ] : array();
		} else {
			$banks = array();
			foreach ( $grouped as $district_banks ) {
				$banks += $district_banks;
			}
		}

		return apply_filters( 'lccl_de_blood_banks', $banks, $district );
	}

	/**
	 * Whether a blood bank belongs to a district.
	 *
	 * @param string $bank     Blood bank key.
	 * @param string $district District name.
	 * @return bool
	 */
	public static function is_valid_blood_bank( $bank, $district ) {
		return array_key_exists( $bank, self::get_blood_banks( $district ) );
	}

	/**
	 * Preferred donation setting options.
	 *
	 * @return array
	 */
	public static function get_donation_preferences() {
		$preferences = array(
			'blood-bank' => __( 'At a blood bank', 'lccl-de' ),
			'campaign'   => __( 'At a Lions blood donation campaign', 'lccl-de' ),
			'either'     => __( 'Either', 'lccl-de' ),
		);

		return apply_filters( 'lccl_de_donation_preferences', $preferences );
	}

	/**
	 * Previous donation history options.
	 *
	 * @return array
	 */
	public static function get_donation_history_options() {
		$options = array(
			'yes'      => __( 'Yes', 'lccl-de' ),
			'no'       => __( 'No', 'lccl-de' ),
			'not-sure' => __( 'Not sure', 'lccl-de' ),
		);

		return apply_filters( 'lccl_de_donation_history_options', $options );
	}

	/**
	 * Preferred contact method options.
	 *
	 * @return array
	 */
	public static function get_contact_methods() {
		$methods = array(
			'phone'    => __( 'Phone call', 'lccl-de' ),
			'whatsapp' => __( 'WhatsApp', 'lccl-de' ),
			'sms'      => __( 'SMS', 'lccl-de' ),
			'email'    => __( 'Email', 'lccl-de' ),
		);

		return apply_filters( 'lccl_de_contact_methods', $methods );
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
				'intro' => __( 'Please provide the information below so we can identify a convenient blood bank or Lions blood donation campaign in your area.', 'lccl-de' ),
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
			'lccl-de-blood-donor-form',
			LCCL_DE_URL . 'assets/js/lccl-de-blood-donor-form.js',
			array(),
			LCCL_DE_VERSION,
			true
		);

		$flash   = LCCL_DE_Blood_Donor_Submissions::consume_flash();
		$values  = $flash['values'];
		$errors  = $flash['errors'];
		$success = ! empty( $flash['success'] );

		ob_start();
		include LCCL_DE_PATH . 'templates/blood-donor-form.php';

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
				'name'        => __( 'LCCL Blood Donor Registration', 'lccl-de' ),
				'base'        => self::SHORTCODE,
				'category'    => __( 'LCCL', 'lccl-de' ),
				'description' => __( 'Registration form for the blood donation programme.', 'lccl-de' ),
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
						'value'      => __( 'Please provide the information below so we can identify a convenient blood bank or Lions blood donation campaign in your area.', 'lccl-de' ),
					),
				),
			)
		);
	}
}
