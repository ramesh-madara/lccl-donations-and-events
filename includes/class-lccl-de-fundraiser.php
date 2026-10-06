<?php
/**
 * Fundraiser system — Events, Table Types, Seats, Bookings.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

class LCCL_DE_Fundraiser {

	public static function init() {
		add_action( 'wp_ajax_lccl_de_fundraiser_create_event',       array( __CLASS__, 'ajax_create_event' ) );
		add_action( 'wp_ajax_lccl_de_fundraiser_update_event',       array( __CLASS__, 'ajax_update_event' ) );
		add_action( 'wp_ajax_lccl_de_fundraiser_delete_event',       array( __CLASS__, 'ajax_delete_event' ) );
		add_action( 'wp_ajax_lccl_de_fundraiser_toggle_public',      array( __CLASS__, 'ajax_toggle_public' ) );
		add_action( 'wp_ajax_lccl_de_fundraiser_add_table_type',     array( __CLASS__, 'ajax_add_table_type' ) );
		add_action( 'wp_ajax_lccl_de_fundraiser_delete_table_type',  array( __CLASS__, 'ajax_delete_table_type' ) );
		add_action( 'wp_ajax_lccl_de_fundraiser_generate_inventory', array( __CLASS__, 'ajax_generate_inventory' ) );
		add_action( 'wp_ajax_lccl_de_fundraiser_delete_inventory',   array( __CLASS__, 'ajax_delete_inventory' ) );
	}

	/* -----------------------------------------------------------------------
	 * Table name helpers
	 * --------------------------------------------------------------------- */

	public static function events_table() { global $wpdb; return $wpdb->prefix . 'lccl_de_fr_events'; }
	public static function table_types_table() { global $wpdb; return $wpdb->prefix . 'lccl_de_fr_table_types'; }
	public static function tables_table() { global $wpdb; return $wpdb->prefix . 'lccl_de_fr_tables'; }
	public static function bookings_table() { global $wpdb; return $wpdb->prefix . 'lccl_de_fr_bookings'; }

	/* -----------------------------------------------------------------------
	 * Schema installer — called by LCCL_DE_Schema::install
	 * --------------------------------------------------------------------- */

	public static function install( $charset ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$events = self::events_table();
		dbDelta( "CREATE TABLE {$events} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(200) NOT NULL,
			description text,
			event_date date DEFAULT NULL,
			event_time time DEFAULT NULL,
			venue varchar(300) DEFAULT NULL,
			ticket_price decimal(12,2) NOT NULL DEFAULT 0.00,
			currency varchar(3) NOT NULL DEFAULT 'LKR',
			status varchar(20) NOT NULL DEFAULT 'draft',
			public_booking tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY public_booking (public_booking)
		) {$charset};" );

		$types = self::table_types_table();
		dbDelta( "CREATE TABLE {$types} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_id bigint(20) unsigned NOT NULL,
			name varchar(200) NOT NULL,
			capacity int(11) NOT NULL DEFAULT 10,
			price decimal(12,2) NOT NULL DEFAULT 0.00,
			PRIMARY KEY  (id),
			KEY event_id (event_id)
		) {$charset};" );

		$tables = self::tables_table();
		dbDelta( "CREATE TABLE {$tables} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_id bigint(20) unsigned NOT NULL,
			type_id bigint(20) unsigned NOT NULL,
			table_label varchar(20) NOT NULL,
			sort_order int(11) NOT NULL DEFAULT 0,
			is_booked tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY event_id (event_id),
			KEY type_id (type_id),
			KEY is_booked (is_booked)
		) {$charset};" );

		$bookings = self::bookings_table();
		dbDelta( "CREATE TABLE {$bookings} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_ref varchar(64) NOT NULL,
			event_id bigint(20) unsigned NOT NULL,
			booking_type varchar(20) NOT NULL DEFAULT 'individual',
			attendee_name varchar(200) NOT NULL,
			attendee_email varchar(191) NOT NULL,
			attendee_phone varchar(30) NOT NULL DEFAULT '',
			qty int(11) NOT NULL DEFAULT 1,
			seat_numbers text,
			table_ids text,
			amount_lkr decimal(12,2) NOT NULL DEFAULT 0.00,
			currency varchar(3) NOT NULL DEFAULT 'LKR',
			status varchar(32) NOT NULL DEFAULT 'pending',
			session_id varchar(128) DEFAULT NULL,
			gateway_receipt varchar(128) DEFAULT NULL,
			gateway_response longtext,
			ip_address varchar(45) DEFAULT NULL,
			created_at datetime NOT NULL,
			paid_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY order_ref (order_ref),
			KEY event_id (event_id),
			KEY status (status),
			KEY attendee_email (attendee_email),
			KEY created_at (created_at)
		) {$charset};" );
	}

	/* -----------------------------------------------------------------------
	 * Read helpers
	 * --------------------------------------------------------------------- */

	public static function get_all_events() {
		global $wpdb;
		$t = self::events_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( "SELECT * FROM `{$t}` ORDER BY event_date DESC, id DESC", ARRAY_A );
	}

	public static function get_event( $id ) {
		global $wpdb;
		$t = self::events_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE id = %d", $id ), ARRAY_A );
	}

	public static function get_active_public_event() {
		global $wpdb;
		$t = self::events_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE public_booking = %d AND status != 'archived' ORDER BY event_date ASC LIMIT 1", 1 ), ARRAY_A );
	}

	public static function get_table_types( $event_id ) {
		global $wpdb;
		$t = self::table_types_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE event_id = %d ORDER BY price ASC", $event_id ), ARRAY_A );
	}

	public static function get_tables( $event_id ) {
		global $wpdb;
		$t  = self::tables_table();
		$tt = self::table_types_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT ta.*, ty.name AS type_name, ty.capacity, ty.price FROM `{$t}` ta JOIN `{$tt}` ty ON ta.type_id = ty.id WHERE ta.event_id = %d ORDER BY ta.type_id ASC, ta.sort_order ASC", $event_id ), ARRAY_A );
	}

	public static function get_available_tables_by_type( $event_id, $type_id ) {
		global $wpdb;
		$t = self::tables_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE event_id = %d AND type_id = %d AND is_booked = 0 ORDER BY sort_order ASC", $event_id, $type_id ), ARRAY_A );
	}

	public static function get_available_seat_numbers( $event_id ) {
		// For individual seats we use seat numbers 1..total_capacity of all tables
		// Simpler approach: count all individual seats from existing bookings
		global $wpdb;
		$event = self::get_event( $event_id );
		if ( ! $event ) return array();
		// Get all booked seat numbers
		$bk = self::bookings_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT seat_numbers FROM `{$bk}` WHERE event_id=%d AND booking_type='individual' AND status='paid'", $event_id ), ARRAY_A );
		$booked = array();
		foreach ( $rows as $row ) {
			if ( ! empty( $row['seat_numbers'] ) ) {
				foreach ( explode( ',', $row['seat_numbers'] ) as $n ) {
					$booked[] = (int) trim( $n );
				}
			}
		}
		// Total seats = sum of all table capacities
		$tt = self::table_types_table();
		$tb = self::tables_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total_capacity = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(ty.capacity),0) FROM `{$tb}` ta JOIN `{$tt}` ty ON ta.type_id=ty.id WHERE ta.event_id=%d", $event_id ) );
		$all = range( 1, max( $total_capacity, 120 ) );
		return array_values( array_diff( $all, $booked ) );
	}

	public static function get_bookings( $event_id = null ) {
		global $wpdb;
		$b  = self::bookings_table();
		$ev = self::events_table();
		if ( $event_id ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return (array) $wpdb->get_results( $wpdb->prepare( "SELECT bk.*, ev.name AS event_name FROM `{$b}` bk LEFT JOIN `{$ev}` ev ON bk.event_id = ev.id WHERE bk.event_id = %d ORDER BY bk.created_at DESC", $event_id ), ARRAY_A );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( "SELECT bk.*, ev.name AS event_name FROM `{$b}` bk LEFT JOIN `{$ev}` ev ON bk.event_id = ev.id ORDER BY bk.created_at DESC", ARRAY_A );
	}

	public static function get_stats() {
		global $wpdb;
		$ev = self::events_table();
		$bk = self::bookings_table();
		return array(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'total_events'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$ev}`" ),
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'public_events'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$ev}` WHERE public_booking=1" ),
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'total_bookings' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$bk}` WHERE status='paid'" ),
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'total_revenue'  => (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount_lkr),0) FROM `{$bk}` WHERE status='paid'" ),
		);
	}

	/* -----------------------------------------------------------------------
	 * AJAX — Events
	 * --------------------------------------------------------------------- */

	private static function nonce_check() {
		check_ajax_referer( 'lccl_de_fundraiser_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Unauthorized.' ) ); }
	}

	public static function ajax_create_event() {
		self::nonce_check();
		global $wpdb;
		$raw = wp_unslash( $_POST );
		$wpdb->insert( self::events_table(), array(
			'name'           => sanitize_text_field( $raw['name'] ?? '' ),
			'description'    => sanitize_textarea_field( $raw['description'] ?? '' ),
			'event_date'     => sanitize_text_field( $raw['event_date'] ?? '' ) ?: null,
			'event_time'     => sanitize_text_field( $raw['event_time'] ?? '' ) ?: null,
			'venue'          => sanitize_text_field( $raw['venue'] ?? '' ),
			'ticket_price'   => (float) ( $raw['ticket_price'] ?? 6500 ),
			'status'         => 'upcoming',
			'public_booking' => 0,
			'created_at'     => current_time( 'mysql' ),
		) );
		wp_send_json_success( array( 'id' => $wpdb->insert_id, 'message' => 'Event created.' ) );
	}

	public static function ajax_update_event() {
		self::nonce_check();
		global $wpdb;
		$raw = wp_unslash( $_POST );
		$id  = (int) ( $raw['event_id'] ?? 0 );
		$wpdb->update( self::events_table(), array(
			'name'         => sanitize_text_field( $raw['name'] ?? '' ),
			'description'  => sanitize_textarea_field( $raw['description'] ?? '' ),
			'event_date'   => sanitize_text_field( $raw['event_date'] ?? '' ) ?: null,
			'event_time'   => sanitize_text_field( $raw['event_time'] ?? '' ) ?: null,
			'venue'        => sanitize_text_field( $raw['venue'] ?? '' ),
			'ticket_price' => (float) ( $raw['ticket_price'] ?? 0 ),
			'status'       => sanitize_key( $raw['status'] ?? 'upcoming' ),
			'updated_at'   => current_time( 'mysql' ),
		), array( 'id' => $id ) );
		wp_send_json_success( array( 'message' => 'Event updated.' ) );
	}

	public static function ajax_delete_event() {
		self::nonce_check();
		global $wpdb;
		$id = (int) ( $_POST['event_id'] ?? 0 );
		$wpdb->delete( self::events_table(),      array( 'id'       => $id ) );
		$wpdb->delete( self::table_types_table(), array( 'event_id' => $id ) );
		$wpdb->delete( self::tables_table(),      array( 'event_id' => $id ) );
		wp_send_json_success();
	}

	public static function ajax_toggle_public() {
		self::nonce_check();
		global $wpdb;
		$id  = (int) ( $_POST['event_id'] ?? 0 );
		$t   = self::events_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$cur = (int) $wpdb->get_var( $wpdb->prepare( "SELECT public_booking FROM `{$t}` WHERE id=%d", $id ) );
		$new = $cur ? 0 : 1;
		$wpdb->update( self::events_table(), array( 'public_booking' => $new ), array( 'id' => $id ) );
		wp_send_json_success( array( 'public_booking' => $new ) );
	}

	/* -----------------------------------------------------------------------
	 * AJAX — Table Types
	 * --------------------------------------------------------------------- */

	public static function ajax_add_table_type() {
		self::nonce_check();
		global $wpdb;
		$raw = wp_unslash( $_POST );
		$wpdb->insert( self::table_types_table(), array(
			'event_id' => (int) ( $raw['event_id'] ?? 0 ),
			'name'     => sanitize_text_field( $raw['name'] ?? '' ),
			'capacity' => (int) ( $raw['capacity'] ?? 10 ),
			'price'    => (float) ( $raw['price'] ?? 0 ),
		) );
		$type_id = $wpdb->insert_id;
		$new_type = array( 'id' => $type_id, 'name' => sanitize_text_field( $raw['name'] ?? '' ), 'capacity' => (int) ( $raw['capacity'] ?? 10 ), 'price' => (float) ( $raw['price'] ?? 0 ) );
		wp_send_json_success( array( 'id' => $type_id, 'type' => $new_type ) );
	}

	public static function ajax_delete_table_type() {
		self::nonce_check();
		global $wpdb;
		$type_id = (int) ( $_POST['type_id'] ?? 0 );
		$wpdb->delete( self::table_types_table(), array( 'id'      => $type_id ) );
		$wpdb->delete( self::tables_table(),      array( 'type_id' => $type_id ) );
		wp_send_json_success();
	}

	/* -----------------------------------------------------------------------
	 * AJAX — Table Inventory
	 * --------------------------------------------------------------------- */

	public static function ajax_generate_inventory() {
		self::nonce_check();
		global $wpdb;
		$raw      = wp_unslash( $_POST );
		$event_id = (int) ( $raw['event_id'] ?? 0 );
		$type_id  = (int) ( $raw['type_id'] ?? 0 );
		$count    = max( 1, min( 200, (int) ( $raw['count'] ?? 0 ) ) );
		$prefix   = strtoupper( sanitize_text_field( $raw['prefix'] ?? 'T' ) );
		$t        = self::tables_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t}` WHERE event_id=%d AND type_id=%d", $event_id, $type_id ) );
		for ( $i = 1; $i <= $count; $i++ ) {
			$num   = $existing + $i;
			$label = $prefix . str_pad( $num, 2, '0', STR_PAD_LEFT );
			$wpdb->insert( self::tables_table(), array(
				'event_id'    => $event_id,
				'type_id'     => $type_id,
				'table_label' => $label,
				'sort_order'  => $num,
				'is_booked'   => 0,
			) );
		}
		wp_send_json_success( array( 'generated' => $count ) );
	}

	public static function ajax_delete_inventory() {
		self::nonce_check();
		global $wpdb;
		$event_id = (int) ( $_POST['event_id'] ?? 0 );
		$type_id  = (int) ( $_POST['type_id'] ?? 0 );
		$wpdb->delete( self::tables_table(), array( 'event_id' => $event_id, 'type_id' => $type_id ) );
		wp_send_json_success();
	}

	/* -----------------------------------------------------------------------
	 * Sample data installer
	 * --------------------------------------------------------------------- */

	public static function install_sample_event() {
		global $wpdb;
		$events = self::events_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$events}`" ) > 0 ) {
			return; // Already have data
		}

		$wpdb->insert( $events, array(
			'name'           => 'LCCL Annual Fundraiser Gala 2026',
			'description'    => 'The Lions Club of Colombo Leads Annual Fundraiser Gala — an elegant evening of dining, entertainment, and community spirit. All proceeds support our community development programmes.',
			'event_date'     => '2026-11-15',
			'event_time'     => '18:30:00',
			'venue'          => 'Grand Ballroom, Hotel Galadari, Colombo',
			'ticket_price'   => 6500.00,
			'currency'       => 'LKR',
			'status'         => 'upcoming',
			'public_booking' => 1,
			'created_at'     => current_time( 'mysql' ),
		) );
		$event_id = $wpdb->insert_id;

		$types_data = array(
			array( 'name' => 'Standard Table (10-Seater)', 'capacity' => 10, 'price' => 65000.00, 'prefix' => 'T', 'count' => 15 ),
			array( 'name' => 'Premium Table (12-Seater)', 'capacity' => 12, 'price' => 90000.00, 'prefix' => 'P', 'count' => 8 ),
			array( 'name' => 'VIP Table (8-Seater)',      'capacity' => 8,  'price' => 120000.00, 'prefix' => 'V', 'count' => 5 ),
		);

		$types = self::table_types_table();
		$tables = self::tables_table();
		foreach ( $types_data as $def ) {
			$wpdb->insert( $types, array( 'event_id' => $event_id, 'name' => $def['name'], 'capacity' => $def['capacity'], 'price' => $def['price'] ) );
			$type_id = $wpdb->insert_id;
			for ( $i = 1; $i <= $def['count']; $i++ ) {
				$wpdb->insert( $tables, array(
					'event_id'    => $event_id,
					'type_id'     => $type_id,
					'table_label' => $def['prefix'] . str_pad( $i, 2, '0', STR_PAD_LEFT ),
					'sort_order'  => $i,
					'is_booked'   => 0,
				) );
			}
		}
	}
}
