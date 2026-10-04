<?php
namespace RK\AIVisualizer\Core;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Errors {
    public static function make( $code, $message, $status = 400, array $data = array() ) {
        $data['status'] = (int) $status;
        return new \WP_Error( (string) $code, (string) $message, $data );
    }
    public static function is( $value ) { return is_wp_error( $value ); }
}
