<?php
namespace RK\AIVisualizer\Core;
use RK\AIVisualizer\Definitions\Definition;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Validation {
    private static function conditionMatches( $value,$expected ) { if (is_bool($value)) { if (is_bool($expected)) return $value===$expected; return $value===in_array(strtolower((string)$expected),array('1','true','yes'),true); } if (is_numeric($value)&&is_numeric($expected)) return (float)$value===(float)$expected; return (string)$value===(string)$expected; }
    public static function upload( $request, Definition $definition ) {
        $files = method_exists( $request, 'get_file_params' ) ? $request->get_file_params() : array();
        $file = isset( $files['image'] ) && is_array( $files['image'] ) ? $files['image'] : null;
        if ( ! $file || ! isset( $file['tmp_name'] ) || ! is_string( $file['tmp_name'] ) || '' === $file['tmp_name'] || ! empty( $file['error'] ) || ! is_readable( $file['tmp_name'] ) ) { return Errors::make( 'rk_viz_no_image', 'Please upload an image.', 400 ); }
        $rules = $definition->get( 'upload', array() ); $size = (int) @filesize( $file['tmp_name'] );
        $limit = isset( $rules['max_size'] ) ? (int) $rules['max_size'] : 10485760;
        if ( function_exists( 'wp_max_upload_size' ) ) { $wpLimit=(int)wp_max_upload_size(); if ($wpLimit<=0) return Errors::make('rk_viz_upload_unavailable','Image uploads are unavailable right now.',503); $limit=min($limit,$wpLimit); }
        if ( $size <= 0 || $limit<=0 || $size > $limit ) { return Errors::make( 'rk_viz_too_large', 'This image is too large. Please choose a smaller image within the allowed size limit.', 413 ); }
        $info = @getimagesize( $file['tmp_name'] ); $allowed = isset( $rules['allowed_types'] ) ? (array) $rules['allowed_types'] : array( 'image/jpeg','image/png','image/webp' );
        if ( ! is_array( $info ) || empty( $info['mime'] ) || ! in_array( $info['mime'], $allowed, true ) ) { return Errors::make( 'rk_viz_bad_type', 'This file type isn\'t supported. Please upload a JPG, PNG, or WebP image.', 415 ); }
        $minW = isset( $rules['min_width'] ) ? (int) $rules['min_width'] : 1; $minH = isset( $rules['min_height'] ) ? (int) $rules['min_height'] : 1;
        if ( (int) $info[0] < $minW || (int) $info[1] < $minH ) { return Errors::make( 'rk_viz_too_small', 'This image is too small for a reliable preview. Please choose a higher-resolution image.', 400 ); }
        return array( 'path'=>$file['tmp_name'],'mime'=>$info['mime'],'width'=>(int)$info[0],'height'=>(int)$info[1],'size'=>$size );
    }
    public static function fields( $raw, Definition $definition ) {
        if ( is_string( $raw ) ) { $raw = json_decode( $raw, true ); }
        if ( ! is_array( $raw ) ) { return Errors::make( 'rk_viz_bad_options', 'Please review the highlighted fields before generating.', 400 ); }
        $out = array();
        foreach ( $definition->fields() as $field ) {
            $id = $field['id']; $rawValue=isset($raw[$id])?$raw[$id]:'';
            if ('toggle'===$field['type'] && ''!==$rawValue) { if (is_bool($rawValue)) $value=$rawValue; elseif (in_array($rawValue,array('1','0',1,0),true)) $value=(bool)$rawValue; else return Errors::make('rk_viz_bad_options','Please choose a valid value for '.strtolower($field['label']).'.',400); }
            else { $value=is_scalar($rawValue)?trim((string)$rawValue):''; }
            if ( 'textarea' === $field['type'] || 'text' === $field['type'] ) { $value = preg_replace( '/\s+/', ' ', $value ); $max = isset( $field['max_length'] ) ? (int)$field['max_length'] : 500; $value = function_exists('mb_substr') ? mb_substr($value,0,$max) : substr($value,0,$max); }
            if ( ! empty( $field['visible_when'] ) ) { $condition = $field['visible_when']; $isVisible = isset($out[$condition['field']]) && self::conditionMatches($out[$condition['field']],$condition['equals']); if ( ! $isVisible ) { $value = ''; } }
            $required = ! empty( $field['required'] );
            if ( ! empty($field['required_when']) ) { $condition=$field['required_when']; $required=isset($out[$condition['field']])&&self::conditionMatches($out[$condition['field']],$condition['equals']); }
            if ( $required && ( '' === $value || ( 'toggle' === $field['type'] && false === $value ) ) ) { $message=isset($field['required_message'])?$field['required_message']:'Please choose an option for ' . strtolower( $field['label'] ) . '.'; return Errors::make( 'rk_viz_bad_options', $message, 400 ); }
            if ( '' !== $value && ! empty( $field['options'] ) && is_array( $field['options'] ) && ! array_key_exists( $value, $field['options'] ) ) { return Errors::make( 'rk_viz_bad_options', 'Please choose a valid option for ' . strtolower( $field['label'] ) . '.', 400 ); }
            if ( '' !== $value && ! empty( $field['pattern'] ) && 1 !== preg_match( $field['pattern'], (string)$value ) ) { return Errors::make( 'rk_viz_bad_options', 'Please enter a valid ' . strtolower( $field['label'] ) . '.', 400 ); }
            if ( in_array($field['type'],array('number','slider'),true) ) { if ( '' !== $value && ! is_numeric( $value ) ) { return Errors::make( 'rk_viz_bad_options', 'Please enter a valid ' . strtolower($field['label']) . '.', 400 ); } $n = '' === $value ? ( isset($field['default']) ? $field['default'] : 0 ) : ( 'slider'===$field['type'] ? (float)$value : (int)$value ); $min=isset($field['min'])?(float)$field['min']:0; $max=isset($field['max'])?(float)$field['max']:1000000; $n=max($min,min($max,$n)); $value='slider'===$field['type']?$n:(int)$n; }
            $out[$id] = $value;
        }
        return $out;
    }
}
