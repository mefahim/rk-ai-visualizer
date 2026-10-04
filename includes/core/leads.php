<?php
namespace RK\AIVisualizer\Core;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Leads {
    const OPTION = 'rk_ai_visualizer_leads';
    public static function validate( $input ) {
        $in=is_array($input)?$input:array(); $name=isset($in['name'])&&is_string($in['name'])?trim($in['name']):''; $email=isset($in['email'])&&is_string($in['email'])?strtolower(trim($in['email'])):''; $phone=isset($in['phone'])&&is_string($in['phone'])?trim($in['phone']):''; $errors=array();
        if (''===$name) { $errors['name']='Please enter your name.'; } elseif (strlen($name)>120) { $errors['name']='Please shorten your name to 120 characters or fewer.'; }
        if (''===$email) { $errors['email']='Please enter your email.'; } elseif (!is_email($email)||strlen($email)>200) { $errors['email']='Please enter a valid email address.'; }
        if (''!==$phone) { if (strlen($phone)>30) { $errors['phone']='Please shorten your phone number to 30 characters or fewer.'; } elseif (strlen(preg_replace('/[^0-9]/','',$phone))<7) { $errors['phone']='Please enter a valid phone number, or leave it blank.'; } }
        return $errors?array('errors'=>$errors):array('lead'=>array('name'=>sanitize_text_field($name),'email'=>$email,'phone'=>sanitize_text_field($phone)));
    }
    public static function all() { $all=get_option(self::OPTION,array()); return is_array($all)?$all:array(); }
    public static function store( array $lead, $visualizer, $generation = '' ) {
        $all=self::all(); $isNew=true; foreach ($all as $i=>$existing) { if (isset($existing['email']) && strtolower($existing['email'])===$lead['email']) { unset($all[$i]); $isNew=false; } }
        $ip=isset($_SERVER['REMOTE_ADDR'])&&filter_var($_SERVER['REMOTE_ADDR'],FILTER_VALIDATE_IP)?$_SERVER['REMOTE_ADDR']:'';
        array_unshift($all,array_merge($lead,array('visualizer'=>sanitize_key($visualizer),'generation'=>sanitize_text_field($generation),'at'=>gmdate('c'),'ip'=>$ip)));
        update_option(self::OPTION,array_slice(array_values($all),0,500),false); return $isNew;
    }
}
