<?php
require dirname( __FILE__, 5 ) . '/wp-load.php';

$raw = get_option( 'lccl_de_paycenter_profiles', array() );

echo "=== Raw DB entry for donations_usd ===" . PHP_EOL;
if ( isset( $raw['donations_usd'] ) ) {
	$p = $raw['donations_usd'];
	echo 'label:           ' . ( $p['label'] ?? 'N/A' ) . PHP_EOL;
	echo 'enabled:         ' . ( $p['enabled'] ?? 'N/A' ) . PHP_EOL;
	echo 'endpoint:        ' . ( $p['endpoint'] ?? 'N/A' ) . PHP_EOL;
	echo 'merchant_id:     ' . ( $p['merchant_id'] ?? 'N/A' ) . PHP_EOL;
	echo 'client_id:       ' . ( $p['client_id'] ?? 'N/A' ) . PHP_EOL;
	echo 'currency:        ' . ( $p['currency'] ?? 'N/A' ) . PHP_EOL;
	echo 'auth_token raw:  ' . ( isset( $p['auth_token'] ) ? substr( $p['auth_token'], 0, 30 ) . '...' : 'EMPTY' ) . PHP_EOL;
	echo 'hmac_secret set: ' . ( ! empty( $p['hmac_secret'] ) ? 'YES' : 'NO' ) . PHP_EOL;
} else {
	echo 'donations_usd profile NOT FOUND in lccl_de_paycenter_profiles' . PHP_EOL;
}

echo PHP_EOL . "=== After get_paycenter() decryption ===" . PHP_EOL;
$cfg = LCCL_DE_Settings::get_paycenter( LCCL_DE_Settings::PROFILE_DONATIONS_USD );
echo 'label:       ' . $cfg['label'] . PHP_EOL;
echo 'enabled:     ' . $cfg['enabled'] . PHP_EOL;
echo 'endpoint:    ' . $cfg['endpoint'] . PHP_EOL;
echo 'merchant_id: ' . $cfg['merchant_id'] . PHP_EOL;
echo 'client_id:   ' . $cfg['client_id'] . PHP_EOL;
echo 'currency:    ' . $cfg['currency'] . PHP_EOL;
echo 'auth_token decrypted (first 20): ' . substr( $cfg['auth_token'], 0, 20 ) . '...' . PHP_EOL;

echo PHP_EOL . "=== is_configured() ===" . PHP_EOL;
echo 'LKR (donations):     ' . ( LCCL_DE_Paycenter_Client::is_configured( 'donations' ) ? 'YES' : 'NO' ) . PHP_EOL;
echo 'USD (donations_usd): ' . ( LCCL_DE_Paycenter_Client::is_configured( 'donations_usd' ) ? 'YES' : 'NO' ) . PHP_EOL;
