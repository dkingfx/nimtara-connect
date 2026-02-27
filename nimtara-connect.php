<?php
/**
 * Plugin Name: Nimtara Connect
 * Plugin URI:  https://github.com/dkingfx/nimtara-connect
 * Description: Connect your WordPress site to the Nimtara AI content platform. Receive and publish AI-generated articles via a secure REST API.
 * Version:     1.0.0
 * Author:      Nimtara
 * License:     GPL-2.0+
 * Text Domain: nimtara-connect
 */

defined( 'ABSPATH' ) || exit;

define( 'NIMTARA_CONNECT_VERSION', '1.0.0' );
define( 'NIMTARA_CONNECT_FILE', __FILE__ );
define( 'NIMTARA_CONNECT_DIR', plugin_dir_path( __FILE__ ) );

require_once NIMTARA_CONNECT_DIR . 'includes/class-api.php';
require_once NIMTARA_CONNECT_DIR . 'includes/class-publisher.php';
require_once NIMTARA_CONNECT_DIR . 'includes/class-media.php';
require_once NIMTARA_CONNECT_DIR . 'includes/class-admin.php';

function nimtara_connect_init() {
    $api = new Nimtara_Connect_API();
    $api->register_routes();

    if ( is_admin() ) {
        $admin = new Nimtara_Connect_Admin();
        $admin->init();
    }
}
add_action( 'init', 'nimtara_connect_init' );

register_activation_hook( __FILE__, 'nimtara_connect_activate' );
function nimtara_connect_activate() {
    if ( ! get_option( 'nimtara_connect_api_key' ) ) {
        update_option( 'nimtara_connect_api_key', wp_generate_password( 40, false ) );
    }
}
