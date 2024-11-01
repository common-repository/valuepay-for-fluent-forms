<?php
/**
 * Plugin Name:       ValuePay for Fluent Forms
 * Description:       Accept payment on Fluent Forms using ValuePay.
 * Version:           1.0.3
 * Requires at least: 4.6
 * Requires PHP:      7.0
 * Author:            Valuefy Solutions Sdn Bhd
 * Author URI:        https://valuepay.my/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( !defined( 'ABSPATH' ) ) exit;

if ( class_exists( 'Valuepay_Fluentform' ) ) return;

define( 'VALUEPAY_FLUENTFORM_FILE', __FILE__ );
define( 'VALUEPAY_FLUENTFORM_URL', plugin_dir_url( VALUEPAY_FLUENTFORM_FILE ) );
define( 'VALUEPAY_FLUENTFORM_PATH', plugin_dir_path( VALUEPAY_FLUENTFORM_FILE ) );
define( 'VALUEPAY_FLUENTFORM_BASENAME', plugin_basename( VALUEPAY_FLUENTFORM_FILE ) );
define( 'VALUEPAY_FLUENTFORM_VERSION', '1.0.3' );

// Plugin core class
require( VALUEPAY_FLUENTFORM_PATH . 'includes/class-valuepay-fluentform.php' );
