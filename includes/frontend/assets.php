<?php
namespace RK\AIVisualizer\Frontend;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Assets {
    private static function version( $relative_path ) {
        $path = RK_AIVIZ_DIR . $relative_path;
        $modified = is_file( $path ) ? filemtime( $path ) : false;
        return false === $modified ? RK_AIVIZ_VERSION : RK_AIVIZ_VERSION . '.' . $modified;
    }

    public static function register() {
        wp_register_script( 'rk-ai-visualizer', RK_AIVIZ_URL . 'assets/js/visualizer.js', array(), self::version( 'assets/js/visualizer.js' ), true );
        wp_register_style( 'rk-ai-visualizer', RK_AIVIZ_URL . 'assets/css/visualizer.css', array(), self::version( 'assets/css/visualizer.css' ) );
    }
}
