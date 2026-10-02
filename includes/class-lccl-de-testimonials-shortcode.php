<?php
/**
 * Testimonials Shortcode.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

class LCCL_DE_Testimonials_Shortcode {

	const TAG = 'lccl_testimonials';

	public static function init() {
		add_shortcode( self::TAG, array( __CLASS__, 'render' ) );
		add_action( 'vc_before_init', array( __CLASS__, 'map_vc' ) );
	}

	public static function render( $atts ) {
		// Only display active testimonials
		$active_testimonials = LCCL_DE_Testimonials::get_active();

		if ( empty( $active_testimonials ) ) {
			return '';
		}

		wp_enqueue_style(
			'lccl-de-testimonials',
			LCCL_DE_URL . 'assets/css/lccl-de-testimonials.css',
			array(),
			LCCL_DE_VERSION
		);

		wp_enqueue_script(
			'lccl-de-testimonials',
			LCCL_DE_URL . 'assets/js/lccl-de-testimonials.js',
			array( 'jquery' ),
			LCCL_DE_VERSION,
			true
		);

		ob_start();
		include LCCL_DE_PATH . 'templates/testimonials.php';
		return ob_get_clean();
	}

	public static function map_vc() {
		if ( ! function_exists( 'vc_map' ) ) {
			return;
		}

		vc_map(
			array(
				'name'        => __( 'LCCL Testimonials', 'lccl-de' ),
				'base'        => self::TAG,
				'category'    => __( 'LCCL', 'lccl-de' ),
				'description' => __( 'Displays an auto-scrolling carousel of active testimonials.', 'lccl-de' ),
				'icon'        => 'icon-wpb-ui-separator',
				'params'      => array(),
			)
		);
	}
}
