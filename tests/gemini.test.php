<?php
error_reporting( E_ALL );
define( 'WP_DEBUG', true );
require __DIR__ . '/bootstrap.php';

$tests = 0;
$failed = 0;
function gemini_test( $name, $callable ) {
    global $tests, $failed;
    $tests++;
    try { $callable(); echo "PASS $name\n"; }
    catch ( Throwable $error ) { $failed++; echo "FAIL $name: " . $error->getMessage() . "\n"; }
}
function gemini_assert( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }
function gemini_error( $value, $message ) { gemini_assert( is_wp_error( $value ), $message . ' should return WP_Error' ); }
function gemini_image_file( $width = 700, $height = 520 ) {
    $file = tempnam( sys_get_temp_dir(), 'rk-gemini-' );
    file_put_contents( $file, test_png( $width, $height ) );
    return $file;
}
function gemini_configure() {
    update_option( 'rk_ai_visualizer_settings', array(
        'enabled' => true,
        'provider' => 'gemini',
        'gemini_key' => 'fake-gemini-test-key',
        'gemini_model' => 'gemini-2.5-flash-image',
        'free_count' => 2,
        'bonus_count' => 1,
        'cooldown_hours' => 24,
        'ip_per_hour' => 10,
        'timeout' => 120,
    ) );
}
function gemini_reset_filter() { unset( $GLOBALS['wp_filters']['rk_ai_visualizer_http'] ); }

$image = gemini_image_file();
$image_bytes = file_get_contents( $image );
gemini_configure();

 gemini_test( 'successful Gemini image response matches the current generateContent contract', function () use ( $image, $image_bytes ) {
    $generated = base64_encode( test_png( 64, 64 ) );
    $GLOBALS['gemini_request'] = array();
    $GLOBALS['wp_filters']['rk_ai_visualizer_http'] = function ( $pre, $method, $url, $args ) use ( $generated ) {
        $GLOBALS['gemini_request'] = array( 'method' => $method, 'url' => $url, 'args' => $args );
        return array( 'code' => 200, 'body' => json_encode( array( 'candidates' => array( array( 'content' => array( 'parts' => array( array( 'text' => 'Done.' ), array( 'inlineData' => array( 'mimeType' => 'image/png', 'data' => $generated ) ) ) ) ) ) ) ) );
    };
    $result = \RK\AIVisualizer\Providers\Factory::create( 'gemini' )->generate( array( 'prompt' => 'Edit the room.', 'image_path' => $image, 'mime_type' => 'image/png', 'options' => array() ) );
    gemini_assert( is_array( $result ) && 'completed' === $result['status'], 'valid Gemini image response was not completed' );
    gemini_assert( 0 === strpos( $result['image_url'], 'https://cms.example.test/wp-content/uploads/rk-ai-visualizer/' ), 'stored Gemini result did not return an HTTPS image URL' );
    $request = $GLOBALS['gemini_request'];
    gemini_assert( 'POST' === $request['method'], 'Gemini request method changed' );
    gemini_assert( 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-image:generateContent' === $request['url'], 'Gemini 2.5 request must use the documented v1beta generateContent endpoint' );
    gemini_assert( 'fake-gemini-test-key' === $request['args']['headers']['x-goog-api-key'], 'Gemini API key header is missing or incorrect' );
    $body = json_decode( $request['args']['body'], true );
    gemini_assert( is_array( $body ) && isset( $body['contents'][0]['parts'][1]['inline_data'] ), 'Gemini image part is missing' );
    gemini_assert( 'image/png' === $body['contents'][0]['parts'][1]['inline_data']['mime_type'], 'Gemini image MIME type is incorrect' );
    gemini_assert( base64_encode( $image_bytes ) === $body['contents'][0]['parts'][1]['inline_data']['data'], 'Gemini image bytes were not base64 encoded as sent' );
    gemini_assert( array( 'TEXT', 'IMAGE' ) === $body['generationConfig']['responseModalities'], 'Gemini response modalities are incorrect' );
    gemini_reset_filter();
} );

gemini_test( 'Gemini HTTP 400, 401, and 403 responses become provider errors', function () use ( $image ) {
    foreach ( array( 400, 401, 403 ) as $status ) {
        $GLOBALS['wp_filters']['rk_ai_visualizer_http'] = function () use ( $status ) { return array( 'code' => $status, 'body' => json_encode( array( 'error' => array( 'status' => 'ERROR' ) ) ) ); };
        $result = \RK\AIVisualizer\Providers\Factory::create( 'gemini' )->generate( array( 'prompt' => 'Edit.', 'image_path' => $image, 'mime_type' => 'image/png', 'options' => array() ) );
        gemini_error( $result, 'Gemini HTTP ' . $status );
        gemini_assert( 'rk_viz_provider' === $result->get_error_code(), 'Gemini HTTP ' . $status . ' error code changed' );
    }
    gemini_reset_filter();
} );

gemini_test( 'WP_DEBUG logs safe Google error diagnostics without secrets or prompt data', function () use ( $image ) {
    $log = tempnam( sys_get_temp_dir(), 'rk-gemini-log-' );
    $previous_log = ini_get( 'error_log' );
    ini_set( 'error_log', $log );
    $secret = 'fake-gemini-test-key';
    $prompt = 'private room prompt that must not be logged';
    $GLOBALS['wp_filters']['rk_ai_visualizer_http'] = function () {
        return array( 'code' => 403, 'body' => json_encode( array( 'error' => array( 'code' => 403, 'status' => 'PERMISSION_DENIED', 'message' => 'The Gemini API key is not authorized.' ) ) ) );
    };
    try {
        $result = \RK\AIVisualizer\Providers\Factory::create( 'gemini' )->generate( array( 'prompt' => $prompt, 'image_path' => $image, 'mime_type' => 'image/png', 'options' => array() ) );
    } finally {
        gemini_reset_filter();
        ini_set( 'error_log', $previous_log );
    }
    gemini_error( $result, 'Gemini diagnostic response' );
    $logged = file_get_contents( $log );
    @unlink( $log );
    foreach ( array( 'http_status=403', 'google_status=PERMISSION_DENIED', 'google_code=403', 'google_message=The Gemini API key is not authorized.', 'model=gemini-2.5-flash-image', 'endpoint=https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-image:generateContent' ) as $expected ) {
        gemini_assert( false !== strpos( $logged, $expected ), 'WP_DEBUG log omitted ' . $expected );
    }
    gemini_assert( false === strpos( $logged, $secret ), 'WP_DEBUG log exposed the Gemini API key' );
    gemini_assert( false === strpos( $logged, $prompt ), 'WP_DEBUG log exposed the prompt' );
} );

gemini_test( 'malformed and image-less Gemini responses fail safely', function () use ( $image ) {
    foreach ( array(
        array( 'candidates' => array( 'not-an-object' ) ),
        array( 'candidates' => array( array( 'content' => array( 'parts' => array( array( 'text' => 'No image.' ) ) ) ) ) ),
    ) as $payload ) {
        $GLOBALS['wp_filters']['rk_ai_visualizer_http'] = function () use ( $payload ) { return array( 'code' => 200, 'body' => json_encode( $payload ) ); };
        gemini_error( \RK\AIVisualizer\Providers\Factory::create( 'gemini' )->generate( array( 'prompt' => 'Edit.', 'image_path' => $image, 'mime_type' => 'image/png', 'options' => array() ) ), 'malformed or image-less Gemini response' );
    }
    gemini_reset_filter();
} );

gemini_test( 'Gemini image storage failure becomes a provider error', function () use ( $image ) {
    $GLOBALS['wp_filters']['rk_ai_visualizer_http'] = function () { return array( 'code' => 200, 'body' => json_encode( array( 'candidates' => array( array( 'content' => array( 'parts' => array( array( 'inlineData' => array( 'mimeType' => 'image/png', 'data' => base64_encode( test_png( 64, 64 ) ) ) ) ) ) ) ) ) ) ); };
    $storage_blocker = tempnam( sys_get_temp_dir(), 'rk-storage-blocker-' );
    $old_upload_dir = $GLOBALS['wp_upload_test'];
    $GLOBALS['wp_upload_test'] = $storage_blocker;
    set_error_handler( function () {} );
    try {
        $result = \RK\AIVisualizer\Providers\Factory::create( 'gemini' )->generate( array( 'prompt' => 'Edit.', 'image_path' => $image, 'mime_type' => 'image/png', 'options' => array() ) );
    } finally {
        restore_error_handler();
        $GLOBALS['wp_upload_test'] = $old_upload_dir;
        @unlink( $storage_blocker );
        gemini_reset_filter();
    }
    gemini_error( $result, 'Gemini storage failure' );
} );

gemini_test( 'Engine refunds quota after a Gemini provider failure', function () use ( $image ) {
    gemini_configure();
    $cookie = str_repeat( 'a', 32 );
    $_COOKIE['rk_ai_viz'] = $cookie;
    $_SERVER['REMOTE_ADDR'] = '192.0.2.80';
    $GLOBALS['wp_filters']['rk_ai_visualizer_http'] = function () { return array( 'code' => 400, 'body' => '{"error":{"status":"INVALID_ARGUMENT"}}' ); };
    $result = ( new \RK\AIVisualizer\Core\Engine() )->generate( 'flooring', request_generate( $image ) );
    gemini_error( $result, 'Engine Gemini failure' );
    $key = 'rk_ai_viz_flooring_' . substr( hash( 'sha256', $cookie ), 0, 32 );
    gemini_assert( 0 === (int) get_transient( $key )['used'], 'quota was not refunded after Gemini provider failure' );
    gemini_reset_filter();
} );

@unlink( $image );
echo "\n$tests Gemini tests, $failed failures\n";
exit( $failed ? 1 : 0 );
