<?php
namespace RK\AIVisualizer\Providers;
use RK\AIVisualizer\Core\Storage;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Http {
    const MAX_RESPONSE_BYTES = 20971520;
    public static function request( $method, $url, array $args = array() ) {
        if (!Storage::isSafeRemoteUrl($url)) { return new \WP_Error('rk_viz_remote_url','Only public, safe HTTPS provider URLs are allowed.'); }
        $requestArgs=array_merge($args,array('method'=>$method,'redirection'=>0,'reject_unsafe_urls'=>true,'limit_response_size'=>self::MAX_RESPONSE_BYTES));
        $pre=apply_filters('rk_ai_visualizer_http',null,$method,$url,$requestArgs);
        if (null!==$pre) { return self::normalizeResponse($pre); }
        $response=wp_remote_request($url,$requestArgs);
        if (is_wp_error($response)) { return $response; }
        return self::normalizeResponse(array('code'=>wp_remote_retrieve_response_code($response),'body'=>wp_remote_retrieve_body($response)));
    }
    private static function normalizeResponse( $response ) {
        if (is_wp_error($response)) { return $response; }
        if (!is_array($response)||!isset($response['code'],$response['body'])||!is_numeric($response['code'])||!is_string($response['body'])) { return new \WP_Error('rk_viz_http_response','The provider returned an invalid HTTP response.'); }
        if (strlen($response['body'])>self::MAX_RESPONSE_BYTES) { return new \WP_Error('rk_viz_http_response','The provider response exceeded the configured size limit.'); }
        return array('code'=>(int)$response['code'],'body'=>$response['body']);
    }
    public static function settings() { $s=get_option('rk_ai_visualizer_settings',array()); return is_array($s)?$s:array(); }
    public static function config( $key, $default = '' ) { $s=self::settings(); if(!isset($s[$key])||!is_scalar($s[$key]))return $default; return $s[$key]; }
    public static function secret( $key, $constant, $env ) {
        if (defined($constant) && is_scalar(constant($constant)) && ''!==(string)constant($constant)) { return (string)constant($constant); }
        $fromEnv=getenv($env); if (is_string($fromEnv)&&''!==$fromEnv) { return $fromEnv; }
        return (string)self::config($key,'');
    }
    public static function json( $response ) { return is_array($response)&&isset($response['body'])&&is_string($response['body'])?json_decode($response['body'],true):null; }
}
