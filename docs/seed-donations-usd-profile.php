<?php
/**
 * One-time local development seed script.
 *
 * Inserts a placeholder `donations_usd` Paycenter profile into wp_options
 * so the USD currency selector on the donation form is enabled locally.
 *
 * On the production server, the real LIONSCLUBUSD credentials are already
 * stored; this script is only needed on fresh local DB imports.
 *
 * Usage (from the site root via PowerShell):
 *   & "C:\xampp\php\php.exe" -r "require 'wp-load.php'; require 'wp-content/plugins/lccl-donations-and-events/docs/seed-donations-usd-profile.php';"
 *
 * Run ONCE. Safe to run again — it only sets the profile if it is empty.
 */

defined( 'ABSPATH' ) || require dirname( __FILE__, 5 ) . '/wp-load.php';

$option_key = 'lccl_de_paycenter_profiles';
$profiles   = get_option( $option_key, array() );

if ( ! is_array( $profiles ) ) {
	$profiles = array();
}

if ( ! empty( $profiles['donations_usd']['client_id'] ) ) {
	echo "donations_usd profile already has credentials — nothing to do.\n";
	exit;
}

// Seed a placeholder profile. Replace these values with real ones from CBC Paycenter.
$profiles['donations_usd'] = array(
	'label'       => 'Donations (USD)',
	'enabled'     => 1,
	'endpoint'    => 'https://paycorp-cbc.prod.aws.paycorp.lk/rest/service/proxy/',
	'merchant_id' => 'LIONSCLUBUSD',
	'client_id'   => 'REPLACE_WITH_REAL_CLIENT_ID',
	'auth_token'  => 'REPLACE_WITH_REAL_AUTH_TOKEN',
	'hmac_secret' => '',
	'currency'    => 'USD',
);

update_option( $option_key, $profiles );
echo "donations_usd profile seeded. Remember to set real credentials in WP Admin -> LCCL Programs -> Payment Gateway.\n";
