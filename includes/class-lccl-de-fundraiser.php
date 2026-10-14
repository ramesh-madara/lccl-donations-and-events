<?php
/**
 * Fundraiser system - Events, Table Types, Seats, Bookings.
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

		// Seat/Table Dual-Pool Management AJAX hooks
		add_action( 'wp_ajax_lccl_de_fr_move_table_pool',            array( __CLASS__, 'ajax_move_table_pool' ) );
		add_action( 'wp_ajax_lccl_de_fundraiser_set_table_mode',     array( __CLASS__, 'ajax_move_table_pool' ) );
		add_action( 'wp_ajax_lccl_de_fr_toggle_table_block',         array( __CLASS__, 'ajax_toggle_table_block' ) );
		add_action( 'wp_ajax_lccl_de_fundraiser_toggle_table_block', array( __CLASS__, 'ajax_toggle_table_block' ) );
		add_action( 'wp_ajax_lccl_de_fr_toggle_seat_block',          array( __CLASS__, 'ajax_toggle_seat_block' ) );
		add_action( 'wp_ajax_lccl_de_fundraiser_toggle_seat_block',  array( __CLASS__, 'ajax_toggle_seat_block' ) );
		add_action( 'wp_ajax_lccl_de_fr_clear_seat_blocks',         array( __CLASS__, 'ajax_clear_seat_blocks' ) );

		// Ensure public booking page exists.
		if ( class_exists( 'LCCL_DE_Roles' ) ) {
			LCCL_DE_Roles::ensure_fundraiser_page();
		}
	}

	/* -----------------------------------------------------------------------
	 * Table name helpers
	 * --------------------------------------------------------------------- */

	public static function events_table() { global $wpdb; return $wpdb->prefix . 'lccl_de_fr_events'; }
	public static function table_types_table() { global $wpdb; return $wpdb->prefix . 'lccl_de_fr_table_types'; }
	public static function tables_table() { global $wpdb; return $wpdb->prefix . 'lccl_de_fr_tables'; }
	public static function bookings_table() { global $wpdb; return $wpdb->prefix . 'lccl_de_fr_bookings'; }
	public static function seat_blocks_table() { global $wpdb; return $wpdb->prefix . 'lccl_de_fr_seat_blocks'; }

	/* -----------------------------------------------------------------------
	 * Schema installer - called by LCCL_DE_Schema::install
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
			booking_mode varchar(10) NOT NULL DEFAULT 'seat',
			is_blocked tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY event_id (event_id),
			KEY type_id (type_id),
			KEY is_booked (is_booked),
			KEY booking_mode (booking_mode),
			KEY is_blocked (is_blocked)
		) {$charset};" );

		$seat_blocks = self::seat_blocks_table();
		dbDelta( "CREATE TABLE {$seat_blocks} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_id bigint(20) unsigned NOT NULL,
			seat_number int(11) NOT NULL,
			blocked_by bigint(20) unsigned DEFAULT NULL,
			blocked_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_seat (event_id, seat_number),
			KEY event_id (event_id)
		) {$charset};" );

		// Ensure columns exist on existing installs where dbDelta does not add columns
		global $wpdb;
		$col_mode = $wpdb->get_var( $wpdb->prepare(
			"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'booking_mode'",
			$tables
		) );
		if ( ! $col_mode ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE `{$tables}` ADD COLUMN `booking_mode` varchar(10) NOT NULL DEFAULT 'seat' AFTER `is_booked`" );
		}
		$col_block = $wpdb->get_var( $wpdb->prepare(
			"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'is_blocked'",
			$tables
		) );
		if ( ! $col_block ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE `{$tables}` ADD COLUMN `is_blocked` tinyint(1) NOT NULL DEFAULT 0 AFTER `booking_mode`" );
		}

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
		return (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM `{$t}`
			 WHERE event_id = %d AND type_id = %d
			   AND booking_mode = 'table'
			   AND is_booked = 0
			   AND is_blocked = 0
			 ORDER BY sort_order ASC",
			$event_id,
			$type_id
		), ARRAY_A );
	}

	public static function get_available_seat_numbers( $event_id ) {
		global $wpdb;
		$tb = self::tables_table();
		$tt = self::table_types_table();
		$bk = self::bookings_table();
		$sb = self::seat_blocks_table();

		// 1. Total capacity from unblocked seat-pool tables
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total_capacity = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(ty.capacity), 0)
			 FROM `{$tb}` ta
			 JOIN `{$tt}` ty ON ta.type_id = ty.id
			 WHERE ta.event_id = %d
			   AND (ta.booking_mode = 'seat' OR ta.booking_mode IS NULL OR ta.booking_mode = '')
			   AND ta.is_blocked = 0",
			$event_id
		) );
		if ( $total_capacity < 1 ) {
			return array();
		}

		// 2. Already paid/booked seat numbers
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT seat_numbers FROM `{$bk}` WHERE event_id = %d AND booking_type = 'individual' AND status = 'paid'",
			$event_id
		), ARRAY_A );
		$booked = array();
		foreach ( $rows as $row ) {
			if ( ! empty( $row['seat_numbers'] ) ) {
				foreach ( explode( ',', $row['seat_numbers'] ) as $n ) {
					$num = (int) trim( $n );
					if ( $num > 0 ) {
						$booked[] = $num;
					}
				}
			}
		}

		// 3. Admin-blocked seat numbers
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$blocked_rows = (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT seat_number FROM `{$sb}` WHERE event_id = %d",
			$event_id
		), ARRAY_A );
		$blocked_seats = array_map( function( $r ) { return (int) $r['seat_number']; }, $blocked_rows );

		// 4. Sequence 1..total_capacity excluding booked & blocked (sorted ascending for deterministic auto-allocation)
		$all         = range( 1, $total_capacity );
		$unavailable = array_unique( array_merge( $booked, $blocked_seats ) );
		$available   = array_values( array_diff( $all, $unavailable ) );
		sort( $available, SORT_NUMERIC );
		return $available;
	}

	public static function get_blocked_seat_numbers( $event_id ) {
		global $wpdb;
		$sb = self::seat_blocks_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT seat_number FROM `{$sb}` WHERE event_id = %d ORDER BY seat_number ASC",
			$event_id
		), ARRAY_A );
		return array_map( function( $r ) { return (int) $r['seat_number']; }, $rows );
	}

	public static function get_booked_seat_numbers( $event_id ) {
		global $wpdb;
		$bk = self::bookings_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT seat_numbers FROM `{$bk}` WHERE event_id = %d AND booking_type = 'individual' AND status = 'paid'",
			$event_id
		), ARRAY_A );
		$booked = array();
		foreach ( $rows as $row ) {
			if ( ! empty( $row['seat_numbers'] ) ) {
				foreach ( explode( ',', $row['seat_numbers'] ) as $n ) {
					$num = (int) trim( $n );
					if ( $num > 0 ) {
						$booked[] = $num;
					}
				}
			}
		}
		$booked = array_values( array_unique( $booked ) );
		sort( $booked, SORT_NUMERIC );
		return $booked;
	}

	public static function get_pool_stats( $event_id ) {
		global $wpdb;
		$ta = self::tables_table();
		$tt = self::table_types_table();
		$bk = self::bookings_table();
		$sb = self::seat_blocks_table();

		// Table pool
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$table_pool_free = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM `{$ta}` WHERE event_id = %d AND booking_mode = 'table' AND is_booked = 0 AND is_blocked = 0",
			$event_id
		) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$table_pool_booked = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM `{$ta}` WHERE event_id = %d AND booking_mode = 'table' AND is_booked = 1",
			$event_id
		) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$table_pool_blocked = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM `{$ta}` WHERE event_id = %d AND booking_mode = 'table' AND is_blocked = 1 AND is_booked = 0",
			$event_id
		) );

		// Seat pool total capacity (unblocked seat tables)
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$seat_pool_total = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(ty.capacity), 0)
			 FROM `{$ta}` ta JOIN `{$tt}` ty ON ta.type_id = ty.id
			 WHERE ta.event_id = %d
			   AND (ta.booking_mode = 'seat' OR ta.booking_mode IS NULL OR ta.booking_mode = '')
			   AND ta.is_blocked = 0",
			$event_id
		) );

		// Booked seats from paid individual bookings
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$seat_pool_booked = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(qty), 0) FROM `{$bk}`
			 WHERE event_id = %d AND booking_type = 'individual' AND status = 'paid'",
			$event_id
		) );

		// Individually blocked seat numbers
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$seat_pool_blocked = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM `{$sb}` WHERE event_id = %d",
			$event_id
		) );

		$seat_pool_free = max( 0, $seat_pool_total - $seat_pool_booked - $seat_pool_blocked );

		return array(
			'table_pool_free'    => $table_pool_free,
			'table_pool_booked'  => $table_pool_booked,
			'table_pool_blocked' => $table_pool_blocked,
			'seat_pool_free'     => $seat_pool_free,
			'seat_pool_booked'   => $seat_pool_booked,
			'seat_pool_blocked'  => $seat_pool_blocked,
			// Aggregate totals
			'tables_total'       => $table_pool_free + $table_pool_booked + $table_pool_blocked,
			'tables_free'        => $table_pool_free,
			'tables_booked'      => $table_pool_booked,
			'seats_total'        => $seat_pool_total,
			'seats_free'         => $seat_pool_free,
			'seats_booked'       => $seat_pool_booked,
		);
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
	 * AJAX - Events
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
	 * AJAX - Table Types
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
	 * AJAX - Table Inventory
	 * --------------------------------------------------------------------- */

	public static function ajax_generate_inventory() {
		self::nonce_check();
		global $wpdb;
		$raw      = wp_unslash( $_POST );
		$event_id = (int) ( $raw['event_id'] ?? 0 );
		$type_id  = (int) ( $raw['type_id'] ?? 0 );
		$count    = max( 1, min( 200, (int) ( $raw['count'] ?? 0 ) ) );
		$prefix   = strtoupper( sanitize_text_field( $raw['prefix'] ?? 'T' ) );
		$mode     = in_array( $raw['mode'] ?? 'seat', array( 'seat', 'table' ), true ) ? $raw['mode'] : 'seat';

		$tt = self::table_types_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$type_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$tt}` WHERE id = %d AND event_id = %d", $type_id, $event_id ), ARRAY_A );
		if ( ! $type_row ) {
			wp_send_json_error( array( 'message' => 'Table tier not found.' ) );
		}

		$t        = self::tables_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t}` WHERE event_id=%d AND type_id=%d", $event_id, $type_id ) );

		$new_tables = array();
		for ( $i = 1; $i <= $count; $i++ ) {
			$num   = $existing + $i;
			$label = $prefix . str_pad( $num, 2, '0', STR_PAD_LEFT );
			$wpdb->insert( self::tables_table(), array(
				'event_id'     => $event_id,
				'type_id'      => $type_id,
				'table_label'  => $label,
				'sort_order'   => $num,
				'is_booked'    => 0,
				'booking_mode' => $mode,
				'is_blocked'   => 0,
			) );
			$new_tables[] = array(
				'id'           => (int) $wpdb->insert_id,
				'event_id'     => $event_id,
				'type_id'      => $type_id,
				'table_label'  => $label,
				'sort_order'   => $num,
				'is_booked'    => 0,
				'booking_mode' => $mode,
				'is_blocked'   => 0,
				'type_name'    => $type_row['name'],
				'capacity'     => (int) $type_row['capacity'],
				'price'        => (float) $type_row['price'],
			);
		}

		wp_send_json_success( array(
			'count'     => $count,
			'generated' => $count,
			'tables'    => $new_tables,
		) );
	}

	public static function ajax_delete_inventory() {
		self::nonce_check();
		global $wpdb;
		$event_id = (int) ( $_POST['event_id'] ?? 0 );
		$type_id  = (int) ( $_POST['type_id'] ?? 0 );
		$wpdb->delete( self::tables_table(), array( 'event_id' => $event_id, 'type_id' => $type_id ) );
		wp_send_json_success();
	}

	public static function ajax_move_table_pool() {
		self::nonce_check();
		global $wpdb;
		$table_id = (int) ( $_POST['table_id'] ?? 0 );
		$mode     = in_array( $_POST['mode'] ?? '', array( 'seat', 'table' ), true ) ? sanitize_key( $_POST['mode'] ) : '';

		if ( ! $table_id || ! $mode ) {
			wp_send_json_error( array( 'message' => 'Invalid request parameters.' ) );
		}

		$tbl = self::tables_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT is_booked, is_blocked FROM `{$tbl}` WHERE id = %d", $table_id ), ARRAY_A );
		if ( ! $row ) {
			wp_send_json_error( array( 'message' => 'Table not found.' ) );
		}
		if ( (int) $row['is_booked'] ) {
			wp_send_json_error( array( 'message' => 'Cannot move a booked table.' ) );
		}
		if ( (int) $row['is_blocked'] ) {
			wp_send_json_error( array( 'message' => 'Unblock the table before moving it to a different pool.' ) );
		}

		$wpdb->update( self::tables_table(), array( 'booking_mode' => $mode ), array( 'id' => $table_id ) );
		wp_send_json_success( array( 'booking_mode' => $mode ) );
	}

	public static function ajax_toggle_table_block() {
		self::nonce_check();
		global $wpdb;
		$table_id = (int) ( $_POST['table_id'] ?? 0 );

		if ( ! $table_id ) {
			wp_send_json_error( array( 'message' => 'Invalid request parameters.' ) );
		}

		$tbl = self::tables_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT is_booked, is_blocked FROM `{$tbl}` WHERE id = %d", $table_id ), ARRAY_A );
		if ( ! $row ) {
			wp_send_json_error( array( 'message' => 'Table not found.' ) );
		}
		if ( (int) $row['is_booked'] ) {
			wp_send_json_error( array( 'message' => 'Cannot block or unblock an already booked table.' ) );
		}

		$new_blocked = isset( $_POST['blocked'] ) ? ( (int) $_POST['blocked'] ? 1 : 0 ) : ( (int) $row['is_blocked'] ? 0 : 1 );
		$wpdb->update( self::tables_table(), array( 'is_blocked' => $new_blocked ), array( 'id' => $table_id ) );
		wp_send_json_success( array( 'is_blocked' => $new_blocked ) );
	}

	public static function ajax_toggle_seat_block() {
		self::nonce_check();
		global $wpdb;
		$event_id    = (int) ( $_POST['event_id'] ?? 0 );
		$seat_number = (int) ( $_POST['seat_num'] ?? ( $_POST['seat_number'] ?? 0 ) );
		$action_type = sanitize_key( $_POST['action_type'] ?? '' ); // 'block' | 'unblock' | ''

		if ( ! $event_id || $seat_number < 1 ) {
			wp_send_json_error( array( 'message' => 'Invalid request parameters.' ) );
		}

		// Ensure seat is not allocated to a paid booking
		$bk   = self::bookings_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT seat_numbers FROM `{$bk}` WHERE event_id = %d AND booking_type = 'individual' AND status = 'paid'",
			$event_id
		), ARRAY_A );
		foreach ( $rows as $r ) {
			if ( ! empty( $r['seat_numbers'] ) ) {
				$seats = array_map( 'intval', explode( ',', $r['seat_numbers'] ) );
				if ( in_array( $seat_number, $seats, true ) ) {
					wp_send_json_error( array( 'message' => "Seat #{$seat_number} is allocated to a paid booking and cannot be blocked." ) );
				}
			}
		}

		$sb = self::seat_blocks_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$exists = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM `{$sb}` WHERE event_id = %d AND seat_number = %d",
			$event_id,
			$seat_number
		) );

		if ( 'unblock' === $action_type || ( '' === $action_type && $exists ) ) {
			$wpdb->delete( $sb, array( 'event_id' => $event_id, 'seat_number' => $seat_number ) );
			wp_send_json_success( array( 'is_blocked' => 0, 'seat_number' => $seat_number ) );
		} else {
			if ( ! $exists ) {
				$wpdb->insert( $sb, array(
					'event_id'    => $event_id,
					'seat_number' => $seat_number,
					'blocked_by'  => get_current_user_id(),
					'blocked_at'  => current_time( 'mysql' ),
				) );
			}
			wp_send_json_success( array( 'is_blocked' => 1, 'seat_number' => $seat_number ) );
		}
	}

	public static function ajax_clear_seat_blocks() {
		self::nonce_check();
		global $wpdb;
		$event_id = (int) ( $_POST['event_id'] ?? 0 );
		if ( ! $event_id ) {
			wp_send_json_error( array( 'message' => 'Invalid event ID.' ) );
		}
		$sb = self::seat_blocks_table();
		$wpdb->delete( $sb, array( 'event_id' => $event_id ) );
		wp_send_json_success( array( 'cleared' => true ) );
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
			'description'    => 'The Lions Club of Colombo Leads Annual Fundraiser Gala - an elegant evening of dining, entertainment, and community spirit. All proceeds support our community development programmes.',
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
			array( 'name' => 'Standard Table (10-Seater)', 'capacity' => 10, 'price' => 65000.00, 'prefix' => 'T', 'count' => 15, 'mode' => 'seat' ),
			array( 'name' => 'Premium Table (12-Seater)', 'capacity' => 12, 'price' => 90000.00, 'prefix' => 'P', 'count' => 8,  'mode' => 'seat' ),
			array( 'name' => 'VIP Table (8-Seater)',      'capacity' => 8,  'price' => 120000.00, 'prefix' => 'V', 'count' => 5,  'mode' => 'table' ),
		);

		$types = self::table_types_table();
		$tables = self::tables_table();
		foreach ( $types_data as $def ) {
			$wpdb->insert( $types, array(
				'event_id' => $event_id,
				'name'     => $def['name'],
				'capacity' => $def['capacity'],
				'price'    => $def['price'],
			) );
			$type_id = $wpdb->insert_id;
			for ( $i = 1; $i <= $def['count']; $i++ ) {
				$wpdb->insert( $tables, array(
					'event_id'     => $event_id,
					'type_id'      => $type_id,
					'table_label'  => $def['prefix'] . str_pad( $i, 2, '0', STR_PAD_LEFT ),
					'sort_order'   => $i,
					'is_booked'    => 0,
					'booking_mode' => $def['mode'],
					'is_blocked'   => 0,
				) );
			}
		}
	}
}
