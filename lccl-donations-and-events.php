<?php
/**
 * Plugin Name:       LCCL Donations and Events
 * Plugin URI:        https://www.colomboleads.org
 * Description:       Donation and event management for the Lions Club of Colombo Leads.
 * Version:           0.5.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Lions Club of Colombo Leads
 * License:           GPL-2.0-or-later
 * Text Domain:       lccl-de
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

define( 'LCCL_DE_VERSION', '0.5.0' );
define( 'LCCL_DE_FILE', __FILE__ );
define( 'LCCL_DE_PATH', plugin_dir_path( __FILE__ ) );
define( 'LCCL_DE_URL', plugin_dir_url( __FILE__ ) );

require_once LCCL_DE_PATH . 'includes/class-lccl-de-shortcodes.php';
require_once LCCL_DE_PATH . 'includes/class-lccl-de-blood-donor-form.php';

add_action( 'init', array( 'LCCL_DE_Shortcodes', 'init' ) );
add_action( 'init', array( 'LCCL_DE_Blood_Donor_Form', 'init' ) );
