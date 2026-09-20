<?php
/**
 * Signed-in file viewer for programme uploads.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

/**
 * Standalone viewer tab: preview, zoom, scroll, and download.
 *
 * No WordPress page is created. A query argument on the home URL is enough.
 */
class LCCL_DE_File_Viewer {

	/**
	 * Query key for the file source, e.g. spectacles.
	 */
	const QUERY_SOURCE = 'lccl_de_file';

	/**
	 * Query key for the row ID.
	 */
	const QUERY_ID = 'lccl_de_fid';

	/**
	 * Query key that streams the file as an attachment.
	 */
	const QUERY_DOWNLOAD = 'lccl_de_fdl';

	/**
	 * Query key that streams the file inline for the viewer stage.
	 */
	const QUERY_RAW = 'lccl_de_fraw';

	/**
	 * Hook the viewer intercept.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ), 0 );
	}

	/**
	 * Viewer, raw, or download URL. Cookie auth is enough — no REST nonce.
	 *
	 * @param string $source spectacles.
	 * @param int    $id     Row ID.
	 * @param string $mode   view|raw|download.
	 * @return string
	 */
	public static function url( $source, $id, $mode = 'view' ) {
		$args = array(
			self::QUERY_SOURCE => sanitize_key( $source ),
			self::QUERY_ID     => (int) $id,
		);

		if ( 'download' === $mode ) {
			$args[ self::QUERY_DOWNLOAD ] = 1;
		} elseif ( 'raw' === $mode ) {
			$args[ self::QUERY_RAW ] = 1;
		}

		return add_query_arg( $args, home_url( '/' ) );
	}

	/**
	 * Render the viewer or stream the file when this request is ours.
	 */
	public static function maybe_render() {
		$file = self::current_file();
		if ( null === $file ) {
			return;
		}

		if ( is_wp_error( $file ) ) {
			self::send_error( $file );
		}

		if ( ! empty( $_GET[ self::QUERY_DOWNLOAD ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			self::stream( $file, 'attachment' );
		}

		if ( ! empty( $_GET[ self::QUERY_RAW ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			self::stream( $file, 'inline' );
		}

		self::render_page( $file );
	}

	/**
	 * Locate the requested file, or a WP_Error, or null if this is not a viewer request.
	 *
	 * @return array|WP_Error|null
	 */
	private static function current_file() {
		if ( empty( $_GET[ self::QUERY_SOURCE ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return null;
		}

		$source = sanitize_key( wp_unslash( $_GET[ self::QUERY_SOURCE ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id     = isset( $_GET[ self::QUERY_ID ] ) ? absint( $_GET[ self::QUERY_ID ] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( '' === $source || $id <= 0 ) {
			return new WP_Error( 'lccl_de_file', __( 'File not found.', 'lccl-de' ), array( 'status' => 404 ) );
		}

		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'lccl_de_file_auth', __( 'Sign in to view this file.', 'lccl-de' ), array( 'status' => 401 ) );
		}

		if ( ! LCCL_DE_Roles::can_view_submissions() ) {
			return new WP_Error( 'lccl_de_file_forbidden', __( 'You are not allowed to view this file.', 'lccl-de' ), array( 'status' => 403 ) );
		}

		$found = self::locate( $source, $id );
		if ( ! $found ) {
			return new WP_Error( 'lccl_de_file', __( 'File not found.', 'lccl-de' ), array( 'status' => 404 ) );
		}

		return $found;
	}

	/**
	 * Resolve a programme file record.
	 *
	 * @param string $source Source key.
	 * @param int    $id     Row ID.
	 * @return array|null
	 */
	private static function locate( $source, $id ) {
		if ( 'spectacles' === $source ) {
			return LCCL_DE_Spectacles_Dashboard::viewer_file( $id );
		}

		return null;
	}

	/**
	 * Stream a file and exit.
	 *
	 * @param array  $file        Viewer file.
	 * @param string $disposition inline|attachment.
	 */
	private static function stream( array $file, $disposition ) {
		nocache_headers();
		header( 'Content-Type: ' . $file['mime'] );
		header( 'Content-Disposition: ' . $disposition . '; filename="' . $file['filename'] . '"' );
		header( 'Content-Length: ' . (string) filesize( $file['path'] ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: private, no-store' );
		readfile( $file['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	/**
	 * Print the reusable viewer chrome.
	 *
	 * @param array $file Viewer file.
	 */
	private static function render_page( array $file ) {
		nocache_headers();
		status_header( 200 );

		$file['raw_url']      = self::url( $file['source'], $file['id'], 'raw' );
		$file['download_url'] = self::url( $file['source'], $file['id'], 'download' );

		include LCCL_DE_PATH . 'templates/file-viewer.php';
		exit;
	}

	/**
	 * Print a small error page and exit.
	 *
	 * @param WP_Error $error Error.
	 */
	private static function send_error( WP_Error $error ) {
		$status = (int) $error->get_error_data( 'status' );
		if ( $status < 400 ) {
			$status = 403;
		}

		nocache_headers();
		status_header( $status );

		$sign_in = '';
		if ( 401 === $status ) {
			$sign_in = LCCL_DE_Roles::spectacles_dashboard_url();
		}

		$file = array(
			'error'        => $error->get_error_message(),
			'sign_in_url'  => $sign_in,
			'kind'         => '',
			'name'         => '',
			'raw_url'      => '',
			'download_url' => '',
		);

		include LCCL_DE_PATH . 'templates/file-viewer.php';
		exit;
	}
}
