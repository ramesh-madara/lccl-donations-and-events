<?php
/**
 * MPGS REST API v66 client.
 *
 * Handles server-to-server communication with the Mastercard Payment Gateway
 * Services (MPGS) Hosted Checkout API.  All credentials are read from
 * wp_options via LCCL_DE_Settings; no values are hardcoded here.
 *
 * API flow (modern Hosted Checkout – migration guide steps 1-4):
 *   1. INITIATE_CHECKOUT  (POST /session)   → returns session.id + successIndicator
 *   2. Browser loads checkout.min.js and calls Checkout.showPaymentPage()
 *   3. MPGS redirects browser to returnUrl with ?resultIndicator=…
 *   4. We compare resultIndicator vs successIndicator stored in step 1
 *   5. RETRIEVE_ORDER    (GET /order/{ref}) → confirms receipt server-side
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stateless service class – every method is static so callers need no instance.
 */
class LCCL_DE_MPGS_Client {

	/**
	 * Recommended API version per migration guide (v66).
	 */
	const DEFAULT_API_VERSION = 66;

	/**
	 * HTTP timeout for gateway requests, in seconds.
	 */
	const TIMEOUT = 30;

	// ------------------------------------------------------------------
	// Public API
	// ------------------------------------------------------------------

	/**
	 * Send INITIATE_CHECKOUT to MPGS and return key fields on success.
	 *
	 * @param array $args {
	 *     Required values for the session request.
	 *
	 *     @type string $order_ref   Unique merchant order reference (e.g. 'LCCL-MF-20260923-ABC12345').
	 *     @type float  $amount      Total in LKR (e.g. 22540.00).
	 *     @type string $currency    ISO 4217 code (default 'LKR').
	 *     @type string $return_url  Full URL MPGS redirects the browser to after payment.
	 *     @type string $description Optional short description shown on the gateway page.
	 * }
	 * @return array{session_id:string,success_indicator:string}|WP_Error
	 */
	/**
	 * Send INITIATE_CHECKOUT to MPGS and return key fields on success.
	 *
	 * @param array  $args {
	 *     Required values for the session request.
	 *
	 *     @type string $order_ref   Unique merchant order reference (e.g. 'LCCL-MF-20260923-ABC12345').
	 *     @type float  $amount      Total in LKR (e.g. 22540.00).
	 *     @type string $currency    ISO 4217 code (default 'LKR').
	 *     @type string $return_url  Full URL MPGS redirects the browser to after payment.
	 *     @type string $description Optional short description shown on the gateway page.
	 * }
	 * @param string $profile Merchant profile key (membership, donations, project_1, project_2).
	 * @return array{session_id:string,success_indicator:string}|WP_Error
	 */
	public static function initiate_checkout( array $args, $profile = LCCL_DE_Settings::PROFILE_MEMBERSHIP ) {
		$cfg = self::get_config( $profile );
		if ( is_wp_error( $cfg ) ) {
			return $cfg;
		}

		$order_ref   = sanitize_text_field( isset( $args['order_ref'] ) ? $args['order_ref'] : '' );
		$amount      = round( (float) ( isset( $args['amount'] ) ? $args['amount'] : 0 ), 2 );
		$currency    = strtoupper( sanitize_text_field( isset( $args['currency'] ) ? $args['currency'] : 'LKR' ) );
		$return_url  = esc_url_raw( isset( $args['return_url'] ) ? $args['return_url'] : '' );
		$description = sanitize_text_field( isset( $args['description'] ) ? $args['description'] : '' );

		if ( '' === $order_ref || $amount <= 0 || '' === $return_url ) {
			return new WP_Error( 'lccl_mpgs_bad_args', __( 'MPGS: order_ref, amount, and return_url are required.', 'lccl-de' ) );
		}

		$body = array(
			'apiOperation' => 'INITIATE_CHECKOUT',
			'interaction'  => array(
				'operation' => 'PURCHASE',
				'returnUrl' => $return_url,
				'merchant'  => array(
					'name' => get_bloginfo( 'name' ),
				),
			),
			'order'        => array(
				'id'          => $order_ref,
				'amount'      => $amount,
				'currency'    => $currency,
				'description' => $description,
			),
		);

		$url      = self::build_request_url( $cfg, 'session' );
		$response = self::post( $url, $cfg, $body );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = $response;

		if ( empty( $data['result'] ) || 'SUCCESS' !== $data['result'] ) {
			$explanation = isset( $data['error']['explanation'] ) ? $data['error']['explanation'] : __( 'Unknown gateway error.', 'lccl-de' );
			return new WP_Error( 'lccl_mpgs_initiate_failed', $explanation );
		}

		if ( empty( $data['successIndicator'] ) || empty( $data['session']['id'] ) ) {
			return new WP_Error( 'lccl_mpgs_missing_fields', __( 'MPGS response missing session ID or successIndicator.', 'lccl-de' ) );
		}

		return array(
			'session_id'        => (string) $data['session']['id'],
			'success_indicator' => (string) $data['successIndicator'],
			'session_version'   => isset( $data['session']['version'] ) ? (string) $data['session']['version'] : '',
		);
	}

	/**
	 * Retrieve order details from MPGS to confirm a completed payment server-side.
	 *
	 * @param string $order_ref Merchant order reference.
	 * @param string $profile   Merchant profile key.
	 * @return array|WP_Error   Full decoded MPGS order array, or WP_Error.
	 */
	public static function retrieve_order( $order_ref, $profile = LCCL_DE_Settings::PROFILE_MEMBERSHIP ) {
		$cfg = self::get_config( $profile );
		if ( is_wp_error( $cfg ) ) {
			return $cfg;
		}

		$order_ref = sanitize_text_field( $order_ref );
		$url       = self::build_request_url( $cfg, 'order/' . rawurlencode( $order_ref ) );

		return self::get( $url, $cfg );
	}

	/**
	 * Verify that a resultIndicator from the browser matches the successIndicator
	 * stored during session creation.  This must be called before any DB update.
	 *
	 * @param string $result_indicator  Value from $_GET['resultIndicator'].
	 * @param string $success_indicator Value stored in lccl_de_payments row.
	 * @return bool
	 */
	public static function verify_result_indicator( $result_indicator, $success_indicator ) {
		$ri = (string) $result_indicator;
		$si = (string) $success_indicator;

		if ( '' === $ri || '' === $si ) {
			return false;
		}

		return hash_equals( $si, $ri );
	}

	/**
	 * Pull the last successful transaction receipt from a RETRIEVE_ORDER response.
	 *
	 * @param array $order_data Full decoded MPGS order response.
	 * @return string  Gateway receipt ID, or empty string if not found.
	 */
	public static function extract_receipt( array $order_data ) {
		if ( empty( $order_data['transaction'] ) || ! is_array( $order_data['transaction'] ) ) {
			return '';
		}

		// The most recent transaction is last in the array.
		$last = end( $order_data['transaction'] );
		if ( empty( $last['result'] ) || 'SUCCESS' !== $last['result'] ) {
			return '';
		}

		return isset( $last['transaction']['receipt'] ) ? (string) $last['transaction']['receipt'] : '';
	}

	/**
	 * Decrypted MPGS configuration for a profile from wp_options.
	 *
	 * @param string $profile Merchant profile key.
	 * @return array{label:string,gateway_url:string,api_version:int,merchant_id:string,api_password:string}|WP_Error
	 */
	public static function get_config( $profile = LCCL_DE_Settings::PROFILE_MEMBERSHIP ) {
		$cfg = LCCL_DE_Settings::get_mpgs( $profile );

		if ( empty( $cfg['enabled'] ) ) {
			return new WP_Error(
				'lccl_mpgs_disabled',
				sprintf(
					/* translators: %s: profile label */
					__( 'Online payments for "%s" are currently turned off. Please contact the club administrator.', 'lccl-de' ),
					! empty( $cfg['label'] ) ? $cfg['label'] : $profile
				)
			);
		}

		if (
			empty( $cfg['gateway_url'] ) ||
			empty( $cfg['merchant_id'] ) ||
			empty( $cfg['api_password'] )
		) {
			return new WP_Error(
				'lccl_mpgs_not_configured',
				sprintf(
					/* translators: %s: profile label */
					__( 'MPGS payment gateway is not configured for "%s". Please enter credentials in LCCL Programs → Payment Gateway.', 'lccl-de' ),
					! empty( $cfg['label'] ) ? $cfg['label'] : $profile
				)
			);
		}

		return $cfg;
	}

	/**
	 * Whether all required credentials for a profile have been saved.
	 *
	 * @param string $profile Merchant profile key.
	 * @return bool
	 */
	public static function is_configured( $profile = LCCL_DE_Settings::PROFILE_MEMBERSHIP ) {
		return ! is_wp_error( self::get_config( $profile ) );
	}

	/**
	 * Full JS URL for the modern Hosted Checkout script (v63+).
	 *
	 * @param string $profile Merchant profile key.
	 * @return string
	 */
	public static function checkout_js_url( $profile = LCCL_DE_Settings::PROFILE_MEMBERSHIP ) {
		$cfg = self::get_config( $profile );
		if ( is_wp_error( $cfg ) ) {
			return '';
		}

		return trailingslashit( $cfg['gateway_url'] ) . 'static/checkout/checkout.min.js';
	}

	// ------------------------------------------------------------------
	// Private helpers
	// ------------------------------------------------------------------

	/**
	 * Build the full REST endpoint URL.
	 *
	 * Pattern: {gateway_url}api/rest/version/{api_version}/merchant/{merchant_id}/{path}
	 *
	 * @param array  $cfg  Config array from get_config().
	 * @param string $path Endpoint path (e.g. 'session', 'order/REF').
	 * @return string
	 */
	private static function build_request_url( array $cfg, $path ) {
		return sprintf(
			'%sapi/rest/version/%d/merchant/%s/%s',
			trailingslashit( $cfg['gateway_url'] ),
			(int) $cfg['api_version'],
			rawurlencode( $cfg['merchant_id'] ),
			$path
		);
	}

	/**
	 * Base64-encoded Basic auth header value.
	 *
	 * MPGS uses: merchant.{MERCHANT_ID}:{API_PASSWORD}
	 *
	 * @param array $cfg Config array from get_config().
	 * @return string
	 */
	private static function build_auth_header( array $cfg ) {
		return 'Basic ' . base64_encode( 'merchant.' . $cfg['merchant_id'] . ':' . $cfg['api_password'] );
	}

	/**
	 * POST JSON to the gateway.
	 *
	 * @param string $url  Full endpoint URL.
	 * @param array  $cfg  Config from get_config().
	 * @param array  $body PHP array that will be JSON-encoded.
	 * @return array|WP_Error Decoded response body or WP_Error.
	 */
	private static function post( $url, array $cfg, array $body ) {
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => self::TIMEOUT,
				'headers' => array(
					'Authorization' => self::build_auth_header( $cfg ),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		return self::parse_response( $response );
	}

	/**
	 * GET from the gateway.
	 *
	 * @param string $url Full endpoint URL.
	 * @param array  $cfg Config from get_config().
	 * @return array|WP_Error Decoded response body or WP_Error.
	 */
	private static function get( $url, array $cfg ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => self::TIMEOUT,
				'headers' => array(
					'Authorization' => self::build_auth_header( $cfg ),
				),
			)
		);

		return self::parse_response( $response );
	}

	/**
	 * Decode a wp_remote_* response or return WP_Error.
	 *
	 * @param array|WP_Error $response Raw wp_remote_* response.
	 * @return array|WP_Error
	 */
	private static function parse_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'lccl_mpgs_bad_response',
				/* translators: HTTP status code */
				sprintf( __( 'MPGS returned an invalid response (HTTP %d).', 'lccl-de' ), $code )
			);
		}

		return $data;
	}
}
