<?php
namespace RK\AIVisualizer\Frontend;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Assets {
    public static function register() { wp_register_script('rk-ai-visualizer',RK_AIVIZ_URL.'assets/js/visualizer.js',array(),RK_AIVIZ_VERSION,true); wp_register_style('rk-ai-visualizer',RK_AIVIZ_URL.'assets/css/visualizer.css',array(),RK_AIVIZ_VERSION); }
}
