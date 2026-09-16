<?php
/**
 * Front end shortcodes.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the plugin shortcodes and their WPBakery equivalents.
 */
class LCCL_DE_Shortcodes {

	/**
	 * Shortcode tag for the scaffolding/test card.
	 */
	const HELLO = 'lccl_hello';

	/**
	 * Hook the shortcodes into WordPress and WPBakery.
	 */
	public static function init() {
		add_shortcode( self::HELLO, array( __CLASS__, 'render_hello' ) );
		add_action( 'vc_before_init', array( __CLASS__, 'map_hello' ) );
	}

	/**
	 * Render the hello world card.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_hello( $atts ) {
		$atts = shortcode_atts(
			array(
				'title'   => __( 'Hello from LCCL', 'lccl-de' ),
				'message' => __( 'The plugin is active and this UI is rendering from a shortcode.', 'lccl-de' ),
			),
			$atts,
			self::HELLO
		);

		wp_enqueue_style(
			'lccl-de',
			LCCL_DE_URL . 'assets/css/lccl-de.css',
			array(),
			LCCL_DE_VERSION
		);

		ob_start();
		?>
		<div class="lccl-de-card">
			<span class="lccl-de-card__badge"><?php esc_html_e( 'LCCL Donations and Events', 'lccl-de' ); ?></span>

			<h3 class="lccl-de-card__title"><?php echo esc_html( $atts['title'] ); ?></h3>
			<p class="lccl-de-card__message"><?php echo esc_html( $atts['message'] ); ?></p>

			<ul class="lccl-de-card__meta">
				<li>
					<span><?php esc_html_e( 'Shortcode', 'lccl-de' ); ?></span>
					<code>[<?php echo esc_html( self::HELLO ); ?>]</code>
				</li>
				<li>
					<span><?php esc_html_e( 'Version', 'lccl-de' ); ?></span>
					<code><?php echo esc_html( LCCL_DE_VERSION ); ?></code>
				</li>
				<li>
					<span><?php esc_html_e( 'Signed in', 'lccl-de' ); ?></span>
					<code><?php echo is_user_logged_in() ? esc_html__( 'yes', 'lccl-de' ) : esc_html__( 'no', 'lccl-de' ); ?></code>
				</li>
			</ul>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Expose the shortcode as a WPBakery element.
	 */
	public static function map_hello() {
		if ( ! function_exists( 'vc_map' ) ) {
			return;
		}

		vc_map(
			array(
				'name'        => __( 'LCCL Hello', 'lccl-de' ),
				'base'        => self::HELLO,
				'category'    => __( 'LCCL', 'lccl-de' ),
				'description' => __( 'Scaffolding card used to verify the plugin renders.', 'lccl-de' ),
				'icon'        => 'icon-wpb-ui-separator',
				'params'      => array(
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Title', 'lccl-de' ),
						'param_name'  => 'title',
						'value'       => __( 'Hello from LCCL', 'lccl-de' ),
						'admin_label' => true,
					),
					array(
						'type'       => 'textarea',
						'heading'    => __( 'Message', 'lccl-de' ),
						'param_name' => 'message',
						'value'      => __( 'The plugin is active and this UI is rendering from a shortcode.', 'lccl-de' ),
					),
				),
			)
		);
	}
}
