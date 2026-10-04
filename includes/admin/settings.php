<?php
namespace RK\AIVisualizer\Admin;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Settings {
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'register' ) );
    }
    public static function menu() {
        add_options_page( 'RK AI Visualizer', 'RK AI Visualizer', 'manage_options', 'rk-ai-visualizer', array( __CLASS__, 'render' ) );
    }
    public static function register() {
        register_setting( 'rk_ai_visualizer', 'rk_ai_visualizer_settings', array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) ) );
    }
    public static function sanitize( $input ) {
        $defaults = array(
            'enabled' => false,
            'provider' => 'huggingface',
            'hf_token' => '',
            'gemini_key' => '',
            'gemini_model' => 'gemini-2.5-flash-image',
            'custom_url' => '',
            'custom_key' => '',
            'custom_header' => 'Authorization',
            'free_count' => 2,
            'bonus_count' => 1,
            'cooldown_hours' => 24,
            'ip_per_hour' => 10,
            'timeout' => 120,
        );
        $old = get_option( 'rk_ai_visualizer_settings', array() );
        $old = is_array( $old ) ? $old : array();
        $in = is_array( $input ) ? $input : array();
        $out = array_merge( $defaults, $old );
        $out['enabled'] = isset( $in['enabled'] ) && in_array( $in['enabled'], array( '1', 1, true, 'on' ), true );
        $provider = isset( $in['provider'] ) && is_string( $in['provider'] ) ? $in['provider'] : ( isset( $out['provider'] ) && is_string( $out['provider'] ) ? $out['provider'] : 'huggingface' );
        $out['provider'] = in_array( $provider, array( 'mock', 'gemini', 'huggingface', 'custom' ), true ) ? $provider : 'huggingface';
        foreach ( array( 'hf_token', 'gemini_key', 'custom_key' ) as $secret ) {
            $saved_secret = isset( $out[ $secret ] ) && is_string( $out[ $secret ] ) ? sanitize_text_field( $out[ $secret ] ) : '';
            $out[ $secret ] = strlen( $saved_secret ) <= 4096 ? $saved_secret : '';
            $clear_key = 'clear_' . $secret;
            $clear = isset( $in[ $clear_key ] ) && in_array( $in[ $clear_key ], array( '1', 1, true, 'on' ), true );
            if ( $clear ) {
                $out[ $secret ] = '';
            } elseif ( isset( $in[ $secret ] ) && is_string( $in[ $secret ] ) && '' !== trim( $in[ $secret ] ) ) {
                $new_secret = sanitize_text_field( $in[ $secret ] );
                $out[ $secret ] = strlen( $new_secret ) <= 4096 ? $new_secret : '';
            }
        }
        $url = isset( $in['custom_url'] ) && is_string( $in['custom_url'] ) ? trim( $in['custom_url'] ) : '';
        $url_parts = preg_match( '#^https://[^\s]+$#i', $url ) ? wp_parse_url( $url ) : false;
        $out['custom_url'] = is_array( $url_parts ) && ! empty( $url_parts['host'] ) && ! isset( $url_parts['user'] ) && ! isset( $url_parts['pass'] ) ? esc_url_raw( $url ) : '';
        $model = isset( $in['gemini_model'] ) && is_string( $in['gemini_model'] ) ? trim( $in['gemini_model'] ) : '';
        $out['gemini_model'] = preg_match( '/^[A-Za-z0-9._-]{1,80}$/', $model ) ? $model : 'gemini-2.5-flash-image';
        $header = isset( $in['custom_header'] ) && is_string( $in['custom_header'] ) ? trim( $in['custom_header'] ) : '';
        $out['custom_header'] = preg_match( '/^[A-Za-z0-9-]{1,60}$/', $header ) ? $header : 'Authorization';
        foreach ( array( 'free_count' => array( 0, 20, 2 ), 'bonus_count' => array( 0, 20, 1 ), 'cooldown_hours' => array( 1, 720, 24 ), 'ip_per_hour' => array( 1, 1000, 10 ), 'timeout' => array( 20, 600, 120 ) ) as $key => $spec ) {
            $number = isset( $in[ $key ] ) && is_numeric( $in[ $key ] ) ? (int) $in[ $key ] : $spec[2];
            $out[ $key ] = max( $spec[0], min( $spec[1], $number ) );
        }
        return $out;
    }
    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $saved = get_option( 'rk_ai_visualizer_settings', array() );
        $s = self::sanitize( $saved );
        $name = 'rk_ai_visualizer_settings';
        echo '<div class="wrap"><h1>RK AI Visualizer</h1><form method="post" action="options.php">';
        settings_fields( 'rk_ai_visualizer' );
        self::checkbox( $name, 'enabled', 'Enable visualizer', $s );
        self::select( $name, 'provider', 'Provider', $s, array( 'mock' => 'Mock (no API cost)', 'gemini' => 'Google Gemini', 'huggingface' => 'Hugging Face', 'custom' => 'Custom backend' ) );
        self::secret( $name, 'gemini_key', 'Gemini API key' );
        self::text( $name, 'gemini_model', 'Gemini model', $s );
        self::secret( $name, 'hf_token', 'Hugging Face token' );
        self::text( $name, 'custom_url', 'Custom HTTPS backend URL', $s );
        self::secret( $name, 'custom_key', 'Custom backend key' );
        self::text( $name, 'custom_header', 'Custom key header', $s );
        foreach ( array( 'free_count' => 'Free generations', 'bonus_count' => 'Lead bonus generations', 'cooldown_hours' => 'Cooldown (hours)', 'ip_per_hour' => 'Requests per IP per hour', 'timeout' => 'Async timeout (seconds)' ) as $key => $label ) {
            self::number( $name, $key, $label, $s );
        }
        submit_button();
        echo '</form><hr><p>Shortcode: <code>[rk_ai_visualizer visualizer="flooring"]</code>. Leads are stored in the WordPress options table. Keep backups and restrict database access.</p></div>';
    }
    private static function name( $option, $key ) { return $option . '[' . $key . ']'; }
    private static function text( $option, $key, $label, $settings ) {
        echo '<p><label>' . esc_html( $label ) . '<br><input class="regular-text" type="text" name="' . esc_attr( self::name( $option, $key ) ) . '" value="' . esc_attr( $settings[ $key ] ) . '"></label></p>';
    }
    private static function secret( $option, $key, $label ) {
        echo '<p><label>' . esc_html( $label ) . ' (saved value is not displayed)<br><input class="regular-text" type="password" autocomplete="new-password" name="' . esc_attr( self::name( $option, $key ) ) . '" value=""></label> <label><input type="checkbox" name="' . esc_attr( self::name( $option, 'clear_' . $key ) ) . '" value="1"> Clear</label></p>';
    }
    private static function number( $option, $key, $label, $settings ) {
        echo '<p><label>' . esc_html( $label ) . '<br><input type="number" name="' . esc_attr( self::name( $option, $key ) ) . '" value="' . esc_attr( (int) $settings[ $key ] ) . '"></label></p>';
    }
    private static function checkbox( $option, $key, $label, $settings ) {
        echo '<p><label><input type="checkbox" name="' . esc_attr( self::name( $option, $key ) ) . '" value="1" ' . checked( ! empty( $settings[ $key ] ), true, false ) . '> ' . esc_html( $label ) . '</label></p>';
    }
    private static function select( $option, $key, $label, $settings, $options ) {
        echo '<p><label>' . esc_html( $label ) . '<br><select name="' . esc_attr( self::name( $option, $key ) ) . '">';
        foreach ( $options as $value => $text ) {
            echo '<option value="' . esc_attr( $value ) . '" ' . selected( $settings[ $key ], $value, false ) . '>' . esc_html( $text ) . '</option>';
        }
        echo '</select></label></p>';
    }
}
