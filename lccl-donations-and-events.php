<?php
/**
 * Plugin Name:       LCCL Donations and Events
 * Description:       Donation and event management for the Lions Club of Colombo Leads.
 * Version:           0.8.69
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Ramesh Madara
 * Author URI:        https://www.linkedin.com/in/ramesh-madara-76b019261
 * License:           GPL-2.0-or-later
 * Text Domain:       lccl-de
 *
 * @package LCCL_Donations_And_Events
 */
// ramesh 3
defined( 'ABSPATH' ) || exit;

define( 'LCCL_DE_VERSION', '0.8.69' );
define( 'LCCL_DE_FILE', __FILE__ );
define( 'LCCL_DE_PATH', plugin_dir_path( __FILE__ ) );
define( 'LCCL_DE_URL', plugin_dir_url( __FILE__ ) );

require_once LCCL_DE_PATH . 'includes/class-lccl-de-schema.php';
require_once LCCL_DE_PATH . 'includes/class-lccl-de-roles.php';
require_once LCCL_DE_PATH . 'includes/class-lccl-de-access.php';
require_once LCCL_DE_PATH . 'includes/class-lccl-de-admin-users.php';
require_once LCCL_DE_PATH . 'includes/class-lccl-de-admin-programs.php';
require_once LCCL_DE_PATH . 'includes/class-lccl-de-settings.php';
require_once LCCL_DE_PATH . 'includes/class-lccl-de-shortcodes.php';
require_once LCCL_DE_PATH . 'includes/class-lccl-de-blood-donor-form.php';
require_once LCCL_DE_PATH . 'includes/class-lccl-de-blood-donor-submissions.php';
require_once LCCL_DE_PATH . 'includes/class-lccl-de-notify.php';
require_once LCCL_DE_PATH . 'includes/class-lccl-de-dashboard.php';

register_activation_hook( __FILE__, array( 'LCCL_DE_Schema', 'install' ) );
register_activation_hook( __FILE__, array( 'LCCL_DE_Roles', 'install' ) );

add_action( 'plugins_loaded', array( 'LCCL_DE_Schema', 'maybe_install' ) );
add_action( 'init', array( 'LCCL_DE_Roles', 'maybe_install' ), 5 );
add_action( 'init', array( 'LCCL_DE_Access', 'init' ) );
add_action( 'init', array( 'LCCL_DE_Admin_Users', 'init' ) );
add_action( 'init', array( 'LCCL_DE_Admin_Programs', 'init' ) );
add_action( 'init', array( 'LCCL_DE_Settings', 'init' ) );
add_action( 'init', array( 'LCCL_DE_Shortcodes', 'init' ) );
add_action( 'init', array( 'LCCL_DE_Blood_Donor_Form', 'init' ) );
add_action( 'init', array( 'LCCL_DE_Blood_Donor_Submissions', 'init' ) );
add_action( 'init', array( 'LCCL_DE_Dashboard', 'init' ) );
