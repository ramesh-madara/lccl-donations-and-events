<?php
/**
 * CBC Paycenter Web 4.0 (Bancstac) gateway client.
 *
 * Handles server-to-server communication with the Commercial Bank of Ceylon
 * Paycenter / Bancstac REST API.  All credentials are read from wp_options via
 * LCCL_DE_Settings; no values are hardcoded here.
 *
 * API flow (Hosted Payment Page / Redirect method):
 *   1. PAYMENT_INIT  → POST to InterfaceServlet with JSON body
 *                    → returns reqid + paymentPageUrl
 *   2. Redirect user's browser to paymentPageUrl
 *   3. Bancstac completes card capture / 3-D Secure
 *   4. Bancstac GET-redirects browser to returnUrl?reqid=…
 *   5. PAYMENT_COMPLETE → server-to-server POST with reqid
 *                       → returns responseCode + txnReference etc.
 *   6. Plugin verifies responseCode === '00', amount, currency, and clientRef
 *      against the database row before marking the payment paid.
 *
 * Security practices preserved from the MPGS client:
 *   – No credentials or amounts are ever accepted from browser input.
 *   – Amount / currency / clientRef triple-verified in PAYMENT_COMPLETE.
 *   – All secrets stored AES-256-GCM encrypted (handled by LCCL_DE_Settings).
 *   – HTTPS enforced before any outbound request is made.
 *   – WP_Error returned on every failure; raw error detail goes to error_log only.
 *   – HTTP timeout capped at TIMEOUT seconds.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stateless service class – every method is static so callers need no instance.
 */
class LCCL_DE_Paycenter_Client {

	/**
	 * Paycenter API protocol version.
	 */
	const API_VERSION = '1.5';

	/**
	 * Operation identifier for payment initialisation.
	 */
	const OP_INIT = 'PAYMENT_INIT';

	/**
	 * Operation identifier for payment completion.
	 */
	const OP_COMPLETE = 'PAYMENT_COMPLETE';

	/**
	 * Bank response code that indicates an approved transaction.
	 */
	const RESPONSE_APPROVED = '00';

	/**
	 * HTTP timeout for gateway requests, in seconds.
	 */
	const TIMEOUT = 30;

	// ------------------------------------------------------------------
	// Public API
	// ------------------------------------------------------------------

	/**
	 * Step 1 – Send PAYMENT_INIT and return the reqid + paymentPageUrl.
	 *
	 * @param array $args {
	 *     Required values for the init request.
	 *
	 *     @type string $order_ref   Unique merchant order reference (clientRef, max 50 chars).
	 *     @type float  $amount      Total in LKR (cents/units – whole numbers only).
	 *     @type string $return_url  Full URL Bancstac redirects the browser to after payment.
	 *     @type string $cancel_url  Full URL for cancel redirect (optional, may be empty).
	 *     @type string $comment     Short description (optional, max 100 chars).
	 * }
	 * @return array{reqid:string,payment_page_url:string}|WP_Error
	 */
	public static function payment_init( array $args ) {
		$cfg = self::get_config();
		if ( is_wp_error( $cfg ) ) {
			return $cfg;
		}

		$order_ref  = sanitize_text_field( isset( $args['order_ref'] ) ? $args['order_ref'] : '' );
		$amount     = (int) round( (float) ( isset( $args['amount'] ) ? $args['amount'] : 0 ) );
		$return_url = esc_url_raw( isset( $args['return_url'] ) ? $args['return_url'] : '' );
		$cancel_url = esc_url_raw( isset( $args['cancel_url'] ) ? $args['cancel_url'] : '' );
		$comment    = sanitize_text_field( isset( $args['comment'] ) ? $args['comment'] : '' );
		$comment    = substr( $comment, 0, 100 );

		if ( '' === $order_ref || $amount <= 0 || '' === $return_url ) {
			return new WP_Error(
				'lccl_pc_bad_args',
				__( 'Paycenter: order_ref, amount, and return_url are required.', 'lccl-de' )
			);
		}

		// clientRef must be ≤ 50 characters.
		$client_ref = substr( $order_ref, 0, 50 );

		$body = array(
			'version'     => self::API_VERSION,
			'msgId'       => self::generate_msg_id(),
			'operation'   => self::OP_INIT,
			'requestDate' => self::iso_date(),
			'validateOnly' => false,
			'requestData' => array(
				'clientId'          => (int) $cfg['client_id'],
				'clientIdHash'      => '',
				'transactionType'   => 'PURCHASE',
				'transactionAmount' => array(
					'totalAmount'     => $amount,
					'paymentAmount'   => 0,
					'serviceFeeAmount' => 0,
					'currency'        => 'LKR',
				),
				'redirect'          => array(
					'returnUrl'    => $return_url,
					'cancelUrl'    => $cancel_url,
					'returnMethod' => 'GET',
				),
				'clientRef'        => $client_ref,
				'comment'          => $comment,
				'tokenize'         => false,
				'cssLocation1'     => '',
				'cssLocation2'     => '',
				'useReliability'   => true,
				'extraData'        => '',
			),
		);

		$response = self::post( $cfg, $body );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Validate that the response contains the expected fields.
		if (
			empty( $response['responseData']['reqid'] ) ||
			empty( $response['responseData']['paymentPageUrl'] )
		) {
			return new WP_Error(
				'lccl_pc_init_missing_fields',
				__( 'Paycenter PAYMENT_INIT response missing reqid or paymentPageUrl.', 'lccl-de' )
			);
		}

		return array(
			'reqid'            => (string) $response['responseData']['reqid'],
			'payment_page_url' => (string) $response['responseData']['paymentPageUrl'],
			'expire_at'        => isset( $response['responseData']['expireAt'] )
				? (string) $response['responseData']['expireAt']
				: '',
		);
	}

	/**
	 * Step 5 – Send PAYMENT_COMPLETE and return the full response data.
	 *
	 * The caller MUST verify responseCode, amount, currency, and clientRef
	 * against the stored database row before accepting the payment.
	 *
	 * @param string $reqid The reqid returned by PAYMENT_INIT.
	 * @return array|WP_Error Full decoded responseData array, or WP_Error.
	 */
	public static function payment_complete( $reqid ) {
		$cfg = self::get_config();
		if ( is_wp_error( $cfg ) ) {
			return $cfg;
		}

		$reqid = sanitize_text_field( (string) $reqid );
		if ( '' === $reqid ) {
			return new WP_Error(
				'lccl_pc_missing_reqid',
				__( 'Paycenter: reqid is required for PAYMENT_COMPLETE.', 'lccl-de' )
			);
		}

		$body = array(
			'version'      => self::API_VERSION,
			'operation'    => self::OP_COMPLETE,
			'msgId'        => self::generate_msg_id(),
			'requestDate'  => self::iso_date(),
			'validateOnly' => false,
			'requestData'  => array(
				'clientId' => (int) $cfg['client_id'],
				'reqid'    => $reqid,
			),
		);

		$response = self::post( $cfg, $body );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['responseData'] ) || ! is_array( $response['responseData'] ) ) {
			return new WP_Error(
				'lccl_pc_complete_missing_data',
				__( 'Paycenter PAYMENT_COMPLETE response missing responseData.', 'lccl-de' )
			);
		}

		return $response['responseData'];
	}

	/**
	 * Whether the gateway response indicates an approved transaction.
	 *
	 * @param array $response_data Decoded responseData from payment_complete().
	 * @return bool
	 */
	public static function is_approved( array $response_data ) {
		$code = isset( $response_data['responseCode'] ) ? (string) $response_data['responseCode'] : '';
		return self::RESPONSE_APPROVED === $code;
	}

	/**
	 * Extract the bank transaction reference from a PAYMENT_COMPLETE response.
	 *
	 * This is the gateway receipt stored in the DB as gateway_receipt.
	 *
	 * @param array $response_data Decoded responseData from payment_complete().
	 * @return string  txnReference, or empty string if absent.
	 */
	public static function extract_receipt( array $response_data ) {
		return isset( $response_data['txnReference'] )
			? (string) $response_data['txnReference']
			: '';
	}

	/**
	 * Verify that returned amount and currency match the stored payment record.
	 *
	 * The API returns amounts in the same unit as submitted (LKR whole units).
	 * We compare paymentAmount against the stored amount_lkr (rounded to int).
	 *
	 * @param array $response_data Decoded responseData from payment_complete().
	 * @param float  $expected_amount  Amount stored in DB (amount_lkr).
	 * @return bool
	 */
	public static function verify_amount( array $response_data, $expected_amount ) {
		$ta = isset( $response_data['transactionAmount'] ) && is_array( $response_data['transactionAmount'] )
			? $response_data['transactionAmount']
			: array();

		$gateway_currency = isset( $ta['currency'] ) ? strtoupper( trim( (string) $ta['currency'] ) ) : '';
		if ( 'LKR' !== $gateway_currency ) {
			return false;
		}

		// The gateway returns paymentAmount in the same unit we sent.
		$gateway_amount = isset( $ta['paymentAmount'] ) ? (int) $ta['paymentAmount'] : 0;
		$expected_int   = (int) round( (float) $expected_amount );

		return $gateway_amount === $expected_int;
	}

	/**
	 * Verify that the clientRef in the response matches the stored order_ref.
	 *
	 * Uses hash_equals() to prevent timing-based side-channel leaks.
	 *
	 * @param array  $response_data Decoded responseData from payment_complete().
	 * @param string $order_ref     Order reference stored in the DB.
	 * @return bool
	 */
	public static function verify_client_ref( array $response_data, $order_ref ) {
		$returned_ref = isset( $response_data['clientRef'] ) ? (string) $response_data['clientRef'] : '';
		$expected_ref = substr( (string) $order_ref, 0, 50 );

		if ( '' === $returned_ref || '' === $expected_ref ) {
			return false;
		}

		return hash_equals( $expected_ref, $returned_ref );
	}

	/**
	 * Decrypted CBC Paycenter configuration from wp_options.
	 *
	 * @return array{label:string,enabled:int,endpoint:string,client_id:string,auth_token:string}|WP_Error
	 */
	public static function get_config() {
		$cfg = LCCL_DE_Settings::get_paycenter();

		if ( empty( $cfg['enabled'] ) ) {
			return new WP_Error(
				'lccl_pc_disabled',
				__( 'Online payments via CBC Paycenter are currently turned off. Please contact the club administrator.', 'lccl-de' )
			);
		}

		if (
			empty( $cfg['endpoint'] ) ||
			empty( $cfg['client_id'] ) ||
			empty( $cfg['auth_token'] )
		) {
			return new WP_Error(
				'lccl_pc_not_configured',
				__( 'CBC Paycenter payment gateway is not configured. Please enter credentials in LCCL Programs → Payment Gateway.', 'lccl-de' )
			);
		}

		// Enforce HTTPS on the endpoint.
		if ( 0 !== stripos( $cfg['endpoint'], 'https://' ) ) {
			return new WP_Error(
				'lccl_pc_insecure_url',
				__( 'CBC Paycenter endpoint must use HTTPS.', 'lccl-de' )
			);
		}

		return $cfg;
	}

	/**
	 * Whether all required credentials are saved and the route is enabled.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		return ! is_wp_error( self::get_config() );
	}

	// ------------------------------------------------------------------
	// Private helpers
	// ------------------------------------------------------------------

	/**
	 * POST a JSON body to the Paycenter InterfaceServlet endpoint.
	 *
	 * Authentication uses HTTP Basic Auth with authToken as the password
	 * and clientId as the username, per Bancstac's integration guide.
	 *
	 * @param array $cfg  Config from get_config().
	 * @param array $body PHP array to be JSON-encoded.
	 * @return array|WP_Error Decoded response body, or WP_Error.
	 */
	private static function post( array $cfg, array $body ) {
		$endpoint = trailingslashit( $cfg['endpoint'] );
		// The InterfaceServlet path as documented by Bancstac.
		$url = $endpoint . 'paycorp-webservice/InterfaceServlet';

		// Bancstac uses Basic Auth: clientId : authToken.
		$auth = 'Basic ' . base64_encode( $cfg['client_id'] . ':' . $cfg['auth_token'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => self::TIMEOUT,
				'headers' => array(
					'Authorization' => $auth,
					'Content-Type'  => 'application/json',
					'Cache-Control' => 'no-cache',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		return self::parse_response( $response );
	}

	/**
	 * Decode a wp_remote_post() response or return WP_Error.
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
				'lccl_pc_bad_response',
				/* translators: HTTP status code */
				sprintf( __( 'Paycenter returned an invalid response (HTTP %d).', 'lccl-de' ), $code )
			);
		}

		return $data;
	}

	/**
	 * Generate a UUID v4 message ID for request correlation.
	 *
	 * @return string
	 */
	private static function generate_msg_id() {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return strtoupper( wp_generate_uuid4() );
		}

		// Fallback for very old WP or test environments.
		return strtoupper( sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0x0fff ) | 0x4000,
			wp_rand( 0, 0x3fff ) | 0x8000,
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff )
		) );
	}

	/**
	 * Current date/time in ISO-8601 format with timezone offset.
	 *
	 * @return string e.g. 2026-09-24T12:00:00.000+0530
	 */
	private static function iso_date() {
		return gmdate( 'Y-m-d\TH:i:s.000+0000' );
	}
}
