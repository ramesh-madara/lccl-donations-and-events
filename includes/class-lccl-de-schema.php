<?php
/**
 * Custom table installer.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades plugin tables.
 */
class LCCL_DE_Schema {

	/**
	 * Current schema version. Bump this when the table definition changes.
	 */
	const VERSION = 7;

	/**
	 * Option that stores the installed schema version.
	 */
	const OPTION = 'lccl_de_schema_version';

	/**
	 * Per-request map of lowercase wanted name => actual MySQL name.
	 *
	 * @var array<string,string>
	 */
	private static $table_names = array();

	/**
	 * Blood donor registrations table, including the WP prefix.
	 *
	 * Uses the name MySQL actually stored. Windows phpMyAdmin lowercases
	 * identifiers; Linux does not. Inserts must use the real case.
	 *
	 * @return string
	 */
	public static function blood_donors_table() {
		global $wpdb;

		$wanted   = $wpdb->prefix . 'lccl_de_blood_donors';
		$existing = self::existing_table_name( $wanted );

		return $existing ? $existing : $wanted;
	}

	/**
	 * Join Our Projects registrations table, including the WP prefix.
	 *
	 * @return string
	 */
	public static function project_joins_table() {
		global $wpdb;

		$wanted   = $wpdb->prefix . 'lccl_de_project_joins';
		$existing = self::existing_table_name( $wanted );

		return $existing ? $existing : $wanted;
	}

	/**
	 * Create tables if they are missing or the schema version is behind.
	 *
	 * Runs on activation and on plugins_loaded so a file-copy deploy or a
	 * dropped table still gets a working schema without a re-activate.
	 */
	public static function maybe_install() {
		$installed = (int) get_option( self::OPTION, 0 );

		if ( $installed >= self::VERSION && self::table_exists() && self::project_joins_exist() && self::spectacles_exist() ) {
			return;
		}

		self::install();
	}

	/**
	 * Whether the blood donors table is present, ignoring identifier case.
	 *
	 * @return bool
	 */
	public static function table_exists() {
		global $wpdb;

		return '' !== self::existing_table_name( $wpdb->prefix . 'lccl_de_blood_donors' );
	}

	/**
	 * Whether the Join Our Projects table is present, ignoring identifier case.
	 *
	 * @return bool
	 */
	public static function project_joins_exist() {
		global $wpdb;

		return '' !== self::existing_table_name( $wpdb->prefix . 'lccl_de_project_joins' );
	}

	/**
	 * Free Spectacles registrations table, including the WP prefix.
	 *
	 * @return string
	 */
	public static function spectacles_table() {
		global $wpdb;

		$wanted   = $wpdb->prefix . 'lccl_de_spectacles';
		$existing = self::existing_table_name( $wanted );

		return $existing ? $existing : $wanted;
	}

	/**
	 * Whether the Free Spectacles table is present, ignoring identifier case.
	 *
	 * @return bool
	 */
	public static function spectacles_exist() {
		global $wpdb;

		return '' !== self::existing_table_name( $wpdb->prefix . 'lccl_de_spectacles' );
	}

	/**
	 * Actual table name as stored by MySQL, or empty if it does not exist.
	 *
	 * @param string $wanted Prefixed table name from $wpdb.
	 * @return string
	 */
	private static function existing_table_name( $wanted ) {
		global $wpdb;

		$key = strtolower( $wanted );
		if ( array_key_exists( $key, self::$table_names ) ) {
			return self::$table_names[ $key ];
		}

		$found = '';
		foreach ( (array) $wpdb->get_col( 'SHOW TABLES' ) as $name ) {
			if ( 0 === strcasecmp( (string) $name, $wanted ) ) {
				$found = (string) $name;
				break;
			}
		}

		self::$table_names[ $key ] = $found;
		return $found;
	}

	/**
	 * Create or update tables with dbDelta.
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::blood_donors_table();
		$charset = $wpdb->get_charset_collate();

		/*
		 * dbDelta is picky: two spaces after PRIMARY KEY, and indexes
		 * must be named. notify_campaigns is a real boolean (0/1), not
		 * a string, so campaign mailing queries stay cheap.
		 */
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			first_name varchar(100) NOT NULL,
			last_name varchar(100) NOT NULL,
			address varchar(255) NOT NULL,
			city varchar(100) NOT NULL,
			postal_code varchar(20) DEFAULT NULL,
			email varchar(191) DEFAULT NULL,
			phone varchar(30) NOT NULL,
			district varchar(64) NOT NULL,
			blood_bank varchar(64) NOT NULL,
			blood_bank_label varchar(255) DEFAULT NULL,
			donation_preference varchar(32) DEFAULT NULL,
			donated_before varchar(16) DEFAULT NULL,
			contact_method varchar(16) NOT NULL,
			notify_campaigns tinyint(1) NOT NULL DEFAULT 0,
			consent tinyint(1) NOT NULL DEFAULT 0,
			ip_address varchar(45) DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime DEFAULT NULL,
			updated_by bigint(20) unsigned DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY district (district),
			KEY blood_bank (blood_bank),
			KEY notify_campaigns (notify_campaigns),
			KEY created_at (created_at),
			KEY email (email)
		) {$charset};";

		dbDelta( $sql );

		$joins = self::project_joins_table();
		$join_sql = "CREATE TABLE {$joins} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			full_name varchar(191) NOT NULL,
			first_name varchar(100) NOT NULL DEFAULT '',
			last_name varchar(100) NOT NULL DEFAULT '',
			email varchar(191) NOT NULL,
			phone varchar(30) NOT NULL,
			address varchar(255) DEFAULT NULL,
			city varchar(100) DEFAULT NULL,
			postal_code varchar(20) DEFAULT NULL,
			occupation varchar(191) DEFAULT NULL,
			organisation varchar(191) DEFAULT NULL,
			support_ways text,
			support_ways_other varchar(255) DEFAULT NULL,
			volunteer_areas text,
			skills text,
			availability text,
			financial_support text,
			contribution_amount varchar(64) DEFAULT NULL,
			interest_areas text,
			interest_areas_other varchar(255) DEFAULT NULL,
			project_types text,
			specific_idea text,
			registering_as varchar(64) DEFAULT NULL,
			company_name varchar(191) DEFAULT NULL,
			designation varchar(191) DEFAULT NULL,
			company_support text,
			message text,
			consent tinyint(1) NOT NULL DEFAULT 0,
			ip_address varchar(45) DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime DEFAULT NULL,
			updated_by bigint(20) unsigned DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY email (email),
			KEY phone (phone),
			KEY created_at (created_at)
		) {$charset};";

		dbDelta( $join_sql );

		$spectacles = self::spectacles_table();
		$spectacles_sql = "CREATE TABLE {$spectacles} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			child_first_name varchar(100) NOT NULL,
			child_last_name varchar(100) NOT NULL,
			dob date NOT NULL,
			age varchar(8) DEFAULT NULL,
			gender varchar(16) NOT NULL,
			grade varchar(32) NOT NULL,
			school_name varchar(191) NOT NULL,
			school_area varchar(191) NOT NULL,
			district varchar(64) NOT NULL,
			guardian_name varchar(191) NOT NULL,
			phone varchar(30) NOT NULL,
			city varchar(100) NOT NULL,
			relationship varchar(32) NOT NULL,
			email varchar(191) DEFAULT NULL,
			eye_exam varchar(16) NOT NULL,
			wear_spectacles varchar(16) NOT NULL,
			difficulty_seeing varchar(16) NOT NULL,
			last_eye_exam varchar(32) DEFAULT NULL,
			eye_condition varchar(16) NOT NULL,
			eye_condition_details text,
			vision_difficulties text,
			vision_other varchar(255) DEFAULT NULL,
			school_letter varchar(32) NOT NULL,
			letter_file varchar(255) DEFAULT NULL,
			letter_file_name varchar(255) DEFAULT NULL,
			letter_mime varchar(100) DEFAULT NULL,
			consent tinyint(1) NOT NULL DEFAULT 0,
			ip_address varchar(45) DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime DEFAULT NULL,
			updated_by bigint(20) unsigned DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY district (district),
			KEY grade (grade),
			KEY gender (gender),
			KEY school_letter (school_letter),
			KEY created_at (created_at),
			KEY phone (phone),
			KEY email (email)
		) {$charset};";

		dbDelta( $spectacles_sql );
		self::$table_names = array();
		update_option( self::OPTION, self::VERSION );
	}
}
