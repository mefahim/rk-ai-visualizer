<?php
namespace RK\AIVisualizer\Providers;
use RK\AIVisualizer\Core\Storage;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Http {
    public static function request( $method, $url, array $args = array() ) {
        if (!Storage::isSafeRemoteUrl($url)) { return new \WP_Error('rk_viz_remote_url','Only public, safe HTTPS provider URLs are allowed.'); }
        $requestArgs=array_merge($args,array('method'=>$method,'redirection'=>0,'reject_unsafe_urls'=>true,'limit_response_size'=>20*1024*1024));
        $pre=apply_filters('rk_ai_visualizer_http',null,$method,$url,$requestArgs);
        if (null!==$pre) { return $pre; }
        $response=wp_remote_request($url,$requestArgs);
        if (is_wp_error($response)) { return $response; }
        return array('code'=>(int)wp_remote_retrieve_response_code($response),'body'=>(string)wp_remote_retrieve_body($response));
    }
    public static function settings() { $s=get_option('rk_ai_visualizer_settings',array()); return is_array($s)?$s:array(); }
    public static function config( $key, $default = '' ) { $s=self::settings(); return isset($s[$key])?$s[$key]:$default; }
    public static function secret( $key, $constant, $env ) {
        if (defined($constant) && constant($constant)) { return (string)constant($constant); }
        $fromEnv=getenv($env); if (is_string($fromEnv)&&''!==$fromEnv) { return $fromEnv; }
        return (string)self::config($key,'');
    }
    public static function json( $response ) { return is_array($response)&&isset($response['body'])&&is_string($response['body'])?json_decode($response['body'],true):null; }
}
