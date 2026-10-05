<?php
error_reporting( E_ALL );
$GLOBALS['test_admin_menus'] = array();
$GLOBALS['test_admin_submenus'] = array();
$GLOBALS['test_posts'] = array();
function admin_url( $path = '' ) { return 'https://wp.example.test/wp-admin/' . ltrim( $path, '/' ); }
function wp_nonce_url( $url, $action = '-1' ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . '_wpnonce=test-nonce'; }
function add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $callback = '', $icon_url = '', $position = null ) { $GLOBALS['test_admin_menus'][] = $menu_slug; return 'toplevel_page_' . $menu_slug; }
function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '' ) { $GLOBALS['test_admin_submenus'][] = $menu_slug; return $parent_slug . '_page_' . $menu_slug; }
function get_posts( $args = array() ) { return $GLOBALS['test_posts']; }
function get_permalink( $post ) { return isset( $post->url ) ? $post->url : ''; }
function size_format( $bytes, $decimals = 0 ) { return number_format( $bytes / 1024 / 1024, $decimals ) . ' MB'; }
function wp_date( $format, $timestamp = null ) { return gmdate( $format, $timestamp ); }
function wp_die( $message = '' ) { throw new RuntimeException( $message ); }
function check_admin_referer( $action = -1 ) { return true; }
function wp_safe_redirect( $location, $status = 302 ) { $GLOBALS['test_redirect'] = $location; return true; }
require __DIR__ . '/bootstrap.php';

$tests = 0;
$failed = 0;
function admin_test( $name, $callable ) {
    global $tests, $failed;
    $tests++;
    try { $callable(); echo "ok $tests - $name\n"; }
    catch ( Throwable $error ) { $failed++; echo "not ok $tests - $name: " . $error->getMessage() . "\n"; }
}
function admin_assert( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }
function admin_render( $callback ) { ob_start(); call_user_func( $callback ); return ob_get_clean(); }

$GLOBALS['wp_options']['rk_ai_visualizer_settings'] = array( 'enabled' => true, 'provider' => 'mock', 'free_count' => 3, 'bonus_count' => 1, 'cooldown_hours' => 24, 'ip_per_hour' => 10, 'timeout' => 120 );
\RK\AIVisualizer\Core\Leads::store( array( 'name' => 'Admin QA', 'email' => 'admin-qa@example.test', 'phone' => '555-555-5555' ), 'flooring' );
$GLOBALS['test_posts'] = array( (object) array( 'post_content' => '[rk_ai_visualizer visualizer="flooring"]', 'url' => 'https://site.example.test/flooring/' ) );

admin_test( 'plugin menu exposes Dashboard, Visualizers, Leads and Settings', function () {
    \RK\AIVisualizer\Admin\Settings::menu();
    admin_assert( false === strpos( file_get_contents( dirname( __DIR__ ) . '/includes/admin/settings.php' ), 'add_options_page' ), 'legacy Settings menu registration must be absent' );
    admin_assert( in_array( 'rk-ai-visualizer', $GLOBALS['test_admin_menus'], true ), 'missing top-level dashboard menu' );
    foreach ( array( 'rk-ai-visualizer', 'rk-ai-visualizers', 'rk-ai-visualizer-leads', 'rk-ai-visualizer-settings' ) as $page ) {
        admin_assert( in_array( $page, $GLOBALS['test_admin_submenus'], true ), 'missing submenu ' . $page );
    }
} );

admin_test( 'dashboard summarizes current global state and latest activity', function () {
    $html = admin_render( array( '\\RK\\AIVisualizer\\Admin\\Dashboard', 'render_dashboard' ) );
    foreach ( array( 'Active visualizers', 'Configured visualizers', 'Free generations', 'Leads captured', 'Mock', 'Latest leads', 'Admin QA' ) as $text ) {
        admin_assert( false !== strpos( $html, $text ), 'dashboard omitted ' . $text );
    }
} );

admin_test( 'visualizer catalog is data-driven, shows type, shortcode, and a detected preview', function () {
    $html = admin_render( array( '\\RK\\AIVisualizer\\Admin\\Dashboard', 'render_visualizers' ) );
    foreach ( array( 'Flooring Visualizer', 'Kitchen Visualizer', 'Built-in', '[rk_ai_visualizer visualizer="flooring"]', 'Preview', 'https://site.example.test/flooring/' ) as $text ) {
        admin_assert( false !== strpos( $html, $text ), 'catalog omitted ' . $text );
    }
} );

admin_test( 'visualizer detail routes through the catalog and keeps definition fields read-only', function () {
    $_GET['visualizer'] = 'kitchen';
    $_GET['tab'] = 'fields';
    $html = admin_render( array( '\\RK\\AIVisualizer\\Admin\\Dashboard', 'render_visualizers' ) );
    unset( $_GET['visualizer'], $_GET['tab'] );
    foreach ( array( 'Kitchen Visualizer', 'Overview', 'Appearance', 'Fields', 'Prompt', 'Usage', 'Advanced', 'Definition fields', 'builder is intentionally not part' ) as $text ) {
        admin_assert( false !== strpos( $html, $text ), 'detail screen omitted ' . $text );
    }
} );

admin_test( 'global settings page retains Settings API form and separates provider and quota sections', function () {
    $html = admin_render( array( '\\RK\\AIVisualizer\\Admin\\Dashboard', 'render_settings_page' ) );
    foreach ( array( 'GLOBAL SETTINGS', 'PROVIDER CREDENTIALS', 'GLOBAL QUOTAS', 'options.php', 'Enable all visualizers', 'Free generations', 'Saved secrets are never displayed' ) as $text ) {
        admin_assert( false !== strpos( $html, $text ), 'settings page omitted ' . $text );
    }
} );

admin_test( 'leads list displays source and uses an opaque nonce-protected deletion reference', function () {
    $html = admin_render( array( '\\RK\\AIVisualizer\\Admin\\Dashboard', 'render_leads' ) );
    foreach ( array( 'Admin QA', 'admin-qa@example.test', 'Flooring Visualizer', 'Created', 'admin-post.php?action=rk_aiviz_delete_lead', '_wpnonce=' ) as $text ) {
        admin_assert( false !== strpos( $html, $text ), 'leads page omitted ' . $text );
    }
    admin_assert( false === strpos( $html, 'email=admin-qa@example.test' ), 'email must not be exposed in the delete URL' );
} );

admin_test( 'built-in metadata is generic and admin styling includes compact mobile navigation', function () {
    admin_assert( 'built-in' === \RK\AIVisualizer\Definitions\Registry::instance()->get( 'flooring' )->get( 'type' ), 'Flooring type metadata missing' );
    admin_assert( 'built-in' === \RK\AIVisualizer\Definitions\Registry::instance()->get( 'kitchen' )->get( 'type' ), 'Kitchen type metadata missing' );
    $css = file_get_contents( dirname( __DIR__ ) . '/assets/css/admin.css' );
    admin_assert( false !== strpos( $css, '@media (max-width: 782px)' ), 'responsive admin navigation breakpoint missing' );
    admin_assert( false !== strpos( $css, 'overflow-x: auto' ), 'compact mobile navigation behavior missing' );
} );

echo "\n$tests admin dashboard tests, $failed failures\n";
exit( $failed ? 1 : 0 );
