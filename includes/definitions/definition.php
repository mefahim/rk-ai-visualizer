<?php
namespace RK\AIVisualizer\Definitions;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Definition {
    private $data;
    public function __construct( array $data ) {
        $slug = isset( $data['slug'] ) ? sanitize_key( $data['slug'] ) : '';
        if ( '' === $slug || empty( $data['name'] ) || empty( $data['fields'] ) || ! is_array( $data['fields'] ) ) {
            throw new \InvalidArgumentException( 'A definition requires a slug, name, and fields.' );
        }
        $data['slug'] = $slug;
        $ids = array();
        foreach ( $data['fields'] as $field ) {
            if ( ! is_array( $field ) || empty( $field['id'] ) || empty( $field['type'] ) || empty( $field['label'] ) ) { throw new \InvalidArgumentException( 'Every field needs an id, type, and label.' ); }
            $id = sanitize_key( $field['id'] );
            if ( isset( $ids[$id] ) ) { throw new \InvalidArgumentException( 'Field identifiers must be unique.' ); }
            $ids[$id] = true;
        }
        $this->data = $data;
    }
    public function get( $key = null, $default = null ) { return null === $key ? $this->data : ( isset( $this->data[$key] ) ? $this->data[$key] : $default ); }
    public function fields() { return $this->data['fields']; }
    public function field( $id ) { foreach ( $this->fields() as $field ) { if ( $field['id'] === $id ) { return $field; } } return null; }
}
