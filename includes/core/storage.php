<?php
namespace RK\AIVisualizer\Core;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Storage {
    public static function storeImage( $data ) {
        if ( ! is_string($data) || ''===$data || ! function_exists('wp_upload_dir') ) { return ''; }
        if ( 0===strpos($data,'data:') ) { $comma=strpos($data,','); if (false===$comma) { return ''; } $data=substr($data,$comma+1); }
        $bytes=base64_decode($data,true); if (false===$bytes) { $bytes=$data; }
        $info=@getimagesizefromstring($bytes); $ext=array('image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp');
        if (!is_array($info) || empty($ext[$info['mime']])) { return ''; }
        $up=wp_upload_dir(); if (!empty($up['error']) || empty($up['basedir']) || empty($up['baseurl'])) { return ''; }
        $dir=trailingslashit($up['basedir']).'rk-ai-visualizer'; if (!is_dir($dir) && !wp_mkdir_p($dir)) { return ''; }
        $name=bin2hex(random_bytes(12)).'.'.$ext[$info['mime']]; $path=trailingslashit($dir).$name;
        if (false===@file_put_contents($path,$bytes,LOCK_EX)) { return ''; }
        return trailingslashit($up['baseurl']).'rk-ai-visualizer/'.$name;
    }
    public static function cleanup() {
        if (!function_exists('wp_upload_dir')) { return; } $up=wp_upload_dir(); if (!empty($up['error'])) { return; }
        $dir=trailingslashit($up['basedir']).'rk-ai-visualizer'; if (!is_dir($dir)) { return; }
        foreach ((array)glob($dir.'/*.{jpg,png,webp}',GLOB_BRACE) as $file) { if (is_file($file) && time()-(int)filemtime($file)>7*86400) { @unlink($file); } }
    }
    public static function isHttpsUrl( $url ) {
        if (!is_string($url)||!preg_match('#^https://[^\s]+$#i',$url)) { return false; }
        $parts=wp_parse_url($url);
        return is_array($parts)&&isset($parts['scheme'],$parts['host'])&&'https'===strtolower($parts['scheme'])&&''!==$parts['host']&&!isset($parts['user'])&&!isset($parts['pass']);
    }
    public static function isSafeRemoteUrl( $url ) { return self::isHttpsUrl($url)&&function_exists('wp_http_validate_url')&&false!==wp_http_validate_url($url); }
}
