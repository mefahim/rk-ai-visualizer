<?php
namespace RK\AIVisualizer\Core;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Leads {
    const OPTION = 'rk_ai_visualizer_leads';
    public static function validate( $input ) {
        $in=is_array($input)?$input:array(); $name=isset($in['name'])&&is_string($in['name'])?trim($in['name']):''; $email=isset($in['email'])&&is_string($in['email'])?strtolower(trim($in['email'])):''; $phone=isset($in['phone'])&&is_string($in['phone'])?trim($in['phone']):''; $errors=array();
        if (''===$name) { $errors['name']='Please enter your name.'; } elseif (strlen($name)>120) { $errors['name']='Please shorten your name to 120 characters or fewer.'; }
        if (''===$email) { $errors['email']='Please enter your email.'; } elseif (!is_email($email)||strlen($email)>200) { $errors['email']='Please enter a valid email address.'; }
        if (''!==$phone) { $digits=preg_replace('/[^0-9]/','',$phone); if (strlen($phone)>30) { $errors['phone']='Please shorten your phone number to 30 characters or fewer.'; } elseif (!preg_match('/^[0-9+().\s-]+$/',$phone)||strlen($digits)<7||strlen($digits)>15) { $errors['phone']='Please enter a valid phone number, or leave it blank.'; } }
        return $errors?array('errors'=>$errors):array('lead'=>array('name'=>sanitize_text_field($name),'email'=>$email,'phone'=>sanitize_text_field($phone)));
    }
    public static function all() { $all=get_option(self::OPTION,array()); return is_array($all)?$all:array(); }
    public static function store( array $lead, $visualizer, $generation = '' ) {
        $all=self::all(); $isNew=true; foreach ($all as $i=>$existing) { if (isset($existing['email']) && strtolower($existing['email'])===$lead['email']) { unset($all[$i]); $isNew=false; } }
        array_unshift($all,array_merge($lead,array('visualizer'=>sanitize_key($visualizer),'generation'=>substr(sanitize_text_field($generation),0,128),'at'=>gmdate('c'))));
        $next=array_slice(array_values($all),0,500); $saved=update_option(self::OPTION,$next,false);
        if (!$saved&&self::all()!==$next) return Errors::make('rk_viz_lead_storage','The contact information could not be saved. Please try again.',503);
        return $isNew;
    }
}
