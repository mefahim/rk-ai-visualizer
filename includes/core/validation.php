<?php
namespace RK\AIVisualizer\Core;
use RK\AIVisualizer\Definitions\Definition;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Validation {
    public static function upload( $request, Definition $definition ) {
        $files = method_exists( $request, 'get_file_params' ) ? $request->get_file_params() : array();
        $file = isset( $files['image'] ) && is_array( $files['image'] ) ? $files['image'] : null;
        if ( ! $file || empty( $file['tmp_name'] ) || ! empty( $file['error'] ) || ! is_readable( $file['tmp_name'] ) ) { return Errors::make( 'rk_viz_no_image', 'Please upload an image.', 400 ); }
        $rules = $definition->get( 'upload', array() ); $size = (int) @filesize( $file['tmp_name'] );
        $limit = isset( $rules['max_size'] ) ? (int) $rules['max_size'] : 10485760;
        if ( function_exists( 'wp_max_upload_size' ) ) { $limit = min( $limit, (int) wp_max_upload_size() ); }
        if ( $size <= 0 || $size > max( 1024, $limit ) ) { return Errors::make( 'rk_viz_too_large', 'This image is too large. Please choose an image under 10 MB.', 413 ); }
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
            $id = $field['id']; $value = isset( $raw[$id] ) && is_scalar( $raw[$id] ) ? trim( (string) $raw[$id] ) : '';
            if ( 'textarea' === $field['type'] || 'text' === $field['type'] ) { $value = preg_replace( '/\s+/', ' ', $value ); $max = isset( $field['max_length'] ) ? (int)$field['max_length'] : 500; $value = function_exists('mb_substr') ? mb_substr($value,0,$max) : substr($value,0,$max); }
            if ( ! empty( $field['visible_when'] ) ) { $condition = $field['visible_when']; $isVisible = isset($out[$condition['field']]) && $out[$condition['field']] === $condition['equals']; if ( ! $isVisible ) { $value = ''; } }
            $required = ! empty( $field['required'] );
            if ( ! empty($field['required_when']) ) { $condition=$field['required_when']; $required=isset($out[$condition['field']])&&$out[$condition['field']]===$condition['equals']; }
            if ( $required && '' === $value ) { $message=isset($field['required_message'])?$field['required_message']:'Please choose an option for ' . strtolower( $field['label'] ) . '.'; return Errors::make( 'rk_viz_bad_options', $message, 400 ); }
            if ( '' !== $value && ! empty( $field['options'] ) && is_array( $field['options'] ) && ! array_key_exists( $value, $field['options'] ) ) { return Errors::make( 'rk_viz_bad_options', 'Please choose a valid option for ' . strtolower( $field['label'] ) . '.', 400 ); }
            if ( '' !== $value && ! empty( $field['pattern'] ) && 1 !== preg_match( $field['pattern'], (string)$value ) ) { return Errors::make( 'rk_viz_bad_options', 'Please enter a valid ' . strtolower( $field['label'] ) . '.', 400 ); }
            if ( 'number' === $field['type'] ) { if ( '' !== $value && ! is_numeric( $value ) ) { return Errors::make( 'rk_viz_bad_options', 'Please enter a valid ' . strtolower($field['label']) . '.', 400 ); } $n = '' === $value ? ( isset($field['default']) ? $field['default'] : 0 ) : (int)$value; $n = max( isset($field['min'])?(int)$field['min']:0, min( isset($field['max'])?(int)$field['max']:1000000, $n ) ); $value = $n; }
            $out[$id] = $value;
        }
        return $out;
    }
}
