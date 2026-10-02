<?php
/**
 * Testimonials management backend (CRUD & AJAX).
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles database operations and AJAX requests for Testimonials.
 */
class LCCL_DE_Testimonials {

	/**
	 * Setup hooks.
	 */
	public static function init() {
		add_action( 'wp_ajax_lccl_de_testimonial_save', array( __CLASS__, 'ajax_save' ) );
		add_action( 'wp_ajax_lccl_de_testimonial_delete', array( __CLASS__, 'ajax_delete' ) );
		add_action( 'wp_ajax_lccl_de_testimonial_toggle', array( __CLASS__, 'ajax_toggle' ) );
		add_action( 'wp_ajax_lccl_de_testimonial_reorder', array( __CLASS__, 'ajax_reorder' ) );
	}

	/**
	 * Get all testimonials, ordered for admin (Active by sort_order, then Inactive by ID desc).
	 *
	 * @return array
	 */
	public static function get_all() {
		global $wpdb;
		$table = LCCL_DE_Schema::testimonials_table();

		if ( ! LCCL_DE_Schema::table_exists() ) { // Fallback, we'll assume it exists if another table exists, but let's query directly safely
			$table_check = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
			if ( ! $table_check ) {
				return array();
			}
		}

		$sql = "SELECT * FROM {$table} ORDER BY is_active DESC, sort_order ASC, id DESC";
		$results = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $results ) ? $results : array();
	}

	/**
	 * Get only active testimonials.
	 *
	 * @return array
	 */
	public static function get_active() {
		global $wpdb;
		$table = LCCL_DE_Schema::testimonials_table();
		$sql = "SELECT * FROM {$table} WHERE is_active = 1 ORDER BY sort_order ASC, id DESC";
		$results = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $results ) ? $results : array();
	}

	/**
	 * AJAX: Save or Update a testimonial.
	 */
	public static function ajax_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'lccl-de' ) ), 403 );
		}

		check_ajax_referer( 'lccl_de_testimonial_save', 'nonce' );

		global $wpdb;
		$table = LCCL_DE_Schema::testimonials_table();

		$id        = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$name      = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$role      = isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( $_POST['role'] ) ) : '';
		$quote     = isset( $_POST['quote'] ) ? sanitize_textarea_field( wp_unslash( $_POST['quote'] ) ) : '';
		$date      = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		$photo_id  = isset( $_POST['photo_id'] ) ? (int) $_POST['photo_id'] : 0;
		$photo_url = isset( $_POST['photo_url'] ) ? esc_url_raw( wp_unslash( $_POST['photo_url'] ) ) : '';

		if ( empty( $name ) || empty( $quote ) ) {
			wp_send_json_error( array( 'message' => __( 'Name and Quote are required.', 'lccl-de' ) ) );
		}

		$data = array(
			'name'       => $name,
			'role'       => $role,
			'quote'      => $quote,
			'date'       => $date,
			'photo_url'  => $photo_url,
			'photo_id'   => $photo_id,
		);

		$format = array( '%s', '%s', '%s', '%s', '%s', '%d' );

		if ( $id > 0 ) {
			// Updating existing
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
			if ( ! $existing ) {
				wp_send_json_error( array( 'message' => __( 'Testimonial not found.', 'lccl-de' ) ) );
			}

			// If photo changed, delete old one
			if ( ! empty( $existing['photo_id'] ) && (int) $existing['photo_id'] !== $photo_id ) {
				wp_delete_attachment( $existing['photo_id'], true );
			}

			$data['updated_at'] = current_time( 'mysql' );
			$data['updated_by'] = get_current_user_id();
			$format[] = '%s';
			$format[] = '%d';

			$result = $wpdb->update( $table, $data, array( 'id' => $id ), $format, array( '%d' ) );
			
			if ( false === $result ) {
				wp_send_json_error( array( 'message' => __( 'Failed to update testimonial.', 'lccl-de' ) ) );
			}
			
			$testimonial = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
			
		} else {
			// Inserting new
			$data['is_active']  = 0;
			$data['sort_order'] = 0;
			$data['created_at'] = current_time( 'mysql' );
			$format[] = '%d';
			$format[] = '%d';
			$format[] = '%s';

			$result = $wpdb->insert( $table, $data, $format );
			
			if ( false === $result ) {
				wp_send_json_error( array( 'message' => __( 'Failed to save testimonial.', 'lccl-de' ) ) );
			}
			
			$id = $wpdb->insert_id;
			$testimonial = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		}

		ob_start();
		$t = $testimonial;
		include LCCL_DE_PATH . 'templates/admin-testimonial-card.php';
		$html = ob_get_clean();

		wp_send_json_success( array(
			'message'     => __( 'Testimonial saved.', 'lccl-de' ),
			'testimonial' => $testimonial,
			'html'        => $html,
		) );
	}

	/**
	 * AJAX: Delete a testimonial.
	 */
	public static function ajax_delete() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'lccl-de' ) ), 403 );
		}

		check_ajax_referer( 'lccl_de_testimonial_delete', 'nonce' );

		global $wpdb;
		$table = LCCL_DE_Schema::testimonials_table();

		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		if ( $id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid ID.', 'lccl-de' ) ) );
		}

		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		if ( ! $existing ) {
			wp_send_json_error( array( 'message' => __( 'Testimonial not found.', 'lccl-de' ) ) );
		}

		// Delete photo attachment if exists
		if ( ! empty( $existing['photo_id'] ) ) {
			wp_delete_attachment( $existing['photo_id'], true );
		}

		$result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

		if ( false === $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to delete testimonial.', 'lccl-de' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Testimonial deleted.', 'lccl-de' ) ) );
	}

	/**
	 * AJAX: Toggle active status of a testimonial.
	 */
	public static function ajax_toggle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'lccl-de' ) ), 403 );
		}

		check_ajax_referer( 'lccl_de_testimonial_toggle', 'nonce' );

		global $wpdb;
		$table = LCCL_DE_Schema::testimonials_table();

		$id        = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$is_active = isset( $_POST['is_active'] ) ? (int) $_POST['is_active'] : 0;
		
		if ( $id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid ID.', 'lccl-de' ) ) );
		}

		// Update to active/inactive. If setting to inactive, reset sort order.
		$data = array(
			'is_active'  => $is_active,
			'updated_at' => current_time( 'mysql' ),
			'updated_by' => get_current_user_id(),
		);
		$format = array( '%d', '%s', '%d' );

		if ( 0 === $is_active ) {
			$data['sort_order'] = 0;
			$format[] = '%d';
		}

		$result = $wpdb->update( $table, $data, array( 'id' => $id ), $format, array( '%d' ) );

		if ( false === $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to toggle status.', 'lccl-de' ) ) );
		}

		wp_send_json_success( array(
			'message'   => __( 'Status updated.', 'lccl-de' ),
			'is_active' => $is_active,
		) );
	}

	/**
	 * AJAX: Reorder active testimonials.
	 */
	public static function ajax_reorder() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'lccl-de' ) ), 403 );
		}

		check_ajax_referer( 'lccl_de_testimonial_reorder', 'nonce' );

		$ids = isset( $_POST['ids'] ) ? $_POST['ids'] : array();
		if ( ! is_array( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'lccl-de' ) ) );
		}

		global $wpdb;
		$table = LCCL_DE_Schema::testimonials_table();

		foreach ( $ids as $index => $id ) {
			$id = (int) $id;
			if ( $id > 0 ) {
				$wpdb->update(
					$table,
					array( 'sort_order' => (int) $index ),
					array( 'id' => $id ),
					array( '%d' ),
					array( '%d' )
				);
			}
		}

		wp_send_json_success( array( 'message' => __( 'Order updated.', 'lccl-de' ) ) );
	}
}
