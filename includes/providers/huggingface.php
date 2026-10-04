<?php
namespace RK\AIVisualizer\Providers;
use RK\AIVisualizer\Core\Storage;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class HuggingFaceProvider implements ProviderInterface {
    private function token() { return Http::secret('hf_token','RK_AIVIZ_HF_TOKEN','HF_TOKEN'); }
    private function routerUrl( $url ) { if(!is_string($url))return ''; $p=wp_parse_url($url); if (!is_array($p)||empty($p['host'])||empty($p['path'])||!preg_match('/(^|\.)fal\.run$/i',$p['host'])||isset($p['user'])||isset($p['pass'])) { return ''; } return 'https://router.huggingface.co/fal-ai'.$p['path'].'?_subdomain=queue'; }
    private function isRouterUrl( $url ) { if(!is_string($url))return false; $p=wp_parse_url($url); return Storage::isHttpsUrl($url)&&is_array($p)&&isset($p['host'],$p['path'])&&'router.huggingface.co'===strtolower($p['host'])&&1===preg_match('#^/fal-ai/[A-Za-z0-9._/-]+$#',$p['path'])&&false===strpos($p['path'],'..'); }
    private function headers() { return array('Authorization'=>'Bearer '.$this->token(),'Content-Type'=>'application/json'); }
    public function generate( array $input ) {
        if (''===$this->token()) { return new \WP_Error('rk_viz_unavailable','Hugging Face is not configured.',array('status'=>503)); }
        $bytes=@file_get_contents($input['image_path']); if (!is_string($bytes)||''===$bytes) return new \WP_Error('rk_viz_bad_image','The uploaded image could not be read.',array('status'=>400)); $endpoint='https://router.huggingface.co/fal-ai/fal-ai/flux-kontext/dev?_subdomain=queue';
        $response=Http::request('POST',$endpoint,array('timeout'=>45,'headers'=>$this->headers(),'body'=>wp_json_encode(array('prompt'=>$input['prompt'],'image_url'=>'data:'.$input['mime_type'].';base64,'.base64_encode((string)$bytes)))));
        $json=Http::json($response); if (is_wp_error($response)||!is_array($json)||$response['code']<200||$response['code']>=300) { return new \WP_Error('rk_viz_provider','The visualization provider could not start the job.',array('status'=>502)); }
        $status=$this->routerUrl(isset($json['status_url'])?$json['status_url']:''); $result=$this->routerUrl(isset($json['response_url'])?$json['response_url']:'');
        if (''===$status||''===$result) { return new \WP_Error('rk_viz_provider','The provider returned an invalid job URL.',array('status'=>502)); }
        return array('status'=>'pending','provider'=>'huggingface','job'=>array('status_url'=>$status,'response_url'=>$result));
    }
    public function poll( array $job ) {
        if (empty($job['status_url'])||empty($job['response_url'])||!$this->isRouterUrl($job['status_url'])||!$this->isRouterUrl($job['response_url'])) { return new \WP_Error('rk_viz_provider','Invalid provider job.',array('status'=>502)); }
        $response=Http::request('GET',$job['status_url'],array('timeout'=>20,'headers'=>$this->headers())); if (is_wp_error($response)) { return array('status'=>'pending'); } if (!isset($response['code'])||$response['code']<200||$response['code']>=300) return new \WP_Error('rk_viz_provider','The provider status request failed.',array('status'=>502));
        $state=Http::json($response); if (!is_array($state)||!isset($state['status'])||!is_string($state['status'])) { return new \WP_Error('rk_viz_provider','Invalid provider status response.',array('status'=>502)); }
        $providerStatus=strtoupper($state['status']);
        if (in_array($providerStatus,array('FAILED','ERROR'),true)) { return new \WP_Error('rk_viz_provider','The image generation failed.',array('status'=>502)); }
        if ('COMPLETED'!==$providerStatus) { return array('status'=>'pending'); }
        $done=Http::request('GET',$job['response_url'],array('timeout'=>30,'headers'=>$this->headers())); if (is_wp_error($done)||!isset($done['code'])||$done['code']<200||$done['code']>=300) return new \WP_Error('rk_viz_provider','The provider result request failed.',array('status'=>502)); $out=Http::json($done); $images=is_array($out)&&isset($out['images'])&&is_array($out['images'])?$out['images']:array(); $first=isset($images[0])&&is_array($images[0])?$images[0]:array(); $url=isset($first['url'])&&is_string($first['url'])?$first['url']:'';
        return Storage::isSafeRemoteUrl($url)?array('status'=>'completed','image_url'=>$url):new \WP_Error('rk_viz_provider','The provider returned no valid image URL.',array('status'=>502));
    }
}
