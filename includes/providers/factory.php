<?php
namespace RK\AIVisualizer\Providers;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Factory {
    public static function create( $name ) {
        switch ($name) {
            case 'mock': return new MockProvider();
            case 'gemini': return new GeminiProvider();
            case 'huggingface': return new HuggingFaceProvider();
            case 'custom': return new CustomProvider();
            default: return new \WP_Error('rk_viz_unavailable','Unknown visualization provider.',array('status'=>503));
        }
    }
}
