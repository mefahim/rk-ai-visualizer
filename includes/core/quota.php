<?php
namespace RK\AIVisualizer\Core;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Quota {
    public static function defaults( array $config = array() ) { $defaults=array('free_count'=>2,'bonus_count'=>1,'cooldown_hours'=>24,'ip_per_hour'=>10); $c=array_merge($defaults,$config); foreach(array('free_count'=>array(0,20),'bonus_count'=>array(0,20),'cooldown_hours'=>array(1,720),'ip_per_hour'=>array(1,1000)) as $key=>$bounds) { $n=isset($c[$key])&&is_numeric($c[$key])?(int)$c[$key]:$defaults[$key]; $c[$key]=max($bounds[0],min($bounds[1],$n)); } return $c; }
    private static function normalizeState( array $state ) { $s=array_merge(array('used'=>0,'bonus_used'=>0,'lead'=>false,'next_at'=>0),$state); $s['used']=max(0,(int)$s['used']); $s['bonus_used']=max(0,(int)$s['bonus_used']); $s['lead']=(bool)$s['lead']; $s['next_at']=max(0,(int)$s['next_at']); return $s; }
    public static function status( array $state, array $config, $now = null ) {
        $now = null === $now ? time() : (int)$now; $c=self::defaults($config); $state=self::normalizeState($state);
        $q=array('phase'=>'free','canGenerate'=>false,'freeRemaining'=>max(0,(int)$c['free_count']-(int)$state['used']),'bonusRemaining'=>max(0,(int)$c['bonus_count']-(int)$state['bonus_used']),'cooldownUntil'=>null,'hasLead'=>(bool)$state['lead']);
        if ((int)$state['used'] < (int)$c['free_count']) { $q['canGenerate']=true; return $q; }
        if (!$state['lead'] && (int)$c['bonus_count']>0) { $q['phase']='needs_lead'; return $q; }
        if ($state['lead'] && (int)$state['bonus_used']<(int)$c['bonus_count']) { $q['phase']='bonus'; $q['canGenerate']=true; return $q; }
        if ((int)$state['next_at'] <= $now) { $q['phase']='rolling'; $q['canGenerate']=true; return $q; }
        $q['phase']='cooldown'; $q['cooldownUntil']=gmdate('c',(int)$state['next_at']); return $q;
    }
    public static function consume( array $state, $phase, array $config, $now = null ) {
        $now=null===$now?time():(int)$now; $c=self::defaults($config); $state=self::normalizeState($state); $cool=(int)$c['cooldown_hours']*3600;
        if ('free'===$phase) { $state['used']++; if ((int)$c['bonus_count']<1 && $state['used'] >= (int)$c['free_count']) { $state['next_at']=$now+$cool; } }
        elseif ('bonus'===$phase) { $state['bonus_used']++; if ($state['bonus_used'] >= (int)$c['bonus_count']) { $state['next_at']=$now+$cool; } }
        elseif ('rolling'===$phase) { $state['next_at']=$now+$cool; }
        return $state;
    }
    public static function refund( array $spent, $phase, array $before ) {
        $spent=self::normalizeState($spent); $before=self::normalizeState($before);
        if ('free'===$phase) { $spent['used']=max(0,(int)$spent['used']-1); }
        if ('bonus'===$phase) { $spent['bonus_used']=max(0,(int)$spent['bonus_used']-1); }
        $spent['next_at']=(int)$before['next_at']; return $spent;
    }
    public static function message( array $q ) {
        if ('free'===$q['phase']) { return $q['freeRemaining'].' free visualization'.(1===$q['freeRemaining']?'':'s').' remaining.'; }
        if ('needs_lead'===$q['phase']) { return "You've used your free visualizations — share your contact info to unlock more."; }
        if ('bonus'===$q['phase']) { return 'You have '.$q['bonusRemaining'].' more free visualization'.(1===$q['bonusRemaining']?'':'s').' available.'; }
        if ('rolling'===$q['phase']) { return 'You have 1 free visualization available today.'; }
        return "You've used today's free visualizations. Talk with us now, or come back later.";
    }
    public static function publicStatus( array $q ) { $q['message']=self::message($q); return $q; }
}
