<?php
namespace RK\AIVisualizer\Definitions;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Flooring {
    public static function definition() {
        $select = function ( $id, $label, array $options, $required = true ) { return array( 'id' => $id, 'label' => $label, 'type' => 'radio', 'required' => $required, 'options' => $options ); };
        return new Definition( array(
            'slug' => 'flooring', 'name' => 'Flooring Visualizer',
            'description' => 'Explore a photorealistic hardwood flooring concept in your room.',
            'upload' => array( 'allowed_types' => array( 'image/jpeg', 'image/png', 'image/webp' ), 'max_size' => 10485760, 'min_width' => 640, 'min_height' => 480 ),
            'fields' => array(
                $select( 'roomType', 'Room type', array( 'kitchen'=>'Kitchen','living_room'=>'Living room','hallway'=>'Hallway','bedroom'=>'Bedroom','office'=>'Office','retail_gym'=>'Retail / gym' ) ),
                $select( 'projectType', 'Project type', array( 'new_installation'=>'New installation','refinish_existing_floor'=>'Refinish existing floor','sandless_refresh'=>'Sandless refresh','commercial_sports'=>'Commercial / sports','deck'=>'Deck','cabinets'=>'Cabinets' ) ),
                $select( 'preferredStyle', 'Preferred style', array( 'light_natural'=>'Light / natural','warm_traditional'=>'Warm / traditional','gray_weathered'=>'Gray / weathered','dark_modern'=>'Dark / modern','custom'=>'Custom' ) ),
                array( 'id'=>'customStyleDescription','label'=>'Describe the style you want','type'=>'textarea','required'=>false,'required_when'=>array( 'field'=>'preferredStyle','equals'=>'custom' ),'required_message'=>'Please describe the style you want.','max_length'=>500,'placeholder'=>'Warm medium-brown oak with a natural, matte finish','visible_when'=>array( 'field'=>'preferredStyle','equals'=>'custom' ) ),
                $select( 'woodSpecies', 'Wood species', array( 'oak'=>'Oak','maple'=>'Maple','hickory'=>'Hickory','mixed_unsure'=>'Mixed / Unsure','existing_floor'=>'Existing Floor' ) ),
                $select( 'floorDirection', 'Floor direction', array( 'parallel'=>'Parallel','perpendicular'=>'Perpendicular','diagonal'=>'Diagonal','herringbone'=>'Herringbone','existing_direction'=>'Existing Direction','unsure'=>'Unsure' ) ),
                $select( 'finishPreference', 'Finish preference', array( 'bona_traffic_hd'=>'Bona Traffic HD','rubio_monocoat'=>'Rubio Monocoat','polyurethane'=>'Polyurethane','unsure'=>'Unsure' ) ),
                $select( 'sheen', 'Sheen', array( 'matte'=>'Matte','satin'=>'Satin','semi_gloss'=>'Semi-gloss','unsure'=>'Unsure' ) ),
                array( 'id'=>'serviceCity','label'=>'Service city (optional)','type'=>'select','required'=>false,'options'=>array(),'options_source'=>'cities','display_if_options'=>true,'pattern'=>'/^[a-z0-9_]{1,60}$/','help'=>'Optional service area.' ),
                array( 'id'=>'squareFootage','label'=>'Approximate square footage (optional)','type'=>'number','required'=>false,'min'=>0,'max'=>1000000,'default'=>0,'input_mode'=>'numeric' ),
            ),
            'prompt' => array(
                'base_rules' => array( 'Edit the uploaded room photo to create a photorealistic hardwood flooring visualization.' ),
                'transformations' => array(
                    'woodSpecies' => array( 'oak'=>'natural white oak hardwood','maple'=>'light maple hardwood','hickory'=>'hickory hardwood with natural color variation','mixed_unsure'=>'natural-toned hardwood','existing_floor'=>"hardwood that matches the existing floor's species" ),
                    'preferredStyle' => array( 'light_natural'=>'a light, natural tone','warm_traditional'=>'a warm, traditional medium-brown tone','gray_weathered'=>'a gray, weathered tone','dark_modern'=>'a dark, modern tone' ),
                    'floorDirection' => array( 'parallel'=>'laid parallel to the longest wall','perpendicular'=>'laid perpendicular to the longest wall','diagonal'=>'laid in a diagonal pattern','herringbone'=>'laid in a herringbone pattern','existing_direction'=>'following the same direction as the existing floor' ),
                    'sheen' => array( 'matte'=>'a matte sheen','satin'=>'a satin sheen','semi_gloss'=>'a semi-gloss sheen' ),
                    'finishPreference' => array( 'bona_traffic_hd'=>'a hard-wearing waterborne finish','rubio_monocoat'=>'a natural hardwax oil finish','polyurethane'=>'a polyurethane finish' ),
                ),
                'transformation_rules' => array( 'Replace ONLY the existing visible floor with realistic {woodSpecies}, in {preferredStyle}, {floorDirection}.', 'Give the floor {sheen} and {finishPreference}.' ),
                'preservation_rules' => array( 'Preserve the exact room architecture, furniture, rug, walls, windows, doors, fireplace, decorations, lighting, shadows, reflections, camera position, perspective, and composition.', 'Do not redesign or regenerate the room.', 'The new hardwood floor must follow the existing floor plane, perspective, vanishing points, boundaries, and geometry naturally.', 'Keep all non-floor objects unchanged.', 'The final image should look like a professionally photographed real room after the hardwood flooring installation.', 'Do not add objects. Do not remove objects. Do not change the furniture. Do not change the walls. Do not change the lighting. Do not change the camera perspective.' ),
                'custom_field' => 'customStyleDescription', 'custom_applies_when' => array( 'field'=>'preferredStyle','equals'=>'custom' ),
            ),
            'cta' => array( 'label'=>'Talk with us','url'=>'' ),
            'copy' => array( 'upload_label'=>'Upload a room photo','submit_label'=>'Create visualization','preview_empty'=>'Upload a room photo to preview your space here.','busy'=>'Creating your floor visualization…','result_disclaimer'=>'Your visualization concept is ready. This is an AI-assisted concept to help you explore ideas — not an exact rendering, a guaranteed color match, or a construction-ready plan.' ),
            'quota' => array( 'free_count'=>2,'bonus_count'=>1,'cooldown_hours'=>24,'ip_per_hour'=>10 ),
        ) );
    }
}
