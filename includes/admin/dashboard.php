<?php
namespace RK\AIVisualizer\Admin;

use RK\AIVisualizer\Core\Leads;
use RK\AIVisualizer\Definitions\Registry;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Application-style administration screens for RK AI Visualizer. */
final class Dashboard {
    const PAGE = 'rk-ai-visualizer';
    const VISUALIZERS_PAGE = 'rk-ai-visualizers';
    const LEADS_PAGE = 'rk-ai-visualizer-leads';
    const SETTINGS_PAGE = 'rk-ai-visualizer-settings';
    const DELETE_ACTION = 'rk_aiviz_delete_lead';

    public static function menu() {
        add_menu_page( 'RK AI Visualizer', 'RK AI Visualizer', 'manage_options', self::PAGE, array( __CLASS__, 'render_dashboard' ), 'dashicons-format-image', 58 );
        add_submenu_page( self::PAGE, 'Dashboard', 'Dashboard', 'manage_options', self::PAGE, array( __CLASS__, 'render_dashboard' ) );
        add_submenu_page( self::PAGE, 'Visualizers', 'Visualizers', 'manage_options', self::VISUALIZERS_PAGE, array( __CLASS__, 'render_visualizers' ) );
        add_submenu_page( self::PAGE, 'Leads', 'Leads', 'manage_options', self::LEADS_PAGE, array( __CLASS__, 'render_leads' ) );
        add_submenu_page( self::PAGE, 'Settings', 'Settings', 'manage_options', self::SETTINGS_PAGE, array( __CLASS__, 'render_settings_page' ) );
    }

    public static function assets( $hook = '' ) {
        if ( false === strpos( (string) $hook, 'rk-ai-visualizer' ) ) { return; }
        $base = defined( 'RK_AIVIZ_URL' ) ? RK_AIVIZ_URL : '';
        $version = defined( 'RK_AIVIZ_VERSION' ) ? RK_AIVIZ_VERSION : '1.0.0';
        wp_enqueue_style( 'rk-ai-visualizer-admin', $base . 'assets/css/admin.css', array(), $version );
    }

    public static function render_dashboard() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $definitions = Registry::instance()->all();
        $settings = self::settings();
        $leads = Leads::all();
        $enabled = ! empty( $settings['enabled'] );
        $active_count = $enabled ? count( $definitions ) : 0;

        self::start( 'dashboard', 'Dashboard', 'A clear overview of your visualizer setup.' );
        echo '<section class="rkaiviz-stat-grid" aria-label="Visualizer summary">';
        self::stat( 'Active visualizers', (string) $active_count, $enabled ? 'Available to visitors' : 'Paused by global settings', $enabled ? 'is-positive' : 'is-muted' );
        self::stat( 'Configured visualizers', (string) count( $definitions ), 'Built-in and registered definitions', '' );
        self::stat( 'Free generations', (string) (int) $settings['free_count'], 'Per visitor · global setting', '' );
        self::stat( 'Leads captured', (string) count( $leads ), 'Stored in the existing lead list', '' );
        echo '</section>';

        echo '<section class="rkaiviz-content-grid">';
        echo '<div class="rkaiviz-panel"><div class="rkaiviz-panel-heading"><div><p class="rkaiviz-eyebrow">CURRENT CONFIGURATION</p><h2>System status</h2></div><span class="rkaiviz-status ' . ( $enabled ? 'is-positive' : 'is-muted' ) . '">' . ( $enabled ? 'Enabled' : 'Paused' ) . '</span></div>';
        echo '<dl class="rkaiviz-facts"><div><dt>Provider</dt><dd>' . esc_html( self::provider_label( $settings['provider'] ) ) . '</dd></div><div><dt>Availability</dt><dd>' . ( $enabled ? 'The plugin-wide switch is on.' : 'Turn on the global switch to make visualizers available.' ) . '</dd></div><div><dt>Quota scope</dt><dd>Generation limits are configured globally; definitions may also declare defaults.</dd></div></dl>';
        echo '<a class="rkaiviz-button rkaiviz-button-secondary" href="' . esc_url( self::url( self::SETTINGS_PAGE ) ) . '">Open global settings</a></div>';

        echo '<div class="rkaiviz-panel"><div class="rkaiviz-panel-heading"><div><p class="rkaiviz-eyebrow">QUICK ACTIONS</p><h2>Keep moving</h2></div></div><div class="rkaiviz-quick-actions">';
        self::quick_action( '01', 'Manage visualizers', 'Review definitions and shortcodes.', self::url( self::VISUALIZERS_PAGE ) );
        self::quick_action( '02', 'View leads', 'Open the existing contact records.', self::url( self::LEADS_PAGE ) );
        self::quick_action( '03', 'Configure the plugin', 'Provider credentials and global limits.', self::url( self::SETTINGS_PAGE ) );
        echo '</div></div></section>';

        echo '<section class="rkaiviz-panel rkaiviz-recent"><div class="rkaiviz-panel-heading"><div><p class="rkaiviz-eyebrow">RECENT ACTIVITY</p><h2>Latest leads</h2></div><a class="rkaiviz-text-link" href="' . esc_url( self::url( self::LEADS_PAGE ) ) . '">View all leads</a></div>';
        self::render_lead_table( array_slice( $leads, 0, 5 ), false );
        echo '</section>';
        self::end();
    }

    public static function render_visualizers() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        if ( isset( $_GET['visualizer'] ) ) { self::render_visualizer(); return; }
        $definitions = Registry::instance()->all();
        $enabled = ! empty( self::settings()['enabled'] );
        self::start( 'visualizers', 'Visualizers', 'Review each registered visualizer and how it is used on your site.' );
        echo '<div class="rkaiviz-notice"><strong>Availability is controlled globally.</strong> This plugin currently has one shared enable switch. Individual on/off controls are not available without changing generation behavior.</div>';
        echo '<div class="rkaiviz-visualizer-grid">';
        foreach ( $definitions as $definition ) {
            $slug = $definition->get( 'slug' );
            $detail_url = self::url( self::VISUALIZERS_PAGE, array( 'visualizer' => $slug ) );
            $preview_url = self::preview_url( $slug );
            $type = 'built-in' === $definition->get( 'type', 'custom' ) ? 'Built-in' : 'Custom';
            echo '<article class="rkaiviz-panel rkaiviz-visualizer-card"><div class="rkaiviz-card-top"><span class="rkaiviz-type">' . esc_html( $type ) . '</span><span class="rkaiviz-status ' . ( $enabled ? 'is-positive' : 'is-muted' ) . '">' . ( $enabled ? 'Active' : 'Paused' ) . '</span></div>';
            echo '<h2>' . esc_html( $definition->get( 'name' ) ) . '</h2><p class="rkaiviz-slug">/' . esc_html( $slug ) . '</p>';
            echo '<div class="rkaiviz-shortcode"><span>SHORTCODE</span><code>[rk_ai_visualizer visualizer="' . esc_html( $slug ) . '"]</code></div>';
            echo '<div class="rkaiviz-card-actions"><a class="rkaiviz-button rkaiviz-button-primary" href="' . esc_url( $detail_url ) . '">Manage</a>';
            if ( $preview_url ) {
                echo '<a class="rkaiviz-button rkaiviz-button-secondary" href="' . esc_url( $preview_url ) . '" target="_blank" rel="noopener noreferrer">Preview</a>';
            } else {
                echo '<span class="rkaiviz-button rkaiviz-button-disabled" aria-disabled="true" title="Add this shortcode to a published page to enable preview">Preview unavailable</span>';
            }
            echo '</div><p class="rkaiviz-card-footnote">Status follows the shared global enable setting.</p></article>';
        }
        if ( ! $definitions ) { echo '<div class="rkaiviz-empty">No visualizer definitions are registered yet.</div>'; }
        echo '</div>';
        self::end();
    }

    public static function render_visualizer() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $slug = isset( $_GET['visualizer'] ) ? sanitize_key( $_GET['visualizer'] ) : '';
        $definition = Registry::instance()->get( $slug );
        if ( ! $definition ) {
            self::start( 'visualizers', 'Visualizer not found', 'Choose a registered visualizer from the catalog.' );
            echo '<div class="rkaiviz-empty"><a href="' . esc_url( self::url( self::VISUALIZERS_PAGE ) ) . '">Return to Visualizers</a></div>';
            self::end();
            return;
        }
        $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'overview';
        $tabs = array( 'overview' => 'Overview', 'appearance' => 'Appearance', 'fields' => 'Fields', 'prompt' => 'Prompt', 'usage' => 'Usage', 'advanced' => 'Advanced' );
        if ( ! isset( $tabs[ $tab ] ) ) { $tab = 'overview'; }
        self::start( 'visualizers', $definition->get( 'name' ), 'Read-only management details for this registered definition.' );
        echo '<nav class="rkaiviz-tabs" aria-label="Visualizer management sections">';
        foreach ( $tabs as $tab_id => $label ) {
            echo '<a class="' . ( $tab === $tab_id ? 'is-current' : '' ) . '" ' . ( $tab === $tab_id ? 'aria-current="page"' : '' ) . ' href="' . esc_url( self::url( self::VISUALIZERS_PAGE, array( 'visualizer' => $slug, 'tab' => $tab_id ) ) ) . '">' . esc_html( $label ) . '</a>';
        }
        echo '</nav><section class="rkaiviz-panel rkaiviz-detail">';
        self::render_detail_tab( $definition, $tab );
        echo '</section>';
        self::end();
    }

    public static function render_leads() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $leads = Leads::all();
        $filter = isset( $_GET['visualizer'] ) ? sanitize_key( $_GET['visualizer'] ) : '';
        if ( '' !== $filter ) {
            $leads = array_values( array_filter( $leads, function ( $lead ) use ( $filter ) { return isset( $lead['visualizer'] ) && $filter === sanitize_key( $lead['visualizer'] ); } ) );
        }
        self::start( 'leads', 'Leads', 'Contact records captured by your existing visualizer lead flow.' );
        if ( isset( $_GET['deleted'] ) && '1' === (string) $_GET['deleted'] ) { echo '<div class="rkaiviz-notice is-success" role="status">Lead deleted.</div>'; }
        if ( '' !== $filter ) { echo '<p class="rkaiviz-filter-note">Filtered by visualizer: <strong>' . esc_html( $filter ) . '</strong> · <a href="' . esc_url( self::url( self::LEADS_PAGE ) ) . '">Clear filter</a></p>'; }
        echo '<section class="rkaiviz-panel"><div class="rkaiviz-panel-heading"><div><p class="rkaiviz-eyebrow">CONTACTS</p><h2>' . esc_html( (string) count( $leads ) ) . ' lead' . ( 1 === count( $leads ) ? '' : 's' ) . '</h2></div></div>';
        self::render_lead_table( $leads, true );
        echo '</section>';
        self::end();
    }

    public static function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        self::start( 'settings', 'Settings', 'Global configuration shared by all registered visualizers.' );
        echo '<div class="rkaiviz-notice"><strong>Global settings</strong> Provider credentials, availability, and quota limits apply across the plugin. Definition-specific data remains read-only until a safe definition editor is available.</div>';
        if ( isset( $_GET['settings-updated'] ) && 'true' === (string) $_GET['settings-updated'] ) { echo '<div class="rkaiviz-notice is-success" role="status">Settings saved.</div>'; }
        Settings::form();
        self::end();
    }

    public static function delete_lead() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'You are not allowed to delete leads.' ); }
        $hash = isset( $_GET['lead'] ) ? sanitize_text_field( $_GET['lead'] ) : '';
        if ( ! preg_match( '/^[a-f0-9]{64}$/', $hash ) ) { wp_die( 'Invalid lead reference.' ); }
        check_admin_referer( self::DELETE_ACTION . '_' . $hash );
        $leads = Leads::all();
        $found = false;
        foreach ( $leads as $index => $lead ) {
            if ( isset( $lead['email'] ) && hash_hmac( 'sha256', strtolower( (string) $lead['email'] ), wp_salt( 'auth' ) ) === $hash ) {
                unset( $leads[ $index ] );
                $found = true;
                break;
            }
        }
        if ( $found ) { update_option( Leads::OPTION, array_values( $leads ), false ); }
        wp_safe_redirect( self::url( self::LEADS_PAGE, array( 'deleted' => $found ? '1' : '0' ) ) );
        exit;
    }

    private static function render_detail_tab( $definition, $tab ) {
        $slug = $definition->get( 'slug' );
        $settings = self::settings();
        $is_active = ! empty( $settings['enabled'] );
        if ( 'overview' === $tab ) {
            $description = $definition->get( 'description', '' );
            echo '<div class="rkaiviz-panel-heading"><div><p class="rkaiviz-eyebrow">OVERVIEW</p><h2>' . esc_html( $definition->get( 'name' ) ) . '</h2></div><span class="rkaiviz-status ' . ( $is_active ? 'is-positive' : 'is-muted' ) . '">' . ( $is_active ? 'Active' : 'Paused' ) . '</span></div>';
            if ( $description ) { echo '<p class="rkaiviz-lead-copy">' . esc_html( $description ) . '</p>'; }
            echo '<dl class="rkaiviz-facts"><div><dt>Slug</dt><dd><code>' . esc_html( $slug ) . '</code></dd></div><div><dt>Type</dt><dd>' . esc_html( 'built-in' === $definition->get( 'type', 'custom' ) ? 'Built-in' : 'Custom' ) . '</dd></div><div><dt>Status scope</dt><dd>Shared global plugin setting</dd></div></dl>';
            echo '<div class="rkaiviz-shortcode"><span>SHORTCODE</span><code>[rk_ai_visualizer visualizer="' . esc_html( $slug ) . '"]</code></div>';
            echo '<a class="rkaiviz-button rkaiviz-button-secondary" href="' . esc_url( self::url( self::SETTINGS_PAGE ) ) . '">Manage global availability</a>';
        } elseif ( 'appearance' === $tab ) {
            $copy = $definition->get( 'copy', array() );
            $cta = $definition->get( 'cta', array() );
            echo '<p class="rkaiviz-eyebrow">APPEARANCE</p><h2>Current display content</h2><p class="rkaiviz-muted">These definition values are shown for inspection only; editing them is not supported in this phase.</p><dl class="rkaiviz-facts">';
            if ( isset( $cta['label'] ) ) { echo '<div><dt>Call to action</dt><dd>' . esc_html( $cta['label'] ) . '</dd></div>'; }
            foreach ( $copy as $key => $value ) { if ( is_string( $value ) && '' !== $value ) { echo '<div><dt>' . esc_html( ucwords( str_replace( '_', ' ', $key ) ) ) . '</dt><dd>' . esc_html( $value ) . '</dd></div>'; } }
            echo '</dl>';
        } elseif ( 'fields' === $tab ) {
            echo '<p class="rkaiviz-eyebrow">FIELDS</p><h2>Definition fields</h2><p class="rkaiviz-muted">Field schema is managed in the registered definition; the builder is intentionally not part of this phase.</p><div class="rkaiviz-table-wrap"><table class="widefat striped rkaiviz-table"><thead><tr><th>Field</th><th>Type</th><th>Required</th><th>Options</th></tr></thead><tbody>';
            foreach ( $definition->fields() as $field ) {
                $options = isset( $field['options'] ) && is_array( $field['options'] ) ? count( $field['options'] ) : ( ! empty( $field['options_source'] ) ? 'Dynamic' : '—' );
                echo '<tr><td><strong>' . esc_html( isset( $field['label'] ) ? $field['label'] : $field['id'] ) . '</strong><br><code>' . esc_html( $field['id'] ) . '</code></td><td>' . esc_html( $field['type'] ) . '</td><td>' . ( ! empty( $field['required'] ) ? 'Yes' : 'Conditional / optional' ) . '</td><td>' . esc_html( (string) $options ) . '</td></tr>';
            }
            echo '</tbody></table></div>';
        } elseif ( 'prompt' === $tab ) {
            $prompt = $definition->get( 'prompt', array() );
            $base = isset( $prompt['base_rules'] ) && is_array( $prompt['base_rules'] ) ? count( $prompt['base_rules'] ) : 0;
            $transforms = isset( $prompt['transformations'] ) && is_array( $prompt['transformations'] ) ? count( $prompt['transformations'] ) : 0;
            $rules = isset( $prompt['transformation_rules'] ) && is_array( $prompt['transformation_rules'] ) ? count( $prompt['transformation_rules'] ) : 0;
            echo '<p class="rkaiviz-eyebrow">PROMPT</p><h2>Prompt configuration</h2><p class="rkaiviz-muted">Prompt composition remains in the existing definition and engine. No prompt editor is exposed.</p><dl class="rkaiviz-facts"><div><dt>Base rules</dt><dd>' . esc_html( (string) $base ) . '</dd></div><div><dt>Transformation maps</dt><dd>' . esc_html( (string) $transforms ) . '</dd></div><div><dt>Template rules</dt><dd>' . esc_html( (string) $rules ) . '</dd></div></dl>';
        } elseif ( 'usage' === $tab ) {
            $matching = array_filter( Leads::all(), function ( $lead ) use ( $slug ) { return isset( $lead['visualizer'] ) && $slug === $lead['visualizer']; } );
            echo '<p class="rkaiviz-eyebrow">USAGE</p><h2>Available usage data</h2><p class="rkaiviz-muted">This phase does not add analytics or generation-history storage.</p><dl class="rkaiviz-facts"><div><dt>Captured leads</dt><dd>' . esc_html( (string) count( $matching ) ) . '</dd></div><div><dt>Generation analytics</dt><dd>Not tracked</dd></div></dl><a class="rkaiviz-button rkaiviz-button-secondary" href="' . esc_url( self::url( self::LEADS_PAGE, array( 'visualizer' => $slug ) ) ) . '">View this visualizer’s leads</a>';
        } else {
            $upload = $definition->get( 'upload', array() );
            $quota = $definition->get( 'quota', array() );
            echo '<p class="rkaiviz-eyebrow">ADVANCED</p><h2>Definition constraints</h2><p class="rkaiviz-muted">Limits below are registered definition metadata; provider and global quota values are configured once in Settings.</p><dl class="rkaiviz-facts"><div><dt>Maximum upload</dt><dd>' . esc_html( isset( $upload['max_size'] ) ? size_format( (int) $upload['max_size'] ) : 'Default' ) . '</dd></div><div><dt>Minimum dimensions</dt><dd>' . esc_html( ( isset( $upload['min_width'] ) ? (int) $upload['min_width'] : 1 ) . ' × ' . ( isset( $upload['min_height'] ) ? (int) $upload['min_height'] : 1 ) . ' px' ) . '</dd></div><div><dt>Allowed image types</dt><dd>' . esc_html( isset( $upload['allowed_types'] ) && is_array( $upload['allowed_types'] ) ? implode( ', ', $upload['allowed_types'] ) : 'Default' ) . '</dd></div>';
            foreach ( array( 'free_count' => 'Default free generations', 'bonus_count' => 'Default lead bonus', 'cooldown_hours' => 'Default cooldown (hours)', 'ip_per_hour' => 'Default requests per IP/hour' ) as $key => $label ) {
                if ( isset( $quota[ $key ] ) ) { echo '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( (string) $quota[ $key ] ) . '</dd></div>'; }
            }
            echo '</dl>';
        }
    }

    private static function render_lead_table( array $leads, $with_actions ) {
        if ( ! $leads ) { echo '<div class="rkaiviz-empty"><strong>No leads yet.</strong><span>New contact records will appear here when visitors submit the existing lead form.</span></div>'; return; }
        echo '<div class="rkaiviz-table-wrap"><table class="widefat striped rkaiviz-table"><thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Visualizer / source</th><th>Created</th>' . ( $with_actions ? '<th>Action</th>' : '' ) . '</tr></thead><tbody>';
        foreach ( $leads as $lead ) {
            $name = isset( $lead['name'] ) ? $lead['name'] : '—';
            $email = isset( $lead['email'] ) ? $lead['email'] : '—';
            $phone = isset( $lead['phone'] ) && '' !== $lead['phone'] ? $lead['phone'] : '—';
            $slug = isset( $lead['visualizer'] ) ? sanitize_key( $lead['visualizer'] ) : '';
            $definition = $slug ? Registry::instance()->get( $slug ) : null;
            $source = $definition ? $definition->get( 'name' ) : ( $slug ? $slug : 'Unknown' );
            $created = isset( $lead['at'] ) ? self::format_date( $lead['at'] ) : '—';
            echo '<tr><td>' . esc_html( $name ) . '</td><td><a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a></td><td>' . esc_html( $phone ) . '</td><td>' . esc_html( $source ) . '</td><td>' . esc_html( $created ) . '</td>';
            if ( $with_actions ) {
                $hash = hash_hmac( 'sha256', strtolower( (string) $email ), wp_salt( 'auth' ) );
                $delete = admin_url( 'admin-post.php?' . http_build_query( array( 'action' => self::DELETE_ACTION, 'lead' => $hash ), '', '&', PHP_QUERY_RFC3986 ) );
                $delete = wp_nonce_url( $delete, self::DELETE_ACTION . '_' . $hash );
                echo '<td><a class="rkaiviz-delete-link" href="' . esc_url( $delete ) . '" onclick="return confirm(\'Delete this lead? This cannot be undone.\')">Delete</a></td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }

    private static function start( $active, $title, $description ) {
        echo '<div class="wrap rkaiviz-admin-wrap"><div class="rkaiviz-app"><aside class="rkaiviz-sidebar"><a class="rkaiviz-brand" href="' . esc_url( self::url( self::PAGE ) ) . '"><span class="rkaiviz-brand-mark" aria-hidden="true">RK</span><span><strong>RK AI Visualizer</strong><small>Admin workspace</small></span></a><nav class="rkaiviz-navigation" aria-label="RK AI Visualizer">';
        $items = array( 'dashboard' => array( self::PAGE, 'Dashboard', '01' ), 'visualizers' => array( self::VISUALIZERS_PAGE, 'Visualizers', '02' ), 'leads' => array( self::LEADS_PAGE, 'Leads', '03' ), 'settings' => array( self::SETTINGS_PAGE, 'Settings', '04' ) );
        foreach ( $items as $id => $item ) {
            $selected = $active === $id;
            echo '<a class="rkaiviz-nav-item ' . ( $selected ? 'is-active' : '' ) . '" ' . ( $selected ? 'aria-current="page"' : '' ) . ' href="' . esc_url( self::url( $item[0] ) ) . '"><span class="rkaiviz-nav-number" aria-hidden="true">' . esc_html( $item[2] ) . '</span><span>' . esc_html( $item[1] ) . '</span></a>';
        }
        echo '</nav><div class="rkaiviz-sidebar-note"><span class="rkaiviz-eyebrow">WORKSPACE</span><p>Manage visualizers, leads, and shared configuration.</p></div></aside><main class="rkaiviz-main"><header class="rkaiviz-page-header"><div><p class="rkaiviz-eyebrow">RK AI VISUALIZER</p><h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $description ) . '</p></div><a class="rkaiviz-header-link" href="' . esc_url( self::url( self::SETTINGS_PAGE ) ) . '">Global settings <span aria-hidden="true">↗</span></a></header>';
    }

    private static function end() { echo '</main></div></div>'; }

    private static function stat( $label, $value, $note, $class ) {
        echo '<article class="rkaiviz-stat-card"><span class="rkaiviz-stat-label">' . esc_html( $label ) . '</span><strong class="rkaiviz-stat-value">' . esc_html( $value ) . '</strong><span class="rkaiviz-stat-note ' . esc_attr( $class ) . '">' . esc_html( $note ) . '</span></article>';
    }

    private static function quick_action( $number, $title, $description, $url ) {
        echo '<a class="rkaiviz-quick-action" href="' . esc_url( $url ) . '"><span class="rkaiviz-quick-number">' . esc_html( $number ) . '</span><span><strong>' . esc_html( $title ) . '</strong><small>' . esc_html( $description ) . '</small></span><span class="rkaiviz-quick-arrow" aria-hidden="true">→</span></a>';
    }

    private static function settings() {
        $saved = get_option( 'rk_ai_visualizer_settings', array() );
        return Settings::sanitize( is_array( $saved ) ? $saved : array() );
    }

    private static function provider_label( $provider ) {
        $labels = array( 'mock' => 'Mock', 'gemini' => 'Google Gemini', 'huggingface' => 'Hugging Face', 'custom' => 'Custom backend' );
        return isset( $labels[ $provider ] ) ? $labels[ $provider ] : 'Not configured';
    }

    private static function url( $page, array $args = array() ) {
        $query = array_merge( array( 'page' => $page ), $args );
        return admin_url( 'admin.php?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 ) );
    }

    private static function preview_url( $slug ) {
        if ( ! function_exists( 'get_posts' ) || ! function_exists( 'get_permalink' ) ) { return ''; }
        $posts = get_posts( array( 'post_type' => array( 'page', 'post' ), 'post_status' => 'publish', 'posts_per_page' => 100, 'orderby' => 'modified', 'order' => 'DESC' ) );
        foreach ( (array) $posts as $post ) {
            $content = isset( $post->post_content ) ? (string) $post->post_content : '';
            $pattern = "~\\[\\s*rk_ai_visualizer\\b[^\\]]*\\bvisualizer\\s*=\\s*([\"'])" . preg_quote( $slug, '~' ) . "\\1~i";
            if ( preg_match( $pattern, $content ) ) { return get_permalink( $post ); }
        }
        return '';
    }

    private static function format_date( $value ) {
        $timestamp = is_numeric( $value ) ? (int) $value : strtotime( (string) $value );
        if ( ! $timestamp ) { return '—'; }
        return function_exists( 'wp_date' ) ? wp_date( get_option( 'date_format', 'M j, Y' ), $timestamp ) : gmdate( 'M j, Y', $timestamp );
    }
}
