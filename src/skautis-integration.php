<?php
/**
 * Plugin Name:       skautIS integration
 * Plugin URI:        https://github.com/skaut/skautis-integration
 * Description:       Integrace WordPressu se skautISem
 * Version:           1.1.25
 * Author:            Junák - český skaut
 * Author URI:        https://github.com/skaut
 * Text Domain:       skautis-integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SKAUTISINTEGRATION_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'SKAUTISINTEGRATION_PATH', plugin_dir_path( __FILE__ ) );
define( 'SKAUTISINTEGRATION_URL', plugin_dir_url( __FILE__ ) );
define( 'SKAUTISINTEGRATION_NAME', 'skautis-integration' );
define( 'SKAUTISINTEGRATION_VERSION', '1.1.25' );

require __DIR__ . '/class-skautis-integration.php';

global $skautisIntegration;
$skautisIntegration = new Skautis_Integration();
