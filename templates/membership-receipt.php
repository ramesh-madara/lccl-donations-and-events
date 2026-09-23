<?php
/**
 * Membership fee MPGS Hosted Checkout intermediate page.
 *
 * Rendered when ?lccl_mpgs_session=SESSION_ID is in the URL.
 * Loads Checkout.configure() with the session ID and calls
 * Checkout.showPaymentPage() to launch the MPGS hosted payment page.
 *
 * The MPGS checkout.min.js script is enqueued via wp_enqueue_script()
 * in LCCL_DE_Membership_Form::render() before this template is included.
 *
 * @package LCCL_Donations_And_Events
 *
 * @var string $session_id Session ID returned by INITIATE_CHECKOUT.
 * @var string $order_ref  Merchant order reference.
 * @var array  $atts       Shortcode attributes (title, intro).
 */

defined( 'ABSPATH' ) || exit;

$cancel_url = remove_query_arg(
	array( LCCL_DE_Membership_Form::QA_SESSION, LCCL_DE_Membership_Form::QA_ORDER_REF ),
	get_permalink()
);
?>
<div class="lccl-bdf lccl-bdf--membership lccl-mf--receipt">

	<div class="lccl-mf__loading-wrap" id="lccl-mf-loading-wrap">
		<div class="lccl-mf__loading-icon" aria-hidden="true">
			<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#f7c016" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
		</div>
		<h2 class="lccl-mf__loading-title"><?php esc_html_e( 'Redirecting to secure payment&hellip;', 'lccl-de' ); ?></h2>
		<p class="lccl-mf__loading-sub"><?php esc_html_e( 'Please wait while we connect you to the Mastercard secure payment page. Do not close or refresh this window.', 'lccl-de' ); ?></p>
		<span class="lccl-mf__spinner" aria-hidden="true"></span>
	</div>

	<div id="lccl-embedded-host" class="lccl-mf__embedded-host" style="display:none;"></div>

	<?php /* inline Checkout.configure() call – data values sanitised by PHP before output */ ?>
	<script type="text/javascript">
	( function() {
		function lcclMpgsError( error ) {
			document.getElementById( 'lccl-mf-loading-wrap' ).style.display = 'none';
			var el = document.getElementById( 'lccl-mf-error-wrap' );
			if ( el ) {
				el.style.display = '';
				if ( error && error.message ) {
					var msg = el.querySelector( '.lccl-mf__error-msg' );
					if ( msg ) { msg.textContent = error.message; }
				}
			}
		}

		if ( typeof Checkout === 'undefined' ) {
			lcclMpgsError( { message: '<?php echo esc_js( __( 'Could not load the payment gateway script. Please try again.', 'lccl-de' ) ); ?>' } );
			return;
		}

		Checkout.configure({
			session: {
				id: '<?php echo esc_js( $session_id ); ?>'
			},
			interaction: {
				operation: 'PURCHASE',
				merchant: {
					name: '<?php echo esc_js( get_bloginfo( 'name' ) ); ?>'
				},
				displayControl: {
					billingAddress : 'HIDE',
					customerEmail  : 'HIDE',
					shipping       : 'HIDE'
				},
				cancelUrl: '<?php echo esc_js( esc_url( $cancel_url ) ); ?>'
			},
			order: {
				id          : '<?php echo esc_js( $order_ref ); ?>',
				description : '<?php echo esc_js( __( 'LCCL Annual Membership Fee', 'lccl-de' ) ); ?>'
			}
		});

		// Modern Hosted Checkout (v63+): use showPaymentPage()
		Checkout.showPaymentPage();
	}() );
	</script>

	<?php /* Error fallback – hidden by default, shown by JS if gateway script fails */ ?>
	<div id="lccl-mf-error-wrap" class="lccl-mf__notice lccl-mf__notice--error" style="display:none;" role="alert">
		<p><?php esc_html_e( 'An error occurred connecting to the payment gateway.', 'lccl-de' ); ?></p>
		<p class="lccl-mf__error-msg"></p>
		<p>
			<a href="<?php echo esc_url( $cancel_url ); ?>" class="lccl-mf__back-link">
				&larr; <?php esc_html_e( 'Return to the payment form', 'lccl-de' ); ?>
			</a>
		</p>
	</div>

</div>
