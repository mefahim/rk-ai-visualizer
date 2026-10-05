<?php
namespace RK\AIVisualizer\Definitions;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Definition {
    private $data;
    private const FIELD_TYPES = array( 'text', 'textarea', 'number', 'select', 'radio', 'cards', 'swatches', 'image', 'toggle', 'slider' );
    private const IMAGE_TYPES = array( 'image/jpeg', 'image/png', 'image/webp' );
    public function __construct( array $data ) {
        $slug = isset( $data['slug'] ) && is_string( $data['slug'] ) ? sanitize_key( $data['slug'] ) : '';
        if ( '' === $slug || empty( $data['name'] ) || ! is_string( $data['name'] ) || empty( $data['fields'] ) || ! is_array( $data['fields'] ) ) {
            throw new \InvalidArgumentException( 'A definition requires a slug, name, and fields.' );
        }
        $data['slug'] = $slug;
        $upload = isset( $data['upload'] ) && is_array( $data['upload'] ) ? $data['upload'] : array();
        $upload = array_merge( array( 'max_size' => 10485760, 'min_width' => 1, 'min_height' => 1, 'allowed_types' => self::IMAGE_TYPES ), $upload );
        if ( ! is_numeric( $upload['max_size'] ) || (float) $upload['max_size'] !== (float) (int) $upload['max_size'] || (int) $upload['max_size'] < 1 || (int) $upload['max_size'] > 52428800 || ! is_numeric( $upload['min_width'] ) || (float) $upload['min_width'] !== (float) (int) $upload['min_width'] || (int) $upload['min_width'] < 1 || ! is_numeric( $upload['min_height'] ) || (float) $upload['min_height'] !== (float) (int) $upload['min_height'] || (int) $upload['min_height'] < 1 || ! is_array( $upload['allowed_types'] ) || ! $upload['allowed_types'] ) {
            throw new \InvalidArgumentException( 'Upload size, dimensions, and allowed types must be valid.' );
        }
        foreach ( $upload['allowed_types'] as $mime ) { if ( ! is_string( $mime ) || ! in_array( $mime, self::IMAGE_TYPES, true ) ) { throw new \InvalidArgumentException( 'The upload MIME type is not supported by image storage.' ); } }
        $data['upload'] = $upload;
        $quota = isset( $data['quota'] ) && is_array( $data['quota'] ) ? $data['quota'] : array();
        foreach ( array( 'free_count' => array( 0, 20 ), 'bonus_count' => array( 0, 20 ), 'cooldown_hours' => array( 1, 720 ), 'ip_per_hour' => array( 1, 1000 ) ) as $key => $bounds ) {
            if ( isset( $quota[$key] ) && ( ! is_numeric( $quota[$key] ) || (float) $quota[$key] !== (float) (int) $quota[$key] || (int) $quota[$key] < $bounds[0] || (int) $quota[$key] > $bounds[1] ) ) { throw new \InvalidArgumentException( 'Quota values are outside supported bounds.' ); }
        }
        $data['quota'] = $quota;
        $ids = array(); $positions = array();
        $expected_index=0; foreach ( $data['fields'] as $index => $field ) {
            if ($index!==$expected_index++) { throw new \InvalidArgumentException( 'Definition fields must be an ordered list.' ); }
            if ( ! is_array( $field ) || empty( $field['id'] ) || empty( $field['type'] ) || empty( $field['label'] ) ) { throw new \InvalidArgumentException( 'Every field needs an id, type, and label.' ); }
            if ( ! is_string( $field['id'] ) || ! preg_match( '/^[A-Za-z][A-Za-z0-9_]*$/', $field['id'] ) || ! is_string( $field['label'] ) || ! is_string( $field['type'] ) ) { throw new \InvalidArgumentException( 'Field IDs must be safe identifiers and labels/types must be strings.' ); }
            if ( ! in_array( $field['type'], self::FIELD_TYPES, true ) ) { throw new \InvalidArgumentException( 'Unsupported field type: ' . $field['type'] ); }
            $id_key = strtolower( $field['id'] );
            if ( isset( $ids[$id_key] ) ) { throw new \InvalidArgumentException( 'Field identifiers must be unique, ignoring case.' ); }
            $ids[$id_key] = $field['id']; $positions[$field['id']] = (int) $index;
            if ( in_array( $field['type'], array( 'select', 'radio', 'cards', 'swatches', 'image' ), true ) ) {
                $options = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
                $source = isset( $field['options_source'] ) ? $field['options_source'] : '';
                if ( ! $options && 'cities' !== $source ) { throw new \InvalidArgumentException( 'Choice fields require options or a supported options source.' ); }
                if ( '' !== $source && 'cities' !== $source ) { throw new \InvalidArgumentException( 'Unsupported choice options source.' ); }
                foreach ( $options as $value => $label ) { if ( ( ! is_string( $value ) && ! is_int( $value ) ) || ! is_string( $label ) || '' === (string) $value || '' === trim( $label ) ) { throw new \InvalidArgumentException( 'Choice option values must be scalar keys and labels must be non-empty strings.' ); } }
                if ( 'image' === $field['type'] ) {
                    $images = isset( $field['images'] ) && is_array( $field['images'] ) ? $field['images'] : array();
                    if ( 'cities' === $source || count( $images ) !== count( $options ) ) { throw new \InvalidArgumentException( 'Image choice fields require one image URL for every static option.' ); }
                    foreach ( $options as $value => $label ) {
                        if ( ! array_key_exists( $value, $images ) || ! is_string( $images[$value] ) || '' === trim( $images[$value] ) ) { throw new \InvalidArgumentException( 'Image choice fields require one image URL for every static option.' ); }
                        $image_url = trim( $images[$value] );
                        $parts = parse_url( $image_url );
                        $absolute = (bool) preg_match( '#^https?://#i', $image_url );
                        $root_relative = 0 === strpos( $image_url, '/' ) && 0 !== strpos( $image_url, '//' );
                        if ( ( ! $absolute && ! $root_relative ) || ( $absolute && ( false === filter_var( $image_url, FILTER_VALIDATE_URL ) || ! is_array( $parts ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) ) ) { throw new \InvalidArgumentException( 'Image choice URLs must be HTTPS/HTTP URLs or root-relative paths without credentials.' ); }
                    }
                }
            }
            if ( in_array( $field['type'], array( 'number', 'slider' ), true ) ) {
                foreach ( array( 'min', 'max', 'default', 'step' ) as $numeric_key ) { if ( isset( $field[$numeric_key] ) && ! is_numeric( $field[$numeric_key] ) ) { throw new \InvalidArgumentException( 'Numeric field constraints must be numeric.' ); } }
                if ( isset( $field['min'], $field['max'] ) && (float) $field['min'] > (float) $field['max'] ) { throw new \InvalidArgumentException( 'A field minimum cannot exceed its maximum.' ); }
                if ( isset( $field['step'] ) && (float) $field['step'] <= 0 ) { throw new \InvalidArgumentException( 'A numeric field step must be positive.' ); }
            }
            if ( isset( $field['max_length'] ) && ( ! is_numeric( $field['max_length'] ) || (int) $field['max_length'] < 1 || (int) $field['max_length'] > 10000 ) ) { throw new \InvalidArgumentException( 'Text field length must be between 1 and 10000.' ); }
            if ( isset( $field['pattern'] ) && ( ! is_string( $field['pattern'] ) || false === @preg_match( $field['pattern'], '' ) ) ) { throw new \InvalidArgumentException( 'Field patterns must be valid regular expressions.' ); }
            if ( isset( $field['required_message'] ) && ! is_string( $field['required_message'] ) ) { throw new \InvalidArgumentException( 'Required messages must be strings.' ); }
        }
        foreach ( $data['fields'] as $field ) {
            foreach ( array( 'visible_when', 'required_when' ) as $condition_key ) {
                if ( empty( $field[$condition_key] ) ) { continue; }
                $condition = $field[$condition_key];
                if ( ! is_array( $condition ) || ! isset( $condition['field'] ) || ! is_string( $condition['field'] ) || ! array_key_exists( $condition['field'], $positions ) || ! array_key_exists( 'equals', $condition ) || ! is_scalar( $condition['equals'] ) || $positions[$condition['field']] >= $positions[$field['id']] ) {
                    throw new \InvalidArgumentException( 'Field conditions must refer to an earlier field and an expected scalar value.' );
                }
            }
        }
        $this->data = $data;
    }
    public function get( $key = null, $default = null ) { return null === $key ? $this->data : ( isset( $this->data[$key] ) ? $this->data[$key] : $default ); }
    public function fields() { return $this->data['fields']; }
    public function field( $id ) { foreach ( $this->fields() as $field ) { if ( $field['id'] === $id ) { return $field; } } return null; }
}
