<?php
/**
 * Plugin Name: RK AI Visualizer
 * Description: A definition-driven AI visualization engine for WordPress.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: RK
 * Text Domain: rk-ai-visualizer
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
define( 'RK_AIVIZ_VERSION', '0.1.0' );
define( 'RK_AIVIZ_FILE', __FILE__ );
define( 'RK_AIVIZ_DIR', plugin_dir_path( __FILE__ ) );
define( 'RK_AIVIZ_URL', plugin_dir_url( __FILE__ ) );
require_once RK_AIVIZ_DIR . 'includes/bootstrap.php';
add_action( 'plugins_loaded', array( '\\RK\\AIVisualizer\\Bootstrap', 'init' ) );
