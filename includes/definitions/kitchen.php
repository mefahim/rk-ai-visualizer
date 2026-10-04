<?php
namespace RK\AIVisualizer\Definitions;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Kitchen {
    public static function definition() {
        $select = function ( $id, $label, array $options ) { return array( 'id' => $id, 'label' => $label, 'type' => 'select', 'required' => true, 'options' => $options ); };
        return new Definition( array(
            'slug' => 'kitchen',
            'name' => 'Kitchen Visualizer',
            'description' => 'Explore coordinated kitchen finishes while keeping the existing room structure and camera view.',
            'upload' => array( 'allowed_types' => array( 'image/jpeg', 'image/png', 'image/webp' ), 'max_size' => 10485760, 'min_width' => 640, 'min_height' => 480 ),
            'fields' => array(
                $select( 'roomType', 'Room type', array(
                    'residential' => 'Residential kitchen',
                    'apartment' => 'Apartment kitchen',
                    'open_plan' => 'Open-plan kitchen',
                    'compact' => 'Compact / secondary kitchen',
                ) ),
                $select( 'kitchenLayout', 'Kitchen layout', array(
                    'existing' => 'Keep the existing layout',
                    'galley' => 'Galley',
                    'l_shaped' => 'L-shaped',
                    'u_shaped' => 'U-shaped',
                    'island' => 'Island',
                    'peninsula' => 'Peninsula',
                ) ),
                $select( 'cabinetStyle', 'Cabinet style', array(
                    'shaker' => 'Shaker',
                    'flat_panel' => 'Flat panel',
                    'slab' => 'Slab / contemporary',
                    'inset' => 'Inset',
                    'traditional' => 'Traditional raised panel',
                    'custom' => 'Custom style',
                ) ),
                $select( 'cabinetColor', 'Cabinet color', array(
                    'warm_white' => 'Warm white',
                    'soft_white' => 'Soft white',
                    'natural_oak' => 'Natural oak',
                    'walnut' => 'Walnut',
                    'sage' => 'Muted sage green',
                    'navy' => 'Deep navy',
                    'charcoal' => 'Charcoal',
                    'light_gray' => 'Light gray',
                ) ),
                $select( 'countertopMaterial', 'Countertop material', array(
                    'quartz' => 'Quartz',
                    'marble' => 'Marble',
                    'granite' => 'Granite',
                    'porcelain' => 'Porcelain slab',
                    'butcher_block' => 'Butcher block',
                    'concrete' => 'Concrete',
                ) ),
                $select( 'countertopColor', 'Countertop color', array(
                    'bright_white' => 'Bright white',
                    'veined_white' => 'White with subtle veining',
                    'warm_beige' => 'Warm beige',
                    'pale_gray' => 'Pale gray',
                    'charcoal' => 'Charcoal',
                    'natural_wood' => 'Natural wood tone',
                ) ),
                $select( 'backsplashStyle', 'Backsplash style', array(
                    'subway' => 'Subway tile',
                    'stacked' => 'Stacked tile',
                    'herringbone' => 'Herringbone tile',
                    'mosaic' => 'Mosaic tile',
                    'full_height' => 'Full-height slab',
                    'minimal' => 'Minimal / no added backsplash',
                ) ),
                $select( 'backsplashMaterial', 'Backsplash material', array(
                    'ceramic' => 'Ceramic',
                    'porcelain' => 'Porcelain',
                    'glass' => 'Glass',
                    'natural_stone' => 'Natural stone',
                    'quartz_slab' => 'Quartz slab',
                    'none' => 'No added material',
                ) ),
                $select( 'backsplashColor', 'Backsplash color', array(
                    'warm_white' => 'Warm white',
                    'crisp_white' => 'Crisp white',
                    'soft_gray' => 'Soft gray',
                    'sage' => 'Muted sage',
                    'blue' => 'Blue',
                    'natural_stone' => 'Natural stone tones',
                ) ),
                $select( 'flooring', 'Kitchen flooring', array(
                    'existing' => 'Keep existing flooring',
                    'oak' => 'Light oak hardwood',
                    'porcelain_tile' => 'Porcelain tile',
                    'natural_stone' => 'Natural stone',
                    'lvp' => 'Light oak luxury vinyl plank',
                    'concrete' => 'Polished concrete',
                ) ),
                $select( 'hardwareFinish', 'Cabinet hardware finish', array(
                    'brushed_brass' => 'Brushed brass',
                    'brushed_nickel' => 'Brushed nickel',
                    'matte_black' => 'Matte black',
                    'aged_bronze' => 'Aged bronze',
                    'polished_chrome' => 'Polished chrome',
                ) ),
                $select( 'applianceStyle', 'Appliance style', array(
                    'stainless' => 'Stainless steel',
                    'black_stainless' => 'Black stainless steel',
                    'integrated' => 'Integrated / panel-ready',
                    'professional' => 'Professional stainless steel',
                    'white' => 'White appliances',
                ) ),
                $select( 'lightingPreference', 'Lighting preference', array(
                    'warm_layered' => 'Warm layered lighting',
                    'recessed_pendants' => 'Recessed lights with island pendants',
                    'under_cabinet' => 'Under-cabinet task lighting',
                    'statement_pendants' => 'Statement pendants',
                    'preserve' => 'Keep the existing lighting style',
                ) ),
                array(
                    'id' => 'customDesignInstructions',
                    'label' => 'Custom cabinet style and design instructions',
                    'type' => 'textarea',
                    'required' => false,
                    'visible_when' => array( 'field' => 'cabinetStyle', 'equals' => 'custom' ),
                    'required_when' => array( 'field' => 'cabinetStyle', 'equals' => 'custom' ),
                    'required_message' => 'Please describe the custom cabinet style and design details.',
                    'max_length' => 600,
                    'placeholder' => 'Describe the custom door profile, details, or other design direction.',
                    'help' => 'Required when Custom style is selected. Keep structural changes within the existing room.',
                ),
            ),
            'prompt' => array(
                'base_rules' => array(
                    'Create a photorealistic kitchen renovation concept from the uploaded room photo.',
                    'Treat every selected option as the requested finish and style direction, applying it visibly to the corresponding kitchen surfaces and fixtures.',
                ),
                'transformations' => array(
                    'roomType' => array(
                        'residential' => 'a residential kitchen',
                        'apartment' => 'an apartment kitchen',
                        'open_plan' => 'an open-plan kitchen',
                        'compact' => 'a compact secondary kitchen',
                    ),
                    'kitchenLayout' => array(
                        'existing' => 'the existing cabinet and work-zone arrangement',
                        'galley' => 'a galley cabinet and work-zone arrangement',
                        'l_shaped' => 'an L-shaped cabinet and work-zone arrangement',
                        'u_shaped' => 'a U-shaped cabinet and work-zone arrangement',
                        'island' => 'an island-centered cabinet and work-zone arrangement',
                        'peninsula' => 'a peninsula cabinet and work-zone arrangement',
                    ),
                    'cabinetStyle' => array(
                        'shaker' => 'Shaker-style cabinet fronts',
                        'flat_panel' => 'flat-panel cabinet fronts',
                        'slab' => 'clean slab cabinet fronts',
                        'inset' => 'inset cabinet fronts',
                        'traditional' => 'traditional raised-panel cabinet fronts',
                        'custom' => 'the custom cabinet style described by the user',
                    ),
                    'cabinetColor' => array(
                        'warm_white' => 'warm white', 'soft_white' => 'soft white', 'natural_oak' => 'natural oak', 'walnut' => 'walnut',
                        'sage' => 'muted sage green', 'navy' => 'deep navy', 'charcoal' => 'charcoal', 'light_gray' => 'light gray',
                    ),
                    'countertopMaterial' => array(
                        'quartz' => 'quartz', 'marble' => 'marble', 'granite' => 'granite', 'porcelain' => 'porcelain slab',
                        'butcher_block' => 'butcher block', 'concrete' => 'concrete',
                    ),
                    'countertopColor' => array(
                        'bright_white' => 'bright white', 'veined_white' => 'white with subtle natural veining', 'warm_beige' => 'warm beige',
                        'pale_gray' => 'pale gray', 'charcoal' => 'charcoal', 'natural_wood' => 'natural wood tones',
                    ),
                    'backsplashStyle' => array(
                        'subway' => 'subway-tile', 'stacked' => 'stacked-tile', 'herringbone' => 'herringbone-tile',
                        'mosaic' => 'mosaic-tile', 'full_height' => 'full-height slab', 'minimal' => 'minimal with no added backsplash',
                    ),
                    'backsplashMaterial' => array(
                        'ceramic' => 'ceramic', 'porcelain' => 'porcelain', 'glass' => 'glass', 'natural_stone' => 'natural stone',
                        'quartz_slab' => 'quartz slab', 'none' => 'no added backsplash material',
                    ),
                    'backsplashColor' => array(
                        'warm_white' => 'warm white', 'crisp_white' => 'crisp white', 'soft_gray' => 'soft gray',
                        'sage' => 'muted sage', 'blue' => 'blue', 'natural_stone' => 'natural stone tones',
                    ),
                    'flooring' => array(
                        'existing' => 'the existing floor finish', 'oak' => 'light oak hardwood', 'porcelain_tile' => 'porcelain tile',
                        'natural_stone' => 'natural stone flooring', 'lvp' => 'light oak luxury vinyl plank', 'concrete' => 'polished concrete',
                    ),
                    'hardwareFinish' => array(
                        'brushed_brass' => 'brushed brass', 'brushed_nickel' => 'brushed nickel', 'matte_black' => 'matte black',
                        'aged_bronze' => 'aged bronze', 'polished_chrome' => 'polished chrome',
                    ),
                    'applianceStyle' => array(
                        'stainless' => 'stainless-steel', 'black_stainless' => 'black stainless-steel', 'integrated' => 'integrated panel-ready',
                        'professional' => 'professional stainless-steel', 'white' => 'white-finish',
                    ),
                    'lightingPreference' => array(
                        'warm_layered' => 'warm layered lighting', 'recessed_pendants' => 'recessed lighting with island pendants',
                        'under_cabinet' => 'under-cabinet task lighting', 'statement_pendants' => 'statement pendant lighting',
                        'preserve' => 'the existing lighting style',
                    ),
                ),
                'transformation_rules' => array(
                    'Design {roomType} using {kitchenLayout}, contained within the original room footprint.',
                    'Update visible cabinet fronts to {cabinetStyle} in {cabinetColor}.',
                    'Use {countertopMaterial} countertops in {countertopColor}.',
                    'Apply a {backsplashStyle} backsplash using {backsplashMaterial} in {backsplashColor} tones.',
                    'Use {flooring} and {hardwareFinish} cabinet hardware.',
                    'Style visible appliances as {applianceStyle} and use {lightingPreference}.',
                ),
                'custom_field' => 'customDesignInstructions',
                'preservation_rules' => array(
                    'Only the selected cabinet fronts/colors, countertops, backsplash, flooring, hardware finish, visible appliance finish, lighting fixtures, and cabinet/island arrangement may change. Keep any layout changes within the existing room footprint.',
                    'Preserve the original room architecture and envelope, including wall and ceiling geometry, structural features, doors, windows, and openings. Do not enlarge or reshape the room.',
                    'Preserve the original camera position, lens, crop, perspective, composition, and room scale. Keep fixed plumbing and major appliances in their original locations; do not change window, door, or structural-opening positions.',
                    'Maintain realistic material scale, grain, seams, reflections, lighting direction, and shadows. Do not add unrelated objects or remove existing non-kitchen elements.',
                ),
            ),
            'cta' => array( 'label' => 'Plan my kitchen', 'url' => '' ),
            'copy' => array(
                'upload_label' => 'Upload a kitchen photo',
                'submit_label' => 'Create kitchen visualization',
                'preview_empty' => 'Upload a kitchen photo to preview your space here.',
                'busy' => 'Creating your kitchen visualization…',
                'result_disclaimer' => 'Your kitchen visualization is an AI-assisted design concept, not a construction-ready plan or guaranteed material/color match.',
            ),
            'quota' => array( 'free_count' => 2, 'bonus_count' => 1, 'cooldown_hours' => 24, 'ip_per_hour' => 10 ),
        ) );
    }
}
