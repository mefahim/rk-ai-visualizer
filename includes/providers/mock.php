<?php
namespace RK\AIVisualizer\Providers;
use RK\AIVisualizer\Core\Storage;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class MockProvider implements ProviderInterface {
    public function generate( array $input ) { $bytes=@file_get_contents($input['image_path']); $url=Storage::storeImage(is_string($bytes)?$bytes:''); return ''===$url?new \WP_Error('rk_viz_provider','The mock provider could not store the uploaded image.',array('status'=>502)):array('status'=>'completed','image_url'=>$url,'provider'=>'mock'); }
    public function poll( array $job ) { return new \WP_Error('rk_viz_no_job','The mock provider does not create asynchronous jobs.',array('status'=>404)); }
}
