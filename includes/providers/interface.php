<?php
namespace RK\AIVisualizer\Providers;
if ( ! defined( 'ABSPATH' ) ) { exit; }
interface ProviderInterface {
    /** Input: prompt, image_path, mime_type, metadata, options. Output: normalized result array or WP_Error. */
    public function generate( array $input );
    /** Poll a normalized provider job. */
    public function poll( array $job );
}
