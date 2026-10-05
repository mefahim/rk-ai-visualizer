<?php
namespace RK\AIVisualizer;
if ( ! defined( 'ABSPATH' ) ) { exit; }
use RK\AIVisualizer\Definitions\Registry;
use RK\AIVisualizer\Definitions\Flooring;
use RK\AIVisualizer\Definitions\Kitchen;
use RK\AIVisualizer\Rest\Routes;
use RK\AIVisualizer\Frontend\Renderer;
use RK\AIVisualizer\Frontend\Assets;
use RK\AIVisualizer\Admin\Settings;
require_once __DIR__ . '/core/errors.php';
require_once __DIR__ . '/definitions/definition.php';
require_once __DIR__ . '/definitions/registry.php';
require_once __DIR__ . '/definitions/flooring.php';
require_once __DIR__ . '/definitions/kitchen.php';
require_once __DIR__ . '/core/validation.php';
require_once __DIR__ . '/core/prompt.php';
require_once __DIR__ . '/core/quota.php';
require_once __DIR__ . '/core/storage.php';
require_once __DIR__ . '/core/leads.php';
require_once __DIR__ . '/providers/interface.php';
require_once __DIR__ . '/providers/http.php';
require_once __DIR__ . '/providers/mock.php';
require_once __DIR__ . '/providers/gemini.php';
require_once __DIR__ . '/providers/huggingface.php';
require_once __DIR__ . '/providers/custom.php';
require_once __DIR__ . '/providers/factory.php';
require_once __DIR__ . '/core/engine.php';
require_once __DIR__ . '/rest/routes.php';
require_once __DIR__ . '/frontend/renderer.php';
require_once __DIR__ . '/frontend/assets.php';
require_once __DIR__ . '/admin/settings.php';
require_once __DIR__ . '/admin/dashboard.php';
final class Bootstrap {
    public static function init() {
        $registry = Registry::instance();
        $registry->register( Flooring::definition() );
        $registry->register( Kitchen::definition() );
        add_action( 'rest_api_init', array( Routes::class, 'register' ) );
        add_shortcode( 'rk_ai_visualizer', array( Renderer::class, 'shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( Assets::class, 'register' ) );
        if ( is_admin() ) { Settings::init(); }
    }
}
