<?php
namespace RK\AIVisualizer\Admin;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Settings {
    public static function init() { add_action('admin_menu',array(__CLASS__,'menu')); add_action('admin_init',array(__CLASS__,'register')); }
    public static function menu() { add_options_page('RK AI Visualizer','RK AI Visualizer','manage_options','rk-ai-visualizer',array(__CLASS__,'render')); }
    public static function register() { register_setting('rk_ai_visualizer','rk_ai_visualizer_settings',array('sanitize_callback'=>array(__CLASS__,'sanitize'))); }
    public static function sanitize( $input ) {
        $old=get_option('rk_ai_visualizer_settings',array()); $old=is_array($old)?$old:array(); $in=is_array($input)?$input:array(); $out=array_merge(array('enabled'=>false,'provider'=>'huggingface','hf_token'=>'','gemini_key'=>'','gemini_model'=>'gemini-2.5-flash-image','custom_url'=>'','custom_key'=>'','custom_header'=>'Authorization','free_count'=>2,'bonus_count'=>1,'cooldown_hours'=>24,'ip_per_hour'=>10,'timeout'=>120),$old);
        $out['enabled']=!empty($in['enabled']); $out['provider']=in_array(isset($in['provider'])?$in['provider']:'',array('mock','gemini','huggingface','custom'),true)?$in['provider']:'huggingface';
        foreach(array('hf_token','gemini_key','custom_key') as $secret){if(!empty($in['clear_'.$secret]))$out[$secret]='';elseif(isset($in[$secret])&&''!==trim((string)$in[$secret]))$out[$secret]=sanitize_text_field($in[$secret]);}
        $url=isset($in['custom_url'])?trim((string)$in['custom_url']):''; $out['custom_url']=preg_match('#^https://[^\s]+$#i',$url)?esc_url_raw($url):'';
        $model=isset($in['gemini_model'])?trim((string)$in['gemini_model']):''; $out['gemini_model']=preg_match('/^[A-Za-z0-9._-]{1,80}$/',$model)?$model:'gemini-2.5-flash-image';
        $hdr=isset($in['custom_header'])?trim((string)$in['custom_header']):''; $out['custom_header']=preg_match('/^[A-Za-z0-9-]{1,60}$/',$hdr)?$hdr:'Authorization';
        foreach(array('free_count'=>array(0,20,2),'bonus_count'=>array(0,20,1),'cooldown_hours'=>array(1,720,24),'ip_per_hour'=>array(1,1000,10),'timeout'=>array(20,600,120)) as $key=>$spec){$n=isset($in[$key])&&is_numeric($in[$key])?(int)$in[$key]:$spec[2];$out[$key]=max($spec[0],min($spec[1],$n));}
        return $out;
    }
    public static function render() { if(!current_user_can('manage_options'))return; $s=array_merge(array('enabled'=>false,'provider'=>'huggingface','hf_token'=>'','gemini_key'=>'','gemini_model'=>'gemini-2.5-flash-image','custom_url'=>'','custom_key'=>'','custom_header'=>'Authorization','free_count'=>2,'bonus_count'=>1,'cooldown_hours'=>24,'ip_per_hour'=>10,'timeout'=>120),get_option('rk_ai_visualizer_settings',array())); $n='rk_ai_visualizer_settings'; echo '<div class="wrap"><h1>RK AI Visualizer</h1><form method="post" action="options.php">'; settings_fields('rk_ai_visualizer');
        self::checkbox($n,'enabled','Enable visualizer',$s); self::select($n,'provider','Provider',$s,array('mock'=>'Mock (no API cost)','gemini'=>'Google Gemini','huggingface'=>'Hugging Face','custom'=>'Custom backend')); self::secret($n,'gemini_key','Gemini API key',$s); self::text($n,'gemini_model','Gemini model',$s); self::secret($n,'hf_token','Hugging Face token',$s); self::text($n,'custom_url','Custom HTTPS backend URL',$s); self::secret($n,'custom_key','Custom backend key',$s); self::text($n,'custom_header','Custom key header',$s);
        foreach(array('free_count'=>'Free generations','bonus_count'=>'Lead bonus generations','cooldown_hours'=>'Cooldown (hours)','ip_per_hour'=>'Requests per IP per hour','timeout'=>'Async timeout (seconds)') as $k=>$label) self::number($n,$k,$label,$s);
        submit_button(); echo '</form><hr><p>Shortcode: <code>[rk_ai_visualizer visualizer="flooring"]</code>. Leads are stored in the WordPress options table. Keep backups and restrict database access.</p></div>'; }
    private static function name($n,$k){return $n.'['.$k.']';}
    private static function text($n,$k,$label,$s){echo '<p><label>'.esc_html($label).'<br><input class="regular-text" type="text" name="'.esc_attr(self::name($n,$k)).'" value="'.esc_attr(isset($s[$k])?$s[$k]:'').'"></label></p>';}
    private static function secret($n,$k,$label,$s){echo '<p><label>'.esc_html($label).' (saved value is not displayed)<br><input class="regular-text" type="password" autocomplete="new-password" name="'.esc_attr(self::name($n,$k)).'" value=""></label> <label><input type="checkbox" name="'.esc_attr(self::name($n,'clear_'.$k)).'" value="1"> Clear</label></p>';}
    private static function number($n,$k,$label,$s){echo '<p><label>'.esc_html($label).'<br><input type="number" name="'.esc_attr(self::name($n,$k)).'" value="'.esc_attr((int)$s[$k]).'"></label></p>';}
    private static function checkbox($n,$k,$label,$s){echo '<p><label><input type="checkbox" name="'.esc_attr(self::name($n,$k)).'" value="1" '.checked(!empty($s[$k]),true,false).'> '.esc_html($label).'</label></p>';}
    private static function select($n,$k,$label,$s,$options){echo '<p><label>'.esc_html($label).'<br><select name="'.esc_attr(self::name($n,$k)).'">';foreach($options as $value=>$text)echo '<option value="'.esc_attr($value).'" '.selected($s[$k],$value,false).'>'.esc_html($text).'</option>';echo '</select></label></p>';}
}
