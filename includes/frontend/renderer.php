<?php
namespace RK\AIVisualizer\Frontend;
use RK\AIVisualizer\Definitions\Registry;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Renderer {
    public static function shortcode( $atts = array() ) {
        $atts=shortcode_atts(array('visualizer'=>'flooring','cities'=>'','cta_label'=>'','cta_url'=>'','submit_label'=>''),$atts,'rk_ai_visualizer');
        $definition=Registry::instance()->get(sanitize_key($atts['visualizer'])); if (!$definition) return '<p>'.esc_html__('This visualizer is not available.','rk-ai-visualizer').'</p>';
        wp_enqueue_script('rk-ai-visualizer'); wp_enqueue_style('rk-ai-visualizer');
        $fields=$definition->fields(); foreach ($fields as &$field) { if (isset($field['options_source'])&&'cities'===$field['options_source']) { $field['options']=array(); foreach(preg_split('/\R/',(string)$atts['cities']) as $label) { $label=trim($label); if (''!==$label) { $slug=trim(preg_replace('/[^a-z0-9]+/','_',strtolower($label)),'_'); $field['options'][$slug]=$label; } } } } unset($field);
        $copy=$definition->get('copy',array()); $submitLabel=''!==trim($atts['submit_label'])?$atts['submit_label']:(isset($copy['submit_label'])?$copy['submit_label']:'Generate'); $base=rest_url('rk-ai/v1/visualizer/'.rawurlencode($definition->get('slug')).'/');
        $html='<section class="rkaiviz" data-rkaiviz="" data-visualizer="'.esc_attr($definition->get('slug')).'" data-api="'.esc_url($base).'">';
        $html.='<form class="rkaiviz-form" data-viz-form="" enctype="multipart/form-data">';
        $html.='<label class="rkaiviz-upload"><span>'.esc_html(isset($copy['upload_label'])?$copy['upload_label']:'Upload an image').'</span><input type="file" name="image" accept="image/jpeg,image/png,image/webp" required></label><p role="alert" data-viz-error="" hidden></p>';
        foreach ($fields as $field) { if (!empty($field['display_if_options'])&&empty($field['options'])) continue; $html.=self::field($field); }
        $html.='<p aria-live="polite" data-viz-quota=""></p><button type="submit" data-viz-submit="">'.esc_html($submitLabel).'</button></form>';
        $html.='<aside class="rkaiviz-preview"><div class="rkaiviz-stage"><img alt="" data-viz-img="" hidden><p data-viz-empty="">'.esc_html(isset($copy['preview_empty'])?$copy['preview_empty']:'Upload an image to preview.').'</p><div data-viz-busy="" hidden>'.esc_html(isset($copy['busy'])?$copy['busy']:'Generating…').'</div></div><div data-viz-done="" hidden><p>'.esc_html(isset($copy['result_disclaimer'])?$copy['result_disclaimer']:'AI-generated concept preview.').'</p><img data-viz-orig="" alt="Original photo"></div>';
        $ctaLabel=$atts['cta_label']?:($definition->get('cta',array())['label']??''); $ctaUrl=$atts['cta_url']?:($definition->get('cta',array())['url']??'');
        if ($ctaLabel&&$ctaUrl) { $html.='<p><a href="'.esc_url($ctaUrl).'">'.esc_html($ctaLabel).'</a></p>'; }
        $html.='</aside><dialog data-viz-lead=""><button type="button" data-viz-close="" aria-label="Close">×</button><form data-viz-leadform=""><h2>Unlock one more visualization</h2><p>Share your contact details and we will unlock another free visualization.</p><label>Name<input name="name" maxlength="120" required></label><label>Email<input name="email" type="email" required></label><label>Phone (optional)<input name="phone" type="tel" maxlength="30"></label><p role="alert" data-viz-lead-error="" hidden></p><button type="submit">Unlock my visualization</button></form></dialog></section>';
        return $html;
    }
    private static function conditionValue( $value ) { return is_bool($value)?($value?'1':'0'):(string)$value; }
    private static function field( array $field ) {
        $id=$field['id']; $name=esc_attr($id); $label=esc_html($field['label']); $required=!empty($field['required'])?' required':''; $attrs=' name="'.$name.'"'.$required;
        $condition=''; if (!empty($field['visible_when'])) { $condition=' data-visible-field="'.esc_attr($field['visible_when']['field']).'" data-visible-equals="'.esc_attr(self::conditionValue($field['visible_when']['equals'])).'" hidden'; }
        if (!empty($field['required_when'])) { $condition.=' data-required-field="'.esc_attr($field['required_when']['field']).'" data-required-equals="'.esc_attr(self::conditionValue($field['required_when']['equals'])).'"'; }
        $h='<div class="rkaiviz-field"'.$condition.'><label for="rkaiviz-'.$name.'">'.$label.'</label>';
        if (in_array($field['type'],array('select','radio','cards','swatches'),true)) {
            if ('select'===$field['type']) { $h.='<select id="rkaiviz-'.$name.'"'.$attrs.'>'; if (empty($field['required'])) $h.='<option value="">Select…</option>'; foreach((array)$field['options'] as $v=>$text) $h.='<option value="'.esc_attr($v).'">'.esc_html($text).'</option>'; $h.='</select>'; }
            else { foreach((array)$field['options'] as $v=>$text) $h.='<label class="rkaiviz-choice"><input type="radio"'.$attrs.' value="'.esc_attr($v).'"><span>'.esc_html($text).'</span></label>'; }
        } elseif ('textarea'===$field['type']) { $h.='<textarea id="rkaiviz-'.$name.'"'.$attrs.' maxlength="'.(int)(isset($field['max_length'])?$field['max_length']:500).'" placeholder="'.esc_attr(isset($field['placeholder'])?$field['placeholder']:'').'"></textarea>'; }
        else { $type=in_array($field['type'],array('number','toggle','slider','text'),true)?$field['type']:'text'; $h.='<input id="rkaiviz-'.$name.'" type="'.esc_attr('toggle'===$type?'checkbox':('slider'===$type?'range':$type)).'"'.$attrs.(isset($field['min'])?' min="'.(int)$field['min'].'"':'').(isset($field['max'])?' max="'.(int)$field['max'].'"':'').(isset($field['default'])?' value="'.esc_attr($field['default']).'"':'').'>'; }
        if (!empty($field['help'])) $h.='<small>'.esc_html($field['help']).'</small>'; return $h.'</div>';
    }
}
