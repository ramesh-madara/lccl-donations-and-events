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
		add_action( 'wp_ajax_lccl_de_fr_unblock_table_group',        array( __CLASS__, 'ajax_unblock_table_group' ) );
		add_action( 'wp_ajax_lccl_de_fr_toggle_seat_block',          array( __CLASS__, 'ajax_toggle_seat_block' ) );
		add_action( 'wp_ajax_lccl_de_fundraiser_toggle_seat_block',  array( __CLASS__, 'ajax_toggle_seat_block' ) );
		add_action( 'wp_ajax_lccl_de_fr_block_seats_group',          array( __CLASS__, 'ajax_block_seats_group' ) );
		add_action( 'wp_ajax_lccl_de_fr_unblock_seat_group',         array( __CLASS__, 'ajax_unblock_seat_group' ) );
		add_action( 'wp_ajax_lccl_de_fr_clear_seat_blocks',          array( __CLASS__, 'ajax_clear_seat_blocks' ) );

		// VIP / Private Booking Link hooks
		add_action( 'wp_ajax_lccl_de_fr_get_or_create_token',        array( __CLASS__, 'ajax_get_or_create_token' ) );
		add_action( 'wp_ajax_lccl_de_fr_revoke_token',               array( __CLASS__, 'ajax_revoke_token' ) );

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
	public static function access_tokens_table() { global $wpdb; return $wpdb->prefix . 'lccl_de_fr_access_tokens'; }

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
			block_group_id varchar(64) DEFAULT NULL,
			block_note text DEFAULT NULL,
			blocked_at datetime DEFAULT NULL,
			blocked_by bigint(20) unsigned DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY event_id (event_id),
			KEY type_id (type_id),
			KEY is_booked (is_booked),
			KEY booking_mode (booking_mode),
			KEY is_blocked (is_blocked),
			KEY event_tbl_group (event_id, block_group_id)
		) {$charset};" );

		$seat_blocks = self::seat_blocks_table();
		dbDelta( "CREATE TABLE {$seat_blocks} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_id bigint(20) unsigned NOT NULL,
			group_id varchar(64) DEFAULT NULL,
			seat_number int(11) NOT NULL,
			note text DEFAULT NULL,
			blocked_by bigint(20) unsigned DEFAULT NULL,
			blocked_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_seat (event_id, seat_number),
			KEY event_id (event_id),
			KEY event_group (event_id, group_id)
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
		$col_tbl_grp = $wpdb->get_var( $wpdb->prepare(
			"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'block_group_id'",
			$tables
		) );
		if ( ! $col_tbl_grp ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE `{$tables}` ADD COLUMN `block_group_id` varchar(64) DEFAULT NULL AFTER `is_blocked`, ADD INDEX event_tbl_group (event_id, block_group_id)" );
		}
		$col_table_note = $wpdb->get_var( $wpdb->prepare(
			"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'block_note'",
			$tables
		) );
		if ( ! $col_table_note ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE `{$tables}` ADD COLUMN `block_note` text DEFAULT NULL AFTER `block_group_id`, ADD COLUMN `blocked_at` datetime DEFAULT NULL AFTER `block_note`, ADD COLUMN `blocked_by` bigint(20) unsigned DEFAULT NULL AFTER `blocked_at`" );
		}

		$col_group = $wpdb->get_var( $wpdb->prepare(
			"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'group_id'",
			$seat_blocks
		) );
		if ( ! $col_group ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE `{$seat_blocks}` ADD COLUMN `group_id` varchar(64) DEFAULT NULL AFTER `event_id`, ADD COLUMN `note` text DEFAULT NULL AFTER `seat_number`, ADD INDEX event_group (event_id, group_id)" );
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
			notes text DEFAULT NULL,
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

		$col_bk_notes = $wpdb->get_var( $wpdb->prepare(
			"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'notes'",
			$bookings
		) );
		if ( ! $col_bk_notes ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE `{$bookings}` ADD COLUMN `notes` text DEFAULT NULL AFTER `table_ids`" );
		}

		$col_bk_token = $wpdb->get_var( $wpdb->prepare(
			"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'access_token'",
			$bookings
		) );
		if ( ! $col_bk_token ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE `{$bookings}` ADD COLUMN `access_token` varchar(64) DEFAULT NULL AFTER `notes`, ADD INDEX bk_access_token (`access_token`)" );
		}

		$access_tokens = self::access_tokens_table();
		dbDelta( "CREATE TABLE {$access_tokens} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_id bigint(20) unsigned NOT NULL,
			token varchar(64) NOT NULL,
			item_type varchar(20) NOT NULL,
			item_ref text NOT NULL,
			recipient_label varchar(255) DEFAULT '',
			expires_at datetime DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			redeemed_booking_id bigint(20) unsigned DEFAULT NULL,
			created_by bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL,
			redeemed_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token (token),
			KEY event_id (event_id),
			KEY status (status),
			KEY event_item (event_id, item_type)
		) {$charset};" );

		// Fallback direct creation if dbDelta was skipped
		$wpdb->query( "CREATE TABLE IF NOT EXISTS `{$access_tokens}` (
			`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			`event_id` bigint(20) unsigned NOT NULL,
			`token` varchar(64) NOT NULL,
			`item_type` varchar(20) NOT NULL,
			`item_ref` text NOT NULL,
			`recipient_label` varchar(255) DEFAULT '',
			`expires_at` datetime DEFAULT NULL,
			`status` varchar(20) NOT NULL DEFAULT 'active',
			`redeemed_booking_id` bigint(20) unsigned DEFAULT NULL,
			`created_by` bigint(20) unsigned DEFAULT NULL,
			`created_at` datetime NOT NULL,
			`redeemed_at` datetime DEFAULT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `token` (`token`),
			KEY `event_id` (`event_id`),
			KEY `status` (`status`),
			KEY `event_item` (`event_id`, `item_type`)
		) {$charset};" );

		// Backfill any legacy blocked tables that were blocked before block_group_id was introduced
		$unlinked = $wpdb->get_results( "SELECT id, blocked_at, block_note FROM `{$tables}` WHERE is_blocked = 1 AND (block_group_id IS NULL OR block_group_id = '')", ARRAY_A );
		if ( ! empty( $unlinked ) ) {
			$grouped = array();
			foreach ( $unlinked as $u ) {
				$k = ( $u['blocked_at'] ?: 'legacy' ) . '_' . ( $u['block_note'] ?: 'block' );
				$grouped[ $k ][] = (int) $u['id'];
			}
			foreach ( $grouped as $k => $ids ) {
				$new_gid = 'tbl_grp_' . wp_generate_password( 8, false, false );
				$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( $wpdb->prepare(
					"UPDATE `{$tables}` SET block_group_id = %s WHERE id IN ({$placeholders})",
					$new_gid,
					...$ids
				) );
			}
		}
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
		return (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT ta.*, ty.name AS type_name, ty.capacity, ty.price, u.display_name AS blocked_by_name
			 FROM `{$t}` ta
			 JOIN `{$tt}` ty ON ta.type_id = ty.id
			 LEFT JOIN `{$wpdb->users}` u ON ta.blocked_by = u.ID
			 WHERE ta.event_id = %d
			 ORDER BY ta.type_id ASC, ta.sort_order ASC",
			$event_id
		), ARRAY_A );
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

	/**
	 * Map each seat-pool table to its sequential seat numbers and identify available seats.
	 *
	 * Preserves sequential numbering 1..total_seats across all seat-pool tables,
	 * matching the admin floor plan and visual grid.
	 *
	 * @param int $event_id
	 * @return array List of tables with start_seat, end_seat, available_seats, and available_count.
	 */
	public static function get_seat_pool_table_availability( $event_id ) {
		global $wpdb;
		$tb = self::tables_table();
		$tt = self::table_types_table();
		$bk = self::bookings_table();
		$sb = self::seat_blocks_table();

		// 1. All seat-pool tables in display order (matching admin grid sequence)
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$tables = (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT ta.*, ty.name AS type_name, ty.capacity, ty.price
			 FROM `{$tb}` ta
			 JOIN `{$tt}` ty ON ta.type_id = ty.id
			 WHERE ta.event_id = %d
			   AND (ta.booking_mode = 'seat' OR ta.booking_mode IS NULL OR ta.booking_mode = '')
			 ORDER BY ta.type_id ASC, ta.sort_order ASC, ta.id ASC",
			$event_id
		), ARRAY_A );

		if ( empty( $tables ) ) {
			return array();
		}

		// 2. Already paid/booked seat numbers AND recently pending (anti-collision: 30-min window)
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT seat_numbers FROM `{$bk}`
			 WHERE event_id = %d AND booking_type = 'individual'
			   AND (status = 'paid' OR (status = 'pending' AND created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)))",
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

		$unavailable = array_flip( array_unique( array_merge( $booked, $blocked_seats ) ) );

		// 4. Map sequential seat ranges to each table
		$seat_counter = 1;
		$table_map    = array();

		foreach ( $tables as $tbl ) {
			$cap   = (int) $tbl['capacity'];
			$start = $seat_counter;
			$end   = $seat_counter + $cap - 1;
			$seat_counter += $cap;

			$free_seats = array();
			$is_blocked = (int) $tbl['is_blocked'];

			if ( ! $is_blocked ) {
				for ( $s = $start; $s <= $end; $s++ ) {
					if ( ! isset( $unavailable[ $s ] ) ) {
						$free_seats[] = $s;
					}
				}
			}

			$table_map[] = array(
				'table_id'        => (int) $tbl['id'],
				'table_label'     => $tbl['table_label'],
				'type_id'         => (int) $tbl['type_id'],
				'type_name'       => $tbl['type_name'],
				'capacity'        => $cap,
				'start_seat'      => $start,
				'end_seat'        => $end,
				'is_blocked'      => $is_blocked,
				'available_seats' => $free_seats,
				'available_count' => count( $free_seats ),
			);
		}

		return $table_map;
	}

	/**
	 * Get a flat, sorted list of all individual seat numbers currently available.
	 *
	 * @param int $event_id
	 * @return int[]
	 */
	public static function get_available_seat_numbers( $event_id ) {
		$table_map = self::get_seat_pool_table_availability( $event_id );
		$all_free  = array();
		foreach ( $table_map as $t ) {
			if ( ! empty( $t['available_seats'] ) ) {
				foreach ( $t['available_seats'] as $s ) {
					$all_free[] = $s;
				}
			}
		}
		sort( $all_free, SORT_NUMERIC );
		return $all_free;
	}

	/**
	 * Allocate seats for a party of $qty with 3-tier precedence:
	 *  Tier 1: Single-table fit (party seated 100% together at the earliest matching table).
	 *  Tier 2: Closest adjacent tables (when split is required, pick closest neighbouring tables).
	 *  Tier 3: General split fallback (fill earliest available seats across remaining tables).
	 *
	 * @param int $event_id
	 * @param int $qty
	 * @return array {
	 *     @type int[]    $seat_numbers Allocated seat numbers
	 *     @type int[]    $table_ids    IDs of tables utilized
	 *     @type string[] $table_labels Labels of tables utilized
	 *     @type bool     $is_split     True if party was seated across >1 table
	 *     @type int      $tier         Allocation tier applied (1, 2, or 3; 0 if failed)
	 * }
	 */
	public static function allocate_seats_for_party( $event_id, $qty ) {
		$qty = (int) $qty;
		if ( $qty < 1 ) {
			return array(
				'seat_numbers' => array(),
				'table_ids'    => array(),
				'table_labels' => array(),
				'is_split'     => false,
				'tier'         => 0,
			);
		}

		$tables = self::get_seat_pool_table_availability( $event_id );
		if ( empty( $tables ) ) {
			return array(
				'seat_numbers' => array(),
				'table_ids'    => array(),
				'table_labels' => array(),
				'is_split'     => false,
				'tier'         => 0,
			);
		}

		// ─────────────────────────────────────────────────────────────────
		// TIER 1: Single-Table Fit (Keep Party 100% Together)
		// ─────────────────────────────────────────────────────────────────
		foreach ( $tables as $tbl ) {
			if ( $tbl['available_count'] >= $qty ) {
				$allocated_seats = array_slice( $tbl['available_seats'], 0, $qty );
				return array(
					'seat_numbers' => $allocated_seats,
					'table_ids'    => array( $tbl['table_id'] ),
					'table_labels' => array( $tbl['table_label'] ),
					'is_split'     => false,
					'tier'         => 1,
				);
			}
		}

		// ─────────────────────────────────────────────────────────────────
		// TIER 2: Closest Adjacent Tables
		// ─────────────────────────────────────────────────────────────────
		// Search sliding windows of neighbouring tables (window size 2, then 3, up to 4)
		// whose combined free seats can hold the party.
		$num_tables = count( $tables );
		for ( $window_size = 2; $window_size <= min( 4, $num_tables ); $window_size++ ) {
			for ( $i = 0; $i <= $num_tables - $window_size; $i++ ) {
				$window_avail = 0;
				for ( $w = 0; $w < $window_size; $w++ ) {
					$window_avail += $tables[ $i + $w ]['available_count'];
				}
				if ( $window_avail >= $qty ) {
					// Found an adjacent cluster of neighbouring tables!
					$allocated_seats = array();
					$used_tbl_ids    = array();
					$used_tbl_labels = array();

					for ( $w = 0; $w < $window_size; $w++ ) {
						$tbl = $tables[ $i + $w ];
						if ( $tbl['available_count'] < 1 ) {
							continue;
						}
						foreach ( $tbl['available_seats'] as $s ) {
							$allocated_seats[] = $s;
							if ( ! in_array( $tbl['table_id'], $used_tbl_ids, true ) ) {
								$used_tbl_ids[]    = $tbl['table_id'];
								$used_tbl_labels[] = $tbl['table_label'];
							}
							if ( count( $allocated_seats ) === $qty ) {
								break 2;
							}
						}
					}

					return array(
						'seat_numbers' => $allocated_seats,
						'table_ids'    => $used_tbl_ids,
						'table_labels' => $used_tbl_labels,
						'is_split'     => true,
						'tier'         => 2,
					);
				}
			}
		}

		// ─────────────────────────────────────────────────────────────────
		// TIER 3: General Split Fallback (Near Sell-Out)
		// ─────────────────────────────────────────────────────────────────
		// If total remaining venue capacity >= $qty, collect seats in order
		// from any remaining tables with free seats so no booking is rejected.
		$total_avail = 0;
		foreach ( $tables as $tbl ) {
			$total_avail += $tbl['available_count'];
		}
		if ( $total_avail < $qty ) {
			return array(
				'seat_numbers' => array(),
				'table_ids'    => array(),
				'table_labels' => array(),
				'is_split'     => false,
				'tier'         => 0,
			);
		}

		$allocated_seats = array();
		$used_tbl_ids    = array();
		$used_tbl_labels = array();

		foreach ( $tables as $tbl ) {
			if ( $tbl['available_count'] < 1 ) {
				continue;
			}
			foreach ( $tbl['available_seats'] as $s ) {
				$allocated_seats[] = $s;
				if ( ! in_array( $tbl['table_id'], $used_tbl_ids, true ) ) {
					$used_tbl_ids[]    = $tbl['table_id'];
					$used_tbl_labels[] = $tbl['table_label'];
				}
				if ( count( $allocated_seats ) === $qty ) {
					break 2;
				}
			}
		}

		return array(
			'seat_numbers' => $allocated_seats,
			'table_ids'    => $used_tbl_ids,
			'table_labels' => $used_tbl_labels,
			'is_split'     => true,
			'tier'         => 3,
		);
	}

	/**
	 * Format a comma-separated list of seat numbers with their assigned table labels.
	 *
	 * E.g. "Table T-01: Seat #1, Seat #2" or for split:
	 * "Table T-02: Seat #19, Seat #20 & Table T-03: Seat #21, Seat #22"
	 *
	 * @param int          $event_id
	 * @param string|array $seat_numbers
	 * @return string
	 */
	public static function format_seats_with_tables( $event_id, $seat_numbers ) {
		if ( is_array( $seat_numbers ) ) {
			$nums = array_filter( array_map( 'intval', $seat_numbers ) );
		} else {
			$nums = array_filter( array_map( 'intval', explode( ',', (string) $seat_numbers ) ) );
		}
		if ( empty( $nums ) ) {
			return '';
		}
		sort( $nums, SORT_NUMERIC );

		$tables = self::get_seat_pool_table_availability( $event_id );
		if ( empty( $tables ) ) {
			return 'Seat #' . implode( ', Seat #', $nums );
		}

		// Group seat numbers by table
		$grouped = array();
		foreach ( $nums as $n ) {
			$found = false;
			foreach ( $tables as $tbl ) {
				if ( $n >= $tbl['start_seat'] && $n <= $tbl['end_seat'] ) {
					$lbl = $tbl['table_label'];
					if ( ! isset( $grouped[ $lbl ] ) ) {
						$grouped[ $lbl ] = array();
					}
					$grouped[ $lbl ][] = $n;
					$found = true;
					break;
				}
			}
			if ( ! $found ) {
				$grouped['General'][] = $n;
			}
		}

		$parts = array();
		foreach ( $grouped as $tbl_label => $s_list ) {
			$seats_str = 'Seat #' . implode( ', Seat #', $s_list );
			if ( 'General' === $tbl_label ) {
				$parts[] = $seats_str;
			} else {
				$parts[] = 'Table ' . $tbl_label . ' (' . $seats_str . ')';
			}
		}

		return implode( ' & ', $parts );
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

	public static function get_blocked_seat_details( $event_id ) {
		global $wpdb;
		$sb = self::seat_blocks_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT sb.*, u.display_name AS blocked_by_name
			 FROM `{$sb}` sb
			 LEFT JOIN `{$wpdb->users}` u ON sb.blocked_by = u.ID
			 WHERE sb.event_id = %d
			 ORDER BY sb.seat_number ASC",
			$event_id
		), ARRAY_A );
	}

	public static function get_blocked_seat_groups( $event_id ) {
		global $wpdb;
		$sb = self::seat_blocks_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT sb.*, u.display_name AS blocked_by_name
			 FROM `{$sb}` sb
			 LEFT JOIN `{$wpdb->users}` u ON sb.blocked_by = u.ID
			 WHERE sb.event_id = %d
			 ORDER BY sb.blocked_at DESC, sb.seat_number ASC",
			$event_id
		), ARRAY_A );

		$groups = array();
		foreach ( $rows as $row ) {
			$gid = ! empty( $row['group_id'] ) ? (string) $row['group_id'] : 'single_' . $row['id'];
			if ( ! isset( $groups[ $gid ] ) ) {
				$groups[ $gid ] = array(
					'group_id'        => $gid,
					'is_group'        => ! empty( $row['group_id'] ),
					'note'            => $row['note'] ?? '',
					'blocked_by'      => (int) $row['blocked_by'],
					'blocked_by_name' => $row['blocked_by_name'] ?? '',
					'blocked_at'      => $row['blocked_at'],
					'seats'           => array(),
				);
			}
			$groups[ $gid ]['seats'][] = (int) $row['seat_number'];
		}

		foreach ( $groups as &$g ) {
			sort( $g['seats'], SORT_NUMERIC );
			$g['seat_count'] = count( $g['seats'] );
		}
		unset( $g );

		return array_values( $groups );
	}

	public static function get_booked_seat_numbers( $event_id ) {
		global $wpdb;
		$bk = self::bookings_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT seat_numbers FROM `{$bk}`
			 WHERE event_id = %d AND booking_type = 'individual'
			   AND (status = 'paid' OR (status = 'pending' AND created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)))",
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

		// Booked seats from paid individual bookings + recent pending (anti-collision)
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$seat_pool_booked = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(qty), 0) FROM `{$bk}`
			 WHERE event_id = %d AND booking_type = 'individual'
			   AND (status = 'paid' OR (status = 'pending' AND created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)))",
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

	/**
	 * Helper to get table labels from comma-separated table IDs or array.
	 *
	 * @param string|array $table_ids_csv CSV string or array of IDs.
	 * @return string[]
	 */
	public static function get_table_labels( $table_ids_csv ) {
		if ( is_array( $table_ids_csv ) ) {
			$ids = array_filter( array_map( 'intval', $table_ids_csv ) );
		} else {
			if ( '' === trim( (string) $table_ids_csv ) ) {
				return array();
			}
			$ids = array_filter( array_map( 'intval', explode( ',', (string) $table_ids_csv ) ) );
		}
		if ( empty( $ids ) ) {
			return array();
		}
		global $wpdb;
		$tbl = self::tables_table();
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT table_label FROM `{$tbl}` WHERE id IN ($placeholders) ORDER BY sort_order ASC",
			...$ids
		), ARRAY_A );
		return array_column( $rows, 'table_label' );
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

		$table_ids = array();
		if ( ! empty( $_POST['table_ids'] ) ) {
			$table_ids = array_filter( array_map( 'intval', (array) $_POST['table_ids'] ) );
		} elseif ( ! empty( $_POST['table_id'] ) ) {
			$table_ids = array( (int) $_POST['table_id'] );
		}

		if ( empty( $table_ids ) ) {
			wp_send_json_error( array( 'message' => 'Invalid request parameters.' ) );
		}

		$tbl         = self::tables_table();
		$new_blocked = isset( $_POST['blocked'] ) ? ( (int) $_POST['blocked'] ? 1 : 0 ) : 1;
		$note        = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : null;
		$uid         = get_current_user_id();
		$now         = current_time( 'mysql' );

		// Self-healing check for block_group_id column
		static $checked_tbl_grp = false;
		if ( ! $checked_tbl_grp ) {
			$checked_tbl_grp = true;
			$has_col = $wpdb->get_var( $wpdb->prepare(
				"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'block_group_id'",
				$tbl
			) );
			if ( ! $has_col ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "ALTER TABLE `{$tbl}` ADD COLUMN `block_group_id` varchar(64) DEFAULT NULL AFTER `is_blocked`" );
			}
		}

		$group_id = ! empty( $_POST['group_id'] ) ? sanitize_key( $_POST['group_id'] ) : null;
		if ( $new_blocked && ! $group_id && count( $table_ids ) > 1 ) {
			$group_id = 'tbl_grp_' . wp_generate_password( 6, false, false ) . '_' . time();
		}

		$update = array( 'is_blocked' => $new_blocked );
		if ( $new_blocked ) {
			$update['block_group_id'] = $group_id;
			$update['block_note']     = $note;
			$update['blocked_at']     = $now;
			$update['blocked_by']     = $uid;
		} else {
			$update['block_group_id'] = null;
			$update['block_note']     = null;
			$update['blocked_at']     = null;
			$update['blocked_by']     = null;
		}

		$updated_ids = array();
		foreach ( $table_ids as $tid ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT is_booked, is_blocked FROM `{$tbl}` WHERE id = %d", $tid ), ARRAY_A );
			if ( $row && ! (int) $row['is_booked'] ) {
				$wpdb->update( $tbl, $update, array( 'id' => $tid ) );
				$updated_ids[] = $tid;
			}
		}

		if ( empty( $updated_ids ) ) {
			wp_send_json_error( array( 'message' => 'Selected table(s) cannot be modified or are already booked.' ) );
		}

		$user_data = get_userdata( $uid );
		wp_send_json_success( array(
			'is_blocked'      => $new_blocked,
			'table_ids'       => $updated_ids,
			'table_id'        => $updated_ids[0] ?? 0,
			'block_group_id'  => $group_id,
			'block_note'      => $update['block_note'] ?? '',
			'blocked_at'      => $update['blocked_at'] ?? '',
			'blocked_by'      => $update['blocked_by'] ?? 0,
			'blocked_by_name' => $user_data ? $user_data->display_name : '',
		) );
	}

	public static function ajax_unblock_table_group() {
		self::nonce_check();
		global $wpdb;
		$event_id  = (int) ( $_POST['event_id'] ?? 0 );
		$group_id  = sanitize_text_field( wp_unslash( $_POST['group_id'] ?? '' ) );
		$table_ids = ! empty( $_POST['table_ids'] ) ? array_filter( array_map( 'intval', (array) $_POST['table_ids'] ) ) : array();

		if ( ! $event_id ) {
			wp_send_json_error( array( 'message' => 'Invalid event ID.' ) );
		}

		$tbl = self::tables_table();
		if ( ! empty( $table_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $table_ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( $wpdb->prepare(
				"UPDATE `{$tbl}` SET is_blocked = 0, block_group_id = NULL, block_note = NULL, blocked_at = NULL, blocked_by = NULL WHERE event_id = %d AND id IN ({$placeholders})",
				$event_id,
				...$table_ids
			) );
		} elseif ( ! empty( $group_id ) ) {
			if ( strpos( $group_id, 'legacy_' ) === 0 ) {
				$parts    = explode( '_', substr( $group_id, 7 ), 2 );
				$blk_at   = $parts[0] ?? '';
				$blk_note = $parts[1] ?? '';
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( $wpdb->prepare(
					"UPDATE `{$tbl}` SET is_blocked = 0, block_group_id = NULL, block_note = NULL, blocked_at = NULL, blocked_by = NULL WHERE event_id = %d AND (block_group_id = %s OR (blocked_at = %s AND block_note = %s))",
					$event_id,
					$group_id,
					$blk_at,
					$blk_note
				) );
			} elseif ( strpos( $group_id, 'single_tbl_' ) === 0 ) {
				$tid = (int) substr( $group_id, 11 );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( $wpdb->prepare(
					"UPDATE `{$tbl}` SET is_blocked = 0, block_group_id = NULL, block_note = NULL, blocked_at = NULL, blocked_by = NULL WHERE event_id = %d AND id = %d",
					$event_id,
					$tid
				) );
			} else {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( $wpdb->prepare(
					"UPDATE `{$tbl}` SET is_blocked = 0, block_group_id = NULL, block_note = NULL, blocked_at = NULL, blocked_by = NULL WHERE event_id = %d AND block_group_id = %s",
					$event_id,
					$group_id
				) );
			}
		} else {
			wp_send_json_error( array( 'message' => 'Please provide a group ID or table IDs to unblock.' ) );
		}

		self::revoke_tokens_for_items( $event_id, array( 'table', 'table_group' ), $group_id, $table_ids );

		wp_send_json_success( array(
			'event_id'  => $event_id,
			'group_id'  => $group_id,
			'table_ids' => $table_ids,
		) );
	}

	public static function ajax_toggle_seat_block() {
		self::nonce_check();
		global $wpdb;
		$event_id    = (int) ( $_POST['event_id'] ?? 0 );
		$seat_number = (int) ( $_POST['seat_num'] ?? ( $_POST['seat_number'] ?? 0 ) );
		$action_type = sanitize_key( $_POST['action_type'] ?? '' ); // 'block' | 'unblock' | ''
		$note        = sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) );
		$group_id    = sanitize_text_field( wp_unslash( $_POST['group_id'] ?? '' ) );

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
			wp_send_json_success( array(
				'is_blocked'    => 0,
				'seat_number'   => $seat_number,
				'groups'        => self::get_blocked_seat_groups( $event_id ),
				'blocked_seats' => self::get_blocked_seat_numbers( $event_id ),
			) );
		} else {
			if ( ! $exists ) {
				$wpdb->insert( $sb, array(
					'event_id'    => $event_id,
					'group_id'    => ! empty( $group_id ) ? $group_id : null,
					'seat_number' => $seat_number,
					'note'        => $note,
					'blocked_by'  => get_current_user_id(),
					'blocked_at'  => current_time( 'mysql' ),
				) );
			}
			wp_send_json_success( array(
				'is_blocked'    => 1,
				'seat_number'   => $seat_number,
				'note'          => $note,
				'group_id'      => $group_id,
				'groups'        => self::get_blocked_seat_groups( $event_id ),
				'blocked_seats' => self::get_blocked_seat_numbers( $event_id ),
			) );
		}
	}

	public static function ajax_block_seats_group() {
		self::nonce_check();
		global $wpdb;
		$event_id    = (int) ( $_POST['event_id'] ?? 0 );
		$seat_nums   = isset( $_POST['seat_numbers'] ) ? (array) $_POST['seat_numbers'] : array();
		$note        = sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) );
		$group_label = sanitize_text_field( wp_unslash( $_POST['group_label'] ?? '' ) );
		$group_id    = sanitize_key( $_POST['group_id'] ?? '' );

		if ( ! $event_id || empty( $seat_nums ) ) {
			wp_send_json_error( array( 'message' => 'Please select at least one seat to block.' ) );
		}

		$seats = array_filter( array_map( 'intval', $seat_nums ) );
		if ( empty( $seats ) ) {
			wp_send_json_error( array( 'message' => 'No valid seat numbers provided.' ) );
		}

		if ( empty( $group_id ) ) {
			$prefix = ! empty( $group_label ) ? sanitize_title( $group_label ) : 'grp';
			$group_id = substr( $prefix, 0, 20 ) . '_' . wp_generate_password( 6, false, false ) . '_' . time();
		}

		// Check if any seat is allocated to a paid booking
		$bk = self::bookings_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT seat_numbers FROM `{$bk}` WHERE event_id = %d AND booking_type = 'individual' AND status = 'paid'",
			$event_id
		), ARRAY_A );
		$paid = array();
		foreach ( $rows as $r ) {
			if ( ! empty( $r['seat_numbers'] ) ) {
				foreach ( explode( ',', $r['seat_numbers'] ) as $sn ) {
					$paid[] = (int) trim( $sn );
				}
			}
		}
		$conflicts = array_intersect( $seats, $paid );
		if ( ! empty( $conflicts ) ) {
			wp_send_json_error( array(
				'message' => sprintf( 'Seat(s) #%s are allocated to paid bookings and cannot be blocked.', implode( ', #', $conflicts ) ),
			) );
		}

		$sb  = self::seat_blocks_table();
		$now = current_time( 'mysql' );
		$uid = get_current_user_id();

		foreach ( $seats as $s_num ) {
			// Remove any previous individual block for this seat
			$wpdb->delete( $sb, array( 'event_id' => $event_id, 'seat_number' => $s_num ) );
			$wpdb->insert( $sb, array(
				'event_id'    => $event_id,
				'group_id'    => $group_id,
				'seat_number' => $s_num,
				'note'        => $note,
				'blocked_by'  => $uid,
				'blocked_at'  => $now,
			) );
		}

		wp_send_json_success( array(
			'event_id'      => $event_id,
			'group_id'      => $group_id,
			'seat_numbers'  => $seats,
			'note'          => $note,
			'groups'        => self::get_blocked_seat_groups( $event_id ),
			'blocked_seats' => self::get_blocked_seat_numbers( $event_id ),
		) );
	}

	public static function ajax_unblock_seat_group() {
		self::nonce_check();
		global $wpdb;
		$event_id = (int) ( $_POST['event_id'] ?? 0 );
		$group_id = sanitize_text_field( wp_unslash( $_POST['group_id'] ?? '' ) );
		$seat_num = (int) ( $_POST['seat_number'] ?? 0 );

		if ( ! $event_id ) {
			wp_send_json_error( array( 'message' => 'Invalid event ID.' ) );
		}

		$sb = self::seat_blocks_table();

		if ( ! empty( $group_id ) ) {
			$wpdb->delete( $sb, array( 'event_id' => $event_id, 'group_id' => $group_id ) );
		} elseif ( $seat_num > 0 ) {
			$wpdb->delete( $sb, array( 'event_id' => $event_id, 'seat_number' => $seat_num ) );
		} else {
			wp_send_json_error( array( 'message' => 'Please provide a group ID or seat number to unblock.' ) );
		}

		self::revoke_tokens_for_items( $event_id, array( 'seat', 'seat_group' ), $group_id, $seat_num > 0 ? array( $seat_num ) : array() );

		wp_send_json_success( array(
			'event_id'      => $event_id,
			'groups'        => self::get_blocked_seat_groups( $event_id ),
			'blocked_seats' => self::get_blocked_seat_numbers( $event_id ),
		) );
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
		self::revoke_tokens_for_items( $event_id, array( 'seat', 'seat_group' ) );
		wp_send_json_success( array(
			'cleared'       => true,
			'groups'        => array(),
			'blocked_seats' => array(),
		) );
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

	/* -----------------------------------------------------------------------
	 * Private / VIP Access Token helpers
	 * --------------------------------------------------------------------- */

	public static function get_vip_booking_url( $token, $event_id = 0 ) {
		$base = class_exists( 'LCCL_DE_Roles' ) ? LCCL_DE_Roles::fundraiser_page_url() : '';
		if ( empty( $base ) ) {
			$base = home_url( '/fundraiser-seat-booking/' );
		}
		$args = array( 'vip_pass' => $token );
		if ( $event_id > 0 ) {
			$args['event_id'] = $event_id;
		}
		return add_query_arg( $args, $base );
	}

	public static function get_active_token_for_item( $event_id, $item_type, $item_ref ) {
		global $wpdb;
		$tbl = self::access_tokens_table();
		$ref = is_array( $item_ref ) ? implode( ',', array_map( 'trim', $item_ref ) ) : trim( (string) $item_ref );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM `{$tbl}`
			 WHERE event_id = %d AND item_type = %s AND item_ref = %s AND status = 'active'
			   AND (expires_at IS NULL OR expires_at > %s)
			 ORDER BY id DESC LIMIT 1",
			$event_id,
			$item_type,
			$ref,
			current_time( 'mysql' )
		), ARRAY_A );

		return $row ?: null;
	}

	public static function generate_access_token( $event_id, $item_type, $item_ref, $recipient_label = '', $expires_hours = 0 ) {
		global $wpdb;
		$tbl = self::access_tokens_table();
		$ref = is_array( $item_ref ) ? implode( ',', array_map( 'trim', $item_ref ) ) : trim( (string) $item_ref );

		// Check if active token already exists for this exact item
		$existing = self::get_active_token_for_item( $event_id, $item_type, $ref );
		if ( $existing ) {
			$existing['booking_url'] = self::get_vip_booking_url( $existing['token'], $event_id );
			return $existing;
		}

		$token = wp_generate_password( 32, false, false );
		$now   = current_time( 'mysql' );
		$uid   = get_current_user_id();

		$expires_at = null;
		$hours = (int) $expires_hours;
		if ( $hours > 0 ) {
			$expires_at = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + ( $hours * HOUR_IN_SECONDS ) );
		}

		$inserted = $wpdb->insert(
			$tbl,
			array(
				'event_id'        => (int) $event_id,
				'token'           => $token,
				'item_type'       => sanitize_key( $item_type ),
				'item_ref'        => $ref,
				'recipient_label' => sanitize_text_field( $recipient_label ),
				'expires_at'      => $expires_at,
				'status'          => 'active',
				'created_by'      => $uid,
				'created_at'      => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		if ( ! $inserted ) {
			// Auto-heal: Ensure table exists and retry
			self::install( $wpdb->get_charset_collate() );
			$inserted = $wpdb->insert(
				$tbl,
				array(
					'event_id'        => (int) $event_id,
					'token'           => $token,
					'item_type'       => sanitize_key( $item_type ),
					'item_ref'        => $ref,
					'recipient_label' => sanitize_text_field( $recipient_label ),
					'expires_at'      => $expires_at,
					'status'          => 'active',
					'created_by'      => $uid,
					'created_at'      => $now,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
			);
			if ( ! $inserted ) {
				return false;
			}
		}

		$id = $wpdb->insert_id;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$tbl}` WHERE id = %d", $id ), ARRAY_A );
		if ( $row ) {
			$row['booking_url'] = self::get_vip_booking_url( $row['token'], $event_id );
		}
		return $row;
	}

	public static function validate_access_token( $token_string, $event_id = 0 ) {
		global $wpdb;
		$token_string = sanitize_text_field( trim( $token_string ) );
		if ( empty( $token_string ) ) {
			return array( 'valid' => false, 'error' => 'empty_token', 'message' => __( 'Missing VIP pass code.', 'lccl-de' ) );
		}

		$tbl = self::access_tokens_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$tbl}` WHERE token = %s LIMIT 1", $token_string ), ARRAY_A );

		if ( ! $row ) {
			return array( 'valid' => false, 'error' => 'not_found', 'message' => __( 'Invalid VIP reservation link.', 'lccl-de' ) );
		}

		if ( 'redeemed' === $row['status'] ) {
			return array(
				'valid'      => false,
				'error'      => 'redeemed',
				'token_data' => $row,
				'message'    => __( 'This private VIP booking link has already been used and redeemed.', 'lccl-de' ),
			);
		}

		if ( 'revoked' === $row['status'] ) {
			return array(
				'valid'      => false,
				'error'      => 'revoked',
				'token_data' => $row,
				'message'    => __( 'This private VIP reservation link has been revoked by event administration.', 'lccl-de' ),
			);
		}

		if ( ! empty( $row['expires_at'] ) && strtotime( $row['expires_at'] ) < current_time( 'timestamp' ) ) {
			return array(
				'valid'      => false,
				'error'      => 'expired',
				'token_data' => $row,
				'message'    => __( 'This private VIP reservation link has expired.', 'lccl-de' ),
			);
		}

		if ( $event_id > 0 && (int) $row['event_id'] !== (int) $event_id ) {
			return array(
				'valid'      => false,
				'error'      => 'event_mismatch',
				'token_data' => $row,
				'message'    => __( 'This VIP reservation link is for a different event.', 'lccl-de' ),
			);
		}

		// Resolve items unlocked by this token
		$ev_id   = (int) $row['event_id'];
		$itype   = $row['item_type'];
		$iref    = $row['item_ref'];
		$details = array(
			'item_type'       => $itype,
			'item_ref'        => $iref,
			'recipient_label' => $row['recipient_label'],
			'tables'          => array(),
			'table_ids'       => array(),
			'table_labels'    => array(),
			'seats'           => array(),
			'seat_count'      => 0,
			'total_capacity'  => 0,
			'total_amount'    => 0.0,
			'type_id'         => 0,
			'booking_mode'    => 'table',
		);

		$tables_table = self::tables_table();
		$types_table  = self::table_types_table();
		$ev_row       = self::get_event( $ev_id );
		$ticket_price = $ev_row ? (float) $ev_row['ticket_price'] : 0.0;

		if ( 'table' === $itype || 'table_group' === $itype ) {
			$details['booking_mode'] = 'table';
			$found_tables = array();
			if ( strpos( $iref, 'legacy_' ) === 0 ) {
				$parts    = explode( '_', substr( $iref, 7 ), 2 );
				$blk_at   = $parts[0] ?? '';
				$blk_note = $parts[1] ?? '';
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$found_tables = (array) $wpdb->get_results( $wpdb->prepare(
					"SELECT ta.*, ty.name AS type_name, ty.capacity, ty.price
					 FROM `{$tables_table}` ta
					 JOIN `{$types_table}` ty ON ta.type_id = ty.id
					 WHERE ta.event_id = %d AND (ta.block_group_id = %s OR (ta.blocked_at = %s AND ta.block_note = %s))
					 ORDER BY ta.sort_order ASC, ta.id ASC",
					$ev_id,
					$iref,
					$blk_at,
					$blk_note
				), ARRAY_A );
			} elseif ( strpos( $iref, 'single_tbl_' ) === 0 ) {
				$tid = (int) substr( $iref, 11 );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$found_tables = (array) $wpdb->get_results( $wpdb->prepare(
					"SELECT ta.*, ty.name AS type_name, ty.capacity, ty.price
					 FROM `{$tables_table}` ta
					 JOIN `{$types_table}` ty ON ta.type_id = ty.id
					 WHERE ta.event_id = %d AND ta.id = %d
					 ORDER BY ta.sort_order ASC, ta.id ASC",
					$ev_id,
					$tid
				), ARRAY_A );
			} elseif ( strpos( $iref, 'tbl_grp_' ) === 0 ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$found_tables = (array) $wpdb->get_results( $wpdb->prepare(
					"SELECT ta.*, ty.name AS type_name, ty.capacity, ty.price
					 FROM `{$tables_table}` ta
					 JOIN `{$types_table}` ty ON ta.type_id = ty.id
					 WHERE ta.event_id = %d AND ta.block_group_id = %s
					 ORDER BY ta.sort_order ASC, ta.id ASC",
					$ev_id,
					$iref
				), ARRAY_A );
			} else {
				$tids = array_filter( array_map( 'intval', explode( ',', $iref ) ) );
				if ( ! empty( $tids ) ) {
					$placeholders = implode( ',', array_fill( 0, count( $tids ), '%d' ) );
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$found_tables = (array) $wpdb->get_results( $wpdb->prepare(
						"SELECT ta.*, ty.name AS type_name, ty.capacity, ty.price
						 FROM `{$tables_table}` ta
						 JOIN `{$types_table}` ty ON ta.type_id = ty.id
						 WHERE ta.event_id = %d AND ta.id IN ({$placeholders})
						 ORDER BY ta.sort_order ASC, ta.id ASC",
						$ev_id,
						...$tids
					), ARRAY_A );
				}
			}

			$tot_cap = 0;
			$tot_amt = 0.0;
			$t_labels = array();
			$t_ids    = array();
			$type_id  = 0;
			foreach ( $found_tables as $ft ) {
				$t_ids[]    = (int) $ft['id'];
				$t_labels[] = $ft['table_label'];
				$tot_cap   += (int) $ft['capacity'];
				$tot_amt   += (float) $ft['price'];
				if ( ! $type_id ) {
					$type_id = (int) $ft['type_id'];
				}
			}
			$details['tables']         = $found_tables;
			$details['table_ids']      = $t_ids;
			$details['table_labels']   = $t_labels;
			$details['total_capacity'] = $tot_cap;
			$details['total_amount']   = $tot_amt;
			$details['type_id']        = $type_id;
		} elseif ( 'seat' === $itype || 'seat_group' === $itype ) {
			$details['booking_mode'] = 'individual';
			$sb = self::seat_blocks_table();
			$found_seats = array();
			if ( strpos( $iref, 'grp_' ) === 0 || strpos( $iref, 'single_' ) === 0 || ! is_numeric( str_replace( ',', '', $iref ) ) ) {
				// Group ID
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$rows = (array) $wpdb->get_col( $wpdb->prepare(
					"SELECT seat_number FROM `{$sb}` WHERE event_id = %d AND group_id = %s ORDER BY seat_number ASC",
					$ev_id,
					$iref
				) );
				$found_seats = array_map( 'intval', $rows );
			} else {
				$snums = array_filter( array_map( 'intval', explode( ',', $iref ) ) );
				if ( ! empty( $snums ) ) {
					$placeholders = implode( ',', array_fill( 0, count( $snums ), '%d' ) );
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$rows = (array) $wpdb->get_col( $wpdb->prepare(
						"SELECT seat_number FROM `{$sb}` WHERE event_id = %d AND seat_number IN ({$placeholders}) ORDER BY seat_number ASC",
						$ev_id,
						...$snums
					) );
					$found_seats = array_map( 'intval', $rows );
				}
			}

			sort( $found_seats, SORT_NUMERIC );
			$details['seats']        = $found_seats;
			$details['seat_count']   = count( $found_seats );
			$details['total_amount'] = count( $found_seats ) * $ticket_price;
		}

		return array(
			'valid'        => true,
			'token'        => $row['token'],
			'token_data'   => $row,
			'item_details' => $details,
		);
	}

	public static function redeem_access_token( $token_string, $booking_id ) {
		global $wpdb;
		$token_string = sanitize_text_field( trim( $token_string ) );
		if ( empty( $token_string ) || ! $booking_id ) {
			return false;
		}

		$tbl = self::access_tokens_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (bool) $wpdb->query( $wpdb->prepare(
			"UPDATE `{$tbl}`
			 SET status = 'redeemed', redeemed_booking_id = %d, redeemed_at = %s
			 WHERE token = %s AND status = 'active'",
			$booking_id,
			current_time( 'mysql' ),
			$token_string
		) );
	}

	public static function revoke_access_token( $token_id_or_string ) {
		global $wpdb;
		$tbl = self::access_tokens_table();
		if ( is_numeric( $token_id_or_string ) && (int) $token_id_or_string > 0 ) {
			return (bool) $wpdb->update( $tbl, array( 'status' => 'revoked' ), array( 'id' => (int) $token_id_or_string ) );
		}
		$token = sanitize_text_field( trim( $token_id_or_string ) );
		if ( empty( $token ) ) {
			return false;
		}
		return (bool) $wpdb->update( $tbl, array( 'status' => 'revoked' ), array( 'token' => $token ) );
	}

	public static function revoke_tokens_for_items( $event_id, $item_types, $group_id = '', $item_ids = array() ) {
		global $wpdb;
		$tbl         = self::access_tokens_table();
		$types_clean = array_map( 'sanitize_key', (array) $item_types );
		$types_in    = "'" . implode( "','", $types_clean ) . "'";

		if ( ! empty( $group_id ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( $wpdb->prepare(
				"UPDATE `{$tbl}` SET status = 'revoked' WHERE event_id = %d AND item_type IN ({$types_in}) AND item_ref = %s AND status = 'active'",
				$event_id,
				$group_id
			) );
		}

		if ( ! empty( $item_ids ) ) {
			$csv = implode( ',', $item_ids );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( $wpdb->prepare(
				"UPDATE `{$tbl}` SET status = 'revoked' WHERE event_id = %d AND item_type IN ({$types_in}) AND item_ref = %s AND status = 'active'",
				$event_id,
				$csv
			) );
			foreach ( $item_ids as $iid ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( $wpdb->prepare(
					"UPDATE `{$tbl}` SET status = 'revoked' WHERE event_id = %d AND item_type IN ({$types_in}) AND item_ref = %s AND status = 'active'",
					$event_id,
					(string) $iid
				) );
			}
		}
	}

	public static function get_event_tokens( $event_id ) {
		global $wpdb;
		$tbl = self::access_tokens_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM `{$tbl}` WHERE event_id = %d ORDER BY id DESC",
			(int) $event_id
		), ARRAY_A );
		foreach ( $rows as &$r ) {
			$r['booking_url'] = self::get_vip_booking_url( $r['token'], (int) $event_id );
		}
		unset( $r );
		return $rows;
	}

	public static function ajax_get_or_create_token() {
		self::nonce_check();
		$event_id        = (int) ( $_POST['event_id'] ?? 0 );
		$item_type       = sanitize_key( $_POST['item_type'] ?? '' );
		$item_ref        = sanitize_text_field( wp_unslash( $_POST['item_ref'] ?? '' ) );
		$recipient_label = sanitize_text_field( wp_unslash( $_POST['recipient_label'] ?? '' ) );
		$expires_hours   = (int) ( $_POST['expires_hours'] ?? 0 );
		$force_new       = ! empty( $_POST['force_new'] );

		if ( ! $event_id || empty( $item_type ) || empty( $item_ref ) ) {
			wp_send_json_error( array( 'message' => 'Missing required parameters.' ) );
		}

		if ( $force_new ) {
			$prev = self::get_active_token_for_item( $event_id, $item_type, $item_ref );
			if ( $prev ) {
				self::revoke_access_token( $prev['token'] );
			}
		}

		$token_data = self::generate_access_token( $event_id, $item_type, $item_ref, $recipient_label, $expires_hours );
		if ( ! $token_data ) {
			global $wpdb;
			$detail = ! empty( $wpdb->last_error ) ? ': ' . $wpdb->last_error : '.';
			wp_send_json_error( array( 'message' => 'Failed to generate VIP booking link' . $detail ) );
		}

		$val = self::validate_access_token( $token_data['token'], $event_id );

		$event_row  = self::get_event( $event_id );
		$event_name = $event_row ? $event_row['name'] : 'Fundraiser Gala';
		$share_text = sprintf(
			/* translators: 1: Event name, 2: VIP booking url */
			__( "Greetings! An exclusive VIP reservation has been reserved for you for %1\$s. Please use this private link to confirm and complete your booking:\n%2\$s", 'lccl-de' ),
			$event_name,
			$token_data['booking_url']
		);

		wp_send_json_success( array(
			'token'           => $token_data['token'],
			'booking_url'     => $token_data['booking_url'],
			'recipient_label' => $token_data['recipient_label'],
			'expires_at'      => $token_data['expires_at'],
			'status'          => $token_data['status'],
			'item_details'    => $val['item_details'] ?? null,
			'whatsapp_share'  => 'https://api.whatsapp.com/send?text=' . rawurlencode( $share_text ),
		) );
	}

	public static function ajax_revoke_token() {
		self::nonce_check();
		$token_str = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
		$token_id  = (int) ( $_POST['token_id'] ?? 0 );

		if ( empty( $token_str ) && empty( $token_id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid token identifier.' ) );
		}

		$success = self::revoke_access_token( $token_id ?: $token_str );
		if ( ! $success ) {
			wp_send_json_error( array( 'message' => 'Unable to revoke VIP link.' ) );
		}

		wp_send_json_success( array( 'message' => 'VIP link has been revoked.' ) );
	}
}
