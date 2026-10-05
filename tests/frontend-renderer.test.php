<?php
require __DIR__ . '/bootstrap.php';

use RK\AIVisualizer\Definitions\Definition;
use RK\AIVisualizer\Definitions\Registry;
use RK\AIVisualizer\Frontend\Renderer;
use RK\AIVisualizer\Core\Prompt;
use RK\AIVisualizer\Core\Validation;

$tests = 0;
$failed = 0;
$run = function ( $name, $test ) use ( &$tests, &$failed ) {
    $tests++;
    try {
        $test();
        echo "PASS $name\n";
    } catch ( Throwable $error ) {
        $failed++;
        echo 'FAIL ' . $name . ': ' . $error->getMessage() . "\n";
    }
};

$run( 'public shortcode uses the definition upload rules and unique accessible IDs', function () {
    $first = Renderer::shortcode( array( 'visualizer' => 'flooring', 'cities' => "Peoria\nPeoria Heights" ) );
    $second = Renderer::shortcode( array( 'visualizer' => 'flooring' ) );
    t_assert( false !== strpos( $first, 'class="rkaiviz rk-ai-visualizer"' ), 'plugin root class missing' );
    t_assert( false !== strpos( $first, 'data-max-size="10485760"' ), 'definition upload-size limit missing' );
    t_assert( false !== strpos( $first, 'data-min-width="640"' ), 'definition minimum width missing' );
    t_assert( false !== strpos( $first, 'data-min-height="480"' ), 'definition minimum height missing' );
    t_assert( false !== strpos( $first, 'data-allowed-types="image/jpeg,image/png,image/webp"' ), 'definition MIME allow-list missing' );
    t_assert( false !== strpos( $first, 'Up to 10 MB' ), 'visible size guidance missing' );
    t_assert( false !== strpos( $first, 'Minimum 640 × 480 px' ), 'visible dimension guidance missing' );
    t_assert( false !== strpos( $first, 'value="peoria_heights">Peoria Heights</option>' ), 'cities shortcode option was not generated' );
    t_assert( false !== strpos( $first, 'data-viz-file-status role="status" aria-live="polite"' ), 'upload status announcement missing' );
    t_assert( false !== strpos( $first, 'data-viz-lead aria-labelledby=' ), 'lead dialog accessible naming missing' );
    preg_match( '/<section id="([^"]+)" class="rkaiviz rk-ai-visualizer"/', $first, $first_id );
    preg_match( '/<section id="([^"]+)" class="rkaiviz rk-ai-visualizer"/', $second, $second_id );
    t_assert( ! empty( $first_id[1] ) && ! empty( $second_id[1] ), 'shortcode instance IDs missing' );
    t_assert( $first_id[1] !== $second_id[1], 'repeated shortcode IDs collide' );
    t_assert( false !== strpos( $first, 'data-visible-field="preferredStyle" data-visible-equals="custom" hidden' ), 'Flooring conditional visibility metadata missing' );
    t_assert( false !== strpos( $first, 'data-required-field="preferredStyle" data-required-equals="custom"' ), 'conditional required metadata missing' );
} );

$run( 'public renderer respects field labels, defaults, help, patterns, and supported controls generically', function () {
    $definition = new Definition( array(
        'slug' => 'frontend-control-showcase',
        'name' => 'Control Showcase',
        'description' => 'A generic definition used to verify public control rendering.',
        'upload' => array( 'max_size' => 5242880, 'min_width' => 320, 'min_height' => 240, 'allowed_types' => array( 'image/jpeg', 'image/png' ) ),
        'fields' => array(
            array( 'id' => 'textValue', 'label' => 'Text value', 'type' => 'text', 'default' => 'A "quoted" value', 'placeholder' => 'Type here', 'help' => 'Keep it local & simple.', 'pattern' => '/^[A-Za-z ]+$/' ),
            array( 'id' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'default' => 'Preserve the walls & windows.', 'max_length' => 80 ),
            array( 'id' => 'quantity', 'label' => 'Quantity', 'type' => 'number', 'min' => 1, 'max' => 9, 'step' => 1, 'default' => 4, 'input_mode' => 'numeric' ),
            array( 'id' => 'finish', 'label' => 'Finish', 'type' => 'select', 'required' => true, 'default' => 'second', 'options' => array( 'first' => 'First option', 'second' => 'Second option' ) ),
            array( 'id' => 'style', 'label' => 'Style', 'type' => 'radio', 'required' => true, 'default' => 'classic', 'options' => array( 'classic' => 'Classic', 'modern' => 'Modern' ) ),
            array( 'id' => 'layout', 'label' => 'Layout', 'type' => 'cards', 'required' => true, 'options' => array( 'one' => 'One room', 'two' => 'Two rooms' ) ),
            array( 'id' => 'color', 'label' => 'Color', 'type' => 'swatches', 'required' => true, 'options' => array( '#afad99' => 'Stone', 'navy' => 'Navy' ) ),
            array( 'id' => 'materialChoice', 'label' => 'Material', 'type' => 'image', 'required' => true, 'default' => 'natural', 'options' => array( 'natural' => 'Natural oak', 'dark' => 'Dark walnut' ), 'images' => array( 'natural' => '/wp-content/uploads/natural-oak.jpg', 'dark' => 'https://cdn.example.test/dark-walnut.jpg' ) ),
            array( 'id' => 'enabled', 'label' => 'Enable option', 'type' => 'toggle', 'default' => true ),
            array( 'id' => 'scale', 'label' => 'Scale', 'type' => 'slider', 'min' => 0, 'max' => 10, 'step' => 2, 'default' => 6 ),
        ),
    ) );
    Registry::instance()->register( $definition );
    $html = Renderer::shortcode( array( 'visualizer' => 'frontend-control-showcase' ) );
    foreach ( array( 'rkaiviz-field--text', 'rkaiviz-field--textarea', 'rkaiviz-field--number', 'rkaiviz-field--select', 'rkaiviz-field--radio', 'rkaiviz-field--cards', 'rkaiviz-field--swatches', 'rkaiviz-field--image', 'rkaiviz-field--toggle', 'rkaiviz-field--slider' ) as $class ) {
        t_assert( false !== strpos( $html, $class ), 'supported control not rendered: ' . $class );
    }
    t_assert( false !== strpos( $html, 'value="A &quot;quoted&quot; value"' ), 'text default or attribute escaping missing' );
    t_assert( false !== strpos( $html, 'Keep it local &amp; simple.' ), 'help text escaping or rendering missing' );
    t_assert( false !== strpos( $html, 'pattern="^[A-Za-z ]+$"' ), 'safe HTML pattern rendering missing' );
    t_assert( false !== strpos( $html, 'Preserve the walls &amp; windows.' ), 'textarea default or escaping missing' );
    t_assert( false !== strpos( $html, 'min="1" max="9" step="1"' ), 'number constraints missing' );
    t_assert( false !== strpos( $html, 'value="second" selected' ), 'select default missing' );
    t_assert( false !== strpos( $html, 'value="classic" required aria-required="true" checked' ), 'radio default or required state missing' );
    t_assert( false !== strpos( $html, 'style="--rkaiviz-swatch:#afad99"' ), 'explicit hex swatch metadata missing' );
    t_assert( false !== strpos( $html, 'class="rkaiviz-image-grid"' ), 'image choice visual grid missing' );
    t_assert( false !== strpos( $html, 'src="/wp-content/uploads/natural-oak.jpg" alt="" loading="lazy" decoding="async"' ), 'image choice thumbnail or lazy-loading attributes missing' );
    t_assert( false !== strpos( $html, 'value="natural" required aria-required="true" checked' ), 'image choice must submit its scalar option value and respect defaults' );
    t_assert( false !== strpos( $html, 'type="checkbox" value="1" checked' ), 'toggle default missing' );
    t_assert( false !== strpos( $html, 'type="range" min="0" max="10" step="2" value="6"' ), 'slider constraints/default missing' );
    t_assert( false !== strpos( $html, 'data-allowed-types="image/jpeg,image/png"' ), 'custom upload allow-list was not reflected in markup' );
} );

$run( 'image choice fields preserve scalar validation and definition prompt mapping', function () {
    $data = array(
        'slug' => 'image-field-check',
        'name' => 'Image Field Check',
        'prompt' => array( 'transformations' => array( 'material' => array( 'natural' => 'natural oak grain', 'dark' => 'deep walnut grain' ) ), 'transformation_rules' => array( 'Use {material} in the room.' ) ),
        'fields' => array( array( 'id' => 'material', 'label' => 'Material', 'type' => 'image', 'required' => true, 'options' => array( 'natural' => 'Natural oak', 'dark' => 'Dark walnut' ), 'images' => array( 'natural' => '/assets/natural.jpg', 'dark' => 'https://cdn.example.test/dark.jpg' ) ) ),
    );
    $definition = new Definition( $data );
    Registry::instance()->register( $definition );
    $html = Renderer::shortcode( array( 'visualizer' => 'image-field-check' ) );
    t_assert( false !== strpos( $html, 'rkaiviz-choice--image' ), 'image option card missing' );
    $valid = Validation::fields( array( 'material' => 'natural' ), $definition );
    t_eq( $valid['material'], 'natural', 'image field should keep its existing scalar option value' );
    t_error( Validation::fields( array( 'material' => 'unknown' ), $definition ), 'rk_viz_bad_options', 'unknown image option' );
    t_assert( false !== strpos( Prompt::build( $definition, $valid ), 'natural oak grain' ), 'image option should continue through the existing definition prompt mapping' );
    $missing_images = $data;
    unset( $missing_images['fields'][0]['images'] );
    try {
        new Definition( $missing_images );
        throw new RuntimeException( 'image option without thumbnails should be rejected' );
    } catch ( InvalidArgumentException $error ) {
        t_assert( false !== strpos( $error->getMessage(), 'one image URL' ), 'missing image source was rejected for the expected reason' );
    }
    $data['fields'][0]['images']['natural'] = 'javascript:alert(1)';
    try {
        new Definition( $data );
        throw new RuntimeException( 'unsafe image option URL should be rejected' );
    } catch ( InvalidArgumentException $error ) {
        t_assert( false !== strpos( $error->getMessage(), 'Image choice URLs' ), 'unsafe image source was rejected for the expected reason' );
    }
    $data['fields'][0]['images']['natural'] = 'https://user:pass@cdn.example.test/natural.jpg';
    try {
        new Definition( $data );
        throw new RuntimeException( 'credentialed image URL should be rejected' );
    } catch ( InvalidArgumentException $error ) {
        t_assert( false !== strpos( $error->getMessage(), 'Image choice URLs' ), 'credentialed image source was rejected for the expected reason' );
    }
} );

echo "\n$tests public renderer tests, $failed failures\n";
exit( $failed ? 1 : 0 );
