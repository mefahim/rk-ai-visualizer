<?php
namespace RK\AIVisualizer\Core;
use RK\AIVisualizer\Definitions\Registry;
use RK\AIVisualizer\Definitions\Definition;
use RK\AIVisualizer\Providers\Factory;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Engine {
    private $registry;
    public function __construct( ?Registry $registry = null ) { $this->registry=$registry?:Registry::instance(); }
    public function definition( $slug ) { return $this->registry->get(is_string($slug)?sanitize_key($slug):''); }
    private function settings() { $s=get_option('rk_ai_visualizer_settings',array()); $s=array_merge(array('enabled'=>false,'provider'=>'huggingface','free_count'=>2,'bonus_count'=>1,'cooldown_hours'=>24,'ip_per_hour'=>10,'timeout'=>120),is_array($s)?$s:array()); if(!is_string($s['provider']))$s['provider']=''; $s['timeout']=is_numeric($s['timeout'])?max(20,min(600,(int)$s['timeout'])):120; return $s; }
    private function providerReady( array $s ) { if (empty($s['enabled'])) return false; if ('mock'===$s['provider']) return true; if ('gemini'===$s['provider']) return ''!==\RK\AIVisualizer\Providers\Http::secret('gemini_key','RK_AIVIZ_GEMINI_KEY','GEMINI_API_KEY'); if ('huggingface'===$s['provider']) return ''!==\RK\AIVisualizer\Providers\Http::secret('hf_token','RK_AIVIZ_HF_TOKEN','HF_TOKEN'); if ('custom'===$s['provider']) return Storage::isSafeRemoteUrl(isset($s['custom_url'])?$s['custom_url']:''); return false; }
    private function visitorId() { $cookie=isset($_COOKIE['rk_ai_viz'])&&is_string($_COOKIE['rk_ai_viz'])?$_COOKIE['rk_ai_viz']:''; if (preg_match('/^[a-f0-9]{32}$/',$cookie)) return $cookie; $id=bin2hex(random_bytes(16)); $_COOKIE['rk_ai_viz']=$id; if (!headers_sent()) setcookie('rk_ai_viz',$id,array('expires'=>time()+365*86400,'path'=>'/','secure'=>is_ssl(),'httponly'=>true,'samesite'=>'Lax')); return $id; }
    private function stateKey( $slug,$vid ) { return 'rk_ai_viz_'.sanitize_key($slug).'_'.substr(hash('sha256',$vid),0,32); }
    private function state( $slug,$vid ) { return array_merge(array('used'=>0,'bonus_used'=>0,'lead'=>false,'next_at'=>0),($v=get_transient($this->stateKey($slug,$vid)))&&is_array($v)?$v:array()); }
    private function saveState( $slug,$vid,array $state ) { $key=$this->stateKey($slug,$vid); if (set_transient($key,$state,60*DAY_IN_SECONDS)) return true; $stored=get_transient($key); return is_array($stored)&&$stored===$state; }
    private function quotaConfig( Definition $d ) { $config=Quota::defaults($d->get('quota',array())); $saved=get_option('rk_ai_visualizer_settings',array()); if (!is_array($saved)) $saved=array(); foreach(array('free_count','bonus_count','cooldown_hours','ip_per_hour') as $key) if(isset($saved[$key])&&is_numeric($saved[$key])) $config[$key]=(int)$saved[$key]; return Quota::defaults($config); }
    private function ipAllowed( array $config ) { $remote=isset($_SERVER['REMOTE_ADDR'])&&is_string($_SERVER['REMOTE_ADDR'])?$_SERVER['REMOTE_ADDR']:''; $ip=filter_var($remote,FILTER_VALIDATE_IP)?$remote:'0.0.0.0'; $salt=function_exists('wp_salt')?wp_salt('auth'):''; $salt=is_string($salt)&&''!==$salt?$salt:__FILE__; $key='rk_ai_viz_ip_'.substr(hash_hmac('sha256',$ip,$salt),0,24); $r=get_transient($key); if (!is_array($r)||empty($r['start'])||time()-(int)$r['start']>=3600) $r=array('start'=>time(),'n'=>0); if ((int)$r['n'] >= (int)$config['ip_per_hour']) return false; $r['n']++; return (bool)set_transient($key,$r,3600); }
    private function getParam( $request,$key,$default=null ) { return method_exists($request,'get_param')?$request->get_param($key):$default; }
    private function publicQuota( array $q ) { return Quota::publicStatus($q); }
    public function quota( $slug ) { $d=$this->definition($slug); if (!$d) return Errors::make('rk_viz_definition','Unknown visualizer.',404); $slug=$d->get('slug'); $s=$this->settings(); if (!$this->providerReady($s)) return array('available'=>false); $vid=$this->visitorId(); $q=$this->publicQuota(Quota::status($this->state($slug,$vid),$this->quotaConfig($d))); return array_merge(array('available'=>true,'visualizer'=>$slug),$q); }
    public function generate( $slug,$request ) {
        $d=$this->definition($slug); if (!$d) return Errors::make('rk_viz_definition','Unknown visualizer.',404); $slug=$d->get('slug'); $s=$this->settings(); if (!$this->providerReady($s)) return Errors::make('rk_viz_unavailable','The visualizer isn\'t available right now.',503);
        $vid=$this->visitorId(); $now=time(); $config=$this->quotaConfig($d); $before=$this->state($slug,$vid); $q=Quota::status($before,$config,$now);
        if (!$q['canGenerate']) return $this->respond(array_merge(array('kind'=>'quota_denied'),$this->publicQuota($q)),429);
        if (!$this->ipAllowed($config)) return $this->respond(array('kind'=>'quota_denied','phase'=>'rate_limited','canGenerate'=>false,'freeRemaining'=>$q['freeRemaining'],'cooldownUntil'=>null,'hasLead'=>$q['hasLead'],'message'=>'Too many requests from your network just now. Please try again in a little while.'),429);
        $upload=Validation::upload($request,$d); if (is_wp_error($upload)) return $upload;
        $options=Validation::fields($this->getParam($request,'options'),$d); if (is_wp_error($options)) return $options;
        Storage::cleanup(); if (!$this->saveState($slug,$vid,Quota::consume($before,$q['phase'],$config,$now))) return Errors::make('rk_viz_quota_storage','The visualizer is temporarily unavailable. Please try again.',503);
        $provider=Factory::create($s['provider']); $result=is_wp_error($provider)?$provider:$provider->generate(array('prompt'=>Prompt::build($d,$options),'image_path'=>$upload['path'],'mime_type'=>$upload['mime'],'metadata'=>array('visualizer'=>$slug),'options'=>$options));
        if (is_wp_error($result)) { $this->saveState($slug,$vid,Quota::refund($this->state($slug,$vid),$q['phase'],$before)); return $result; }
        $after=$this->publicQuota(Quota::status($this->state($slug,$vid),$config,$now));
        if (is_array($result)&&isset($result['status'],$result['image_url'])&&'completed'===$result['status']&&Storage::isHttpsUrl($result['image_url'])) return $this->respond(array('status'=>'success','imageUrl'=>$result['image_url'],'quota'=>$after));
        if (!is_array($result)||!isset($result['status'],$result['job'])||'pending'!==$result['status']||!is_array($result['job'])||empty($result['job'])) { $this->saveState($slug,$vid,Quota::refund($this->state($slug,$vid),$q['phase'],$before)); return Errors::make('rk_viz_provider','The provider returned an invalid result.',502); }
        $id=bin2hex(random_bytes(12)); $job=array('slug'=>$slug,'provider'=>$s['provider'],'job'=>$result['job'],'owner'=>hash('sha256',$vid),'phase'=>$q['phase'],'before'=>$before,'started'=>$now);
        if (!set_transient('rk_ai_viz_job_'.$id,$job,3600)) { $this->saveState($slug,$vid,Quota::refund($this->state($slug,$vid),$q['phase'],$before)); return Errors::make('rk_viz_job_storage','The visualizer is temporarily unavailable. Please try again.',503); }
        return $this->respond(array('status'=>'pending','job'=>$id,'quota'=>$after));
    }
    public function status( $request,$expectedSlug=null ) {
        $jobParam=$this->getParam($request,'job',''); $id=is_string($jobParam)?$jobParam:''; $record=preg_match('/^[a-f0-9]{24}$/',$id)?get_transient('rk_ai_viz_job_'.$id):false; $vid=$this->visitorId();
        if (!is_array($record)||!isset($record['owner'],$record['slug'],$record['provider'],$record['job'],$record['phase'],$record['before'],$record['started'])||!is_string($record['owner'])||!is_string($record['slug'])||!is_string($record['provider'])||!is_array($record['job'])||!is_array($record['before'])||!is_numeric($record['started'])||!in_array($record['phase'],array('free','bonus','rolling'),true)||!hash_equals($record['owner'],hash('sha256',$vid))|| (null!==$expectedSlug&&$record['slug']!==$expectedSlug)) return Errors::make('rk_viz_no_job','That visualization isn\'t available any more. Please try again.',404);
        $s=$this->settings(); $provider=Factory::create($record['provider']); $result=is_wp_error($provider)?$provider:$provider->poll($record['job']);
        if (!is_wp_error($result)&&is_array($result)&&isset($result['status'])&&'pending'===$result['status']&&time()-(int)$record['started']<=(int)$s['timeout']) return $this->respond(array('status'=>'pending'));
        delete_transient('rk_ai_viz_job_'.$id);
        if (!is_wp_error($result)&&is_array($result)&&isset($result['status'],$result['image_url'])&&'completed'===$result['status']&&Storage::isHttpsUrl($result['image_url'])) return $this->respond(array('status'=>'success','imageUrl'=>$result['image_url']));
        $d=$this->definition($record['slug']); if ($d) { $state=$this->state($record['slug'],$vid); $this->saveState($record['slug'],$vid,Quota::refund($state,$record['phase'],$record['before'])); }
        if (!is_wp_error($result)&&time()-(int)$record['started']>(int)$s['timeout']) return Errors::make('rk_viz_timeout','The visualization is taking longer than expected. Please try again.',504);
        return Errors::make('rk_viz_provider','We couldn\'t generate the visualization right now. Please try again.',502);
    }
    public function lead( $slug,$request ) {
        $d=$this->definition($slug); if (!$d) return Errors::make('rk_viz_definition','Unknown visualizer.',404); $slug=$d->get('slug'); $s=$this->settings(); if (!$this->providerReady($s)) return Errors::make('rk_viz_unavailable','The visualizer isn\'t available right now.',503);
        $config=$this->quotaConfig($d); if (!$this->ipAllowed($config)) return Errors::make('rk_viz_rate','Too many requests. Please try again in a little while.',429);
        $body=method_exists($request,'get_json_params')?$request->get_json_params():array(); $validated=Leads::validate($body); if (isset($validated['errors'])) return new \WP_Error('rk_viz_lead_invalid','Please check the highlighted fields.',array('status'=>400,'fields'=>$validated['errors']));
        $vid=$this->visitorId(); $state=$this->state($slug,$vid); $lead=$validated['lead']; $generation=isset($body['generation'])&&is_string($body['generation'])?$body['generation']:''; $stored=Leads::store($lead,$slug,$generation); if (is_wp_error($stored)) return $stored; $state['lead']=true; if (!$this->saveState($slug,$vid,$state)) return Errors::make('rk_viz_lead_storage','Your contact information was saved, but unlocking is temporarily unavailable. Please try again.',503);
        return $this->respond(array_merge(array('ok'=>true,'visualizer'=>$slug),$this->publicQuota(Quota::status($state,$config))));
    }
    public function respond( array $body,$status=200 ) { $response=new \WP_REST_Response($body,$status); $response->header('Cache-Control','no-store, private'); return $response; }
}
