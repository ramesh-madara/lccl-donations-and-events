<?php
/**
 * Temporary Fundraiser Gala Database Seeder.
 *
 * Automatically seeds the database from docs/fundraiser_gala_seed_records.sql
 * on first mount / plugin load.
 *
 * NOTE: This seeder is temporary and can be safely deleted later.
 * When deleting: remove this file and the 2 lines referencing it in lccl-donations-and-events.php.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

class LCCL_DE_Gala_Seeder {

	/**
	 * WP Option name storing the seed completion timestamp.
	 */
	const OPTION_NAME = 'lccl_de_gala_seed_imported';

	/**
	 * Mount hook into plugins_loaded.
	 */
	public static function init() {
		self::maybe_seed();

		// Optional manual reseed trigger for administrators: ?lccl_reseed_gala=1&_wpnonce=...
		if ( is_admin() && isset( $_GET['lccl_reseed_gala'] ) && current_user_can( 'manage_options' ) ) {
			if ( check_admin_referer( 'lccl_reseed_gala_action', '_lccl_nonce' ) ) {
				$success = self::run_seed( true );
				add_action( 'admin_notices', function () use ( $success ) {
					if ( $success ) {
						echo '<div class="notice notice-success is-dismissible"><p><strong>LCCL Seeder:</strong> Fundraiser Gala 2026 dummy records seeded successfully!</p></div>';
					} else {
						echo '<div class="notice notice-error is-dismissible"><p><strong>LCCL Seeder:</strong> Failed to seed Fundraiser Gala records. Check error logs.</p></div>';
					}
				} );
			}
		}
	}

	/**
	 * Runs the seed operation if not already imported.
	 *
	 * @return bool True if seeded, false if already seeded or error.
	 */
	public static function maybe_seed() {
		if ( get_option( self::OPTION_NAME ) ) {
			return false; // Already seeded
		}

		return self::run_seed( false );
	}

	/**
	 * Reads and executes docs/fundraiser_gala_seed_records.sql against the active WordPress DB.
	 *
	 * @param bool $force If true, executes even if previously marked as seeded.
	 * @return bool True on success.
	 */
	public static function run_seed( $force = false ) {
		global $wpdb;

		if ( ! $force && get_option( self::OPTION_NAME ) ) {
			return false;
		}

		$sql_file = LCCL_DE_PATH . 'docs/fundraiser_gala_seed_records.sql';
		if ( ! file_exists( $sql_file ) ) {
			error_log( 'LCCL_DE_Gala_Seeder: SQL file not found at ' . $sql_file );
			return false;
		}

		$raw_sql = file_get_contents( $sql_file );
		if ( empty( $raw_sql ) ) {
			error_log( 'LCCL_DE_Gala_Seeder: SQL file is empty at ' . $sql_file );
			return false;
		}

		// Dynamically replace default 'wp_' table prefix with current database prefix.
		if ( 'wp_' !== $wpdb->prefix ) {
			$raw_sql = str_replace( '`wp_lccl_de_fr_', '`' . $wpdb->prefix . 'lccl_de_fr_', $raw_sql );
		}

		$statements = self::split_sql_statements( $raw_sql );
		if ( empty( $statements ) ) {
			error_log( 'LCCL_DE_Gala_Seeder: No valid SQL statements parsed from file.' );
			return false;
		}

		$had_error = false;
		foreach ( $statements as $query ) {
			$res = $wpdb->query( $query );
			if ( false === $res ) {
				error_log( 'LCCL_DE_Gala_Seeder query error: ' . $wpdb->last_error . ' | Query: ' . substr( $query, 0, 150 ) );
				$had_error = true;
			}
		}

		if ( ! $had_error ) {
			update_option( self::OPTION_NAME, current_time( 'mysql' ) );
			return true;
		}

		return false;
	}

	/**
	 * Robustly splits a multi-statement SQL script into individual queries,
	 * respecting single/double quotes, escape sequences, and SQL comments.
	 *
	 * @param string $sql Raw SQL string.
	 * @return array List of individual executable SQL query strings.
	 */
	public static function split_sql_statements( $sql ) {
		$queries       = array();
		$strlen        = strlen( $sql );
		$current_query = '';
		$in_string     = false;
		$string_char   = '';
		$in_comment    = false;
		$comment_type  = '';

		for ( $i = 0; $i < $strlen; $i++ ) {
			$char = $sql[ $i ];
			$next = ( $i + 1 < $strlen ) ? $sql[ $i + 1 ] : '';

			// Handling string literals
			if ( $in_string ) {
				if ( '\\' === $char ) {
					$current_query .= $char . $next;
					$i++;
					continue;
				}
				if ( $char === $string_char ) {
					if ( "'" === $string_char && "'" === $next ) {
						$current_query .= "''";
						$i++;
						continue;
					}
					$in_string = false;
				}
				$current_query .= $char;
				continue;
			}

			// Handling comments
			if ( $in_comment ) {
				if ( '--' === $comment_type || '#' === $comment_type ) {
					if ( "\n" === $char || "\r" === $char ) {
						$in_comment = false;
					}
				} elseif ( '/*' === $comment_type ) {
					if ( '*' === $char && '/' === $next ) {
						$in_comment = false;
						$i++;
					}
				}
				continue;
			}

			// Comment starters
			if ( '-' === $char && '-' === $next ) {
				$in_comment   = true;
				$comment_type = '--';
				$i++;
				continue;
			}
			if ( '#' === $char ) {
				$in_comment   = true;
				$comment_type = '#';
				continue;
			}
			if ( '/' === $char && '*' === $next ) {
				$in_comment   = true;
				$comment_type = '/*';
				$i++;
				continue;
			}

			// String literal starters
			if ( "'" === $char || '"' === $char ) {
				$in_string     = true;
				$string_char   = $char;
				$current_query .= $char;
				continue;
			}

			// Statement terminator
			if ( ';' === $char ) {
				$trimmed = trim( $current_query );
				if ( '' !== $trimmed ) {
					$queries[] = $trimmed;
				}
				$current_query = '';
				continue;
			}

			$current_query .= $char;
		}

		$trimmed = trim( $current_query );
		if ( '' !== $trimmed ) {
			$queries[] = $trimmed;
		}

		return $queries;
	}
}
