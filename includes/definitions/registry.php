<?php
namespace RK\AIVisualizer\Definitions;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Registry {
    private static $instance;
    private $definitions = array();
    public static function instance() { if ( ! self::$instance ) { self::$instance = new self(); } return self::$instance; }
    public function register( Definition $definition ) {
        $slug = $definition->get( 'slug' );
        if ( isset( $this->definitions[$slug] ) ) { throw new \InvalidArgumentException( 'A visualizer definition with this slug is already registered.' ); }
        $this->definitions[$slug] = $definition;
        return true;
    }
    public function get( $slug ) { return isset( $this->definitions[$slug] ) ? $this->definitions[$slug] : null; }
    public function all() { return $this->definitions; }
    public function clear() { $this->definitions = array(); }
}
