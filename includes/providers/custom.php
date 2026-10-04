<?php
namespace RK\AIVisualizer\Providers;
use RK\AIVisualizer\Core\Storage;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class CustomProvider implements ProviderInterface {
    private function authHeaders() { $h=array('Accept'=>'application/json','Content-Type'=>'application/json'); $key=(string)Http::config('custom_key',''); $name=(string)Http::config('custom_header','Authorization'); if (''!==$key&&preg_match('/^[A-Za-z0-9-]{1,60}$/',$name)) { $h[$name]='Authorization'===$name?'Bearer '.$key:$key; } return $h; }
    private function allowedStatus( $url ) { $root=(string)Http::config('custom_url',''); $a=wp_parse_url($root); $b=wp_parse_url((string)$url); return Storage::isHttpsUrl($url)&&is_array($a)&&is_array($b)&&strtolower(isset($a['host'])?$a['host']:'')===strtolower(isset($b['host'])?$b['host']:''); }
    public function generate( array $input ) {
        $url=(string)Http::config('custom_url',''); if (!Storage::isHttpsUrl($url)) { return new \WP_Error('rk_viz_unavailable','The custom provider needs an HTTPS URL.',array('status'=>503)); }
        $bytes=@file_get_contents($input['image_path']); $payload=array('prompt'=>$input['prompt'],'image'=>'data:'.$input['mime_type'].';base64,'.base64_encode((string)$bytes),'mimeType'=>$input['mime_type'],'options'=>$input['options']);
        $res=Http::request('POST',$url,array('timeout'=>(int)Http::config('timeout',120),'headers'=>$this->authHeaders(),'body'=>wp_json_encode($payload))); $j=Http::json($res);
        if (is_wp_error($res)||!is_array($j)||$res['code']<200||$res['code']>=300) { return new \WP_Error('rk_viz_provider','The custom provider returned an error.',array('status'=>502)); }
        return $this->normalize($j);
    }
    private function normalize( array $j ) {
        $state=strtolower(isset($j['status'])?(string)$j['status']:'');
        foreach (array('imageUrl','image_url','url') as $k) { if (isset($j[$k])&&Storage::isHttpsUrl($j[$k])) { return array('status'=>'completed','image_url'=>$j[$k],'provider'=>'custom'); } }
        foreach (array('image','b64_json','imageBase64') as $k) { if (isset($j[$k])&&is_string($j[$k])) { $url=Storage::storeImage($j[$k]); if (''!==$url) { return array('status'=>'completed','image_url'=>$url,'provider'=>'custom'); } } }
        if (isset($j['images'][0]['url'])&&Storage::isHttpsUrl($j['images'][0]['url'])) { return array('status'=>'completed','image_url'=>$j['images'][0]['url'],'provider'=>'custom'); }
        $status=isset($j['statusUrl'])?$j['statusUrl']:(isset($j['status_url'])?$j['status_url']:'');
        if ($this->allowedStatus($status)||in_array($state,array('pending','processing','queued','running','in_progress'),true)) { if (!$this->allowedStatus($status)) { return new \WP_Error('rk_viz_provider','The custom provider returned an unsafe status URL.',array('status'=>502)); } return array('status'=>'pending','provider'=>'custom','job'=>array('status_url'=>$status)); }
        return new \WP_Error('rk_viz_provider','The custom provider returned no image or job.',array('status'=>502));
    }
    public function poll( array $job ) { if (empty($job['status_url'])||!$this->allowedStatus($job['status_url'])) { return new \WP_Error('rk_viz_provider','Invalid custom-provider job URL.',array('status'=>502)); }
        $res=Http::request('GET',$job['status_url'],array('timeout'=>20,'headers'=>$this->authHeaders())); if (is_wp_error($res)) { return array('status'=>'pending'); } $j=Http::json($res); if (!is_array($j)) { return new \WP_Error('rk_viz_provider','Invalid custom-provider response.',array('status'=>502)); }
        if (in_array(strtolower(isset($j['status'])?(string)$j['status']:''),array('pending','processing','queued','running','in_progress'),true)) { return array('status'=>'pending'); }
        return $this->normalize($j); }
}
