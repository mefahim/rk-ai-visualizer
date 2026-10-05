<?php
namespace RK\AIVisualizer\Providers;
use RK\AIVisualizer\Core\Storage;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class GeminiProvider implements ProviderInterface {
    public function generate( array $input ) {
        $key=Http::secret('gemini_key','RK_AIVIZ_GEMINI_KEY','GEMINI_API_KEY'); if (''===$key) { return new \WP_Error('rk_viz_unavailable','Gemini is not configured.',array('status'=>503)); }
        $model=(string)Http::config('gemini_model','gemini-2.5-flash-image'); if (!preg_match('/^[A-Za-z0-9._-]{1,80}$/',$model)) { $model='gemini-2.5-flash-image'; }
        $bytes=@file_get_contents($input['image_path']); if (!is_string($bytes)) { return new \WP_Error('rk_viz_bad_image','The uploaded image could not be read.',array('status'=>400)); }
        $url='https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($model).':generateContent';
        $response=Http::request('POST',$url,array('timeout'=>(int)Http::config('timeout',120),'headers'=>array('Content-Type'=>'application/json','x-goog-api-key'=>$key),'body'=>wp_json_encode(array('contents'=>array(array('parts'=>array(array('text'=>$input['prompt']),array('inline_data'=>array('mime_type'=>$input['mime_type'],'data'=>base64_encode($bytes)))))),'generationConfig'=>array('responseModalities'=>array('TEXT','IMAGE'))))));
        if (is_wp_error($response)) { return new \WP_Error('rk_viz_provider','The visualization provider could not be reached.',array('status'=>502)); }
        $json=Http::json($response); if ($response['code']<200||$response['code']>=300||!is_array($json)) { self::debugError( $response['code'], $json, $model, $url ); return new \WP_Error('rk_viz_provider','The visualization provider returned an error.',array('status'=>502)); }
        $data=''; $candidates=isset($json['candidates'])&&is_array($json['candidates'])?$json['candidates']:array(); foreach ($candidates as $candidate) { if (!is_array($candidate)||!isset($candidate['content'])||!is_array($candidate['content'])||!isset($candidate['content']['parts'])||!is_array($candidate['content']['parts'])) { continue; } foreach ($candidate['content']['parts'] as $part) { if (!is_array($part)) { continue; } $inline=isset($part['inlineData'])&&is_array($part['inlineData'])?$part['inlineData']:(isset($part['inline_data'])&&is_array($part['inline_data'])?$part['inline_data']:array()); if (isset($inline['data'])&&is_string($inline['data'])) { $data=$inline['data']; break 2; } } }
        $stored=Storage::storeImage($data); if (''===$stored) { return new \WP_Error('rk_viz_provider','The provider did not return a supported image.',array('status'=>502)); }
        return array('status'=>'completed','image_url'=>$stored,'provider'=>'gemini');
    }
    private static function debugError( $http_status, $json, $model, $endpoint ) {
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) { return; }
        $error = is_array( $json ) && isset( $json['error'] ) && is_array( $json['error'] ) ? $json['error'] : array();
        $status = isset( $error['status'] ) && is_scalar( $error['status'] ) ? (string) $error['status'] : '';
        $code = isset( $error['code'] ) && is_scalar( $error['code'] ) ? (string) $error['code'] : '';
        $message = isset( $error['message'] ) && is_scalar( $error['message'] ) ? (string) $error['message'] : '';
        $message = str_replace( array( "\r", "\n" ), ' ', substr( $message, 0, 500 ) );
        error_log( 'RK AI Visualizer Gemini error: http_status=' . (int) $http_status . ' google_status=' . $status . ' google_code=' . $code . ' google_message=' . $message . ' model=' . $model . ' endpoint=' . $endpoint );
    }
    public function poll( array $job ) { return new \WP_Error('rk_viz_no_job','Gemini completes synchronously.',array('status'=>404)); }
}
