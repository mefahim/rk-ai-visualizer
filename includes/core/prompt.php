<?php
namespace RK\AIVisualizer\Core;
use RK\AIVisualizer\Definitions\Definition;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Prompt {
    public static function build( Definition $definition, array $values ) {
        $config = $definition->get( 'prompt', array() ); $parts = array();
        foreach ( (array) ( isset($config['base_rules']) ? $config['base_rules'] : array() ) as $rule ) { if ( is_string($rule) && '' !== trim($rule) ) { $parts[] = trim($rule); } }
        $maps = isset($config['transformations']) && is_array($config['transformations']) ? $config['transformations'] : array(); $replacements = array();
        foreach ( $maps as $field => $choices ) { if ( isset($values[$field]) && array_key_exists($values[$field],$choices) ) { $replacements['{'.$field.'}'] = (string)$choices[$values[$field]]; } }
        foreach ( (array) ( isset($config['transformation_rules']) ? $config['transformation_rules'] : array() ) as $rule ) {
            preg_match_all('/\{([A-Za-z0-9_]+)\}/',(string)$rule,$matches);
            $resolved=0; foreach($matches[1] as $field) { if (isset($replacements['{'.$field.'}']) && ''!==$replacements['{'.$field.'}']) $resolved++; }
            if ($matches[1] && 0===$resolved) { continue; }
            $line = strtr((string)$rule,$replacements);
            $line = preg_replace('/\{[A-Za-z0-9_]+\}/','',$line);
            $line = preg_replace('/\s*,\s*,+/',',',$line);
            $line = preg_replace('/\s+,\s*/',', ',$line);
            $line = preg_replace('/,\s*\./','.',$line);
            $line = preg_replace('/\s+\./','.',$line);
            $line = preg_replace('/\s+(?:and|or)\s*\./i','.',$line);
            $line = preg_replace('/\s+(?:and|or)\s*$/i','',$line);
            $line = trim($line," \t\n\r\0\x0B,.");
            if (''!==$line) { $parts[] = $line.'.'; }
        }
        $customId = isset($config['custom_field']) ? $config['custom_field'] : '';
        if ( $customId && ! empty($values[$customId]) ) { $parts[] = 'Custom user instruction: ' . sanitize_textarea_field($values[$customId]); }
        foreach ( (array) ( isset($config['preservation_rules']) ? $config['preservation_rules'] : array() ) as $rule ) { if ( is_string($rule) && '' !== trim($rule) ) { $parts[] = trim($rule); } }
        return implode("\n\n", $parts);
    }
}
