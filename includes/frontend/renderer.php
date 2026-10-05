<?php
namespace RK\AIVisualizer\Frontend;

use RK\AIVisualizer\Definitions\Registry;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Renderer {
    private static $render_count = 0;

    public static function shortcode( $atts = array() ) {
        $atts = shortcode_atts( array( 'visualizer' => 'flooring', 'cities' => '', 'cta_label' => '', 'cta_url' => '', 'submit_label' => '' ), $atts, 'rk_ai_visualizer' );
        $definition = Registry::instance()->get( sanitize_key( $atts['visualizer'] ) );
        if ( ! $definition ) { return '<p>' . esc_html__( 'This visualizer is not available.', 'rk-ai-visualizer' ) . '</p>'; }

        wp_enqueue_script( 'rk-ai-visualizer' );
        wp_enqueue_style( 'rk-ai-visualizer' );
        $fields = $definition->fields();
        foreach ( $fields as &$field ) {
            if ( isset( $field['options_source'] ) && 'cities' === $field['options_source'] ) {
                $field['options'] = array();
                foreach ( preg_split( '/\R/', (string) $atts['cities'] ) as $label ) {
                    $label = trim( $label );
                    if ( '' !== $label ) {
                        $city_slug = trim( preg_replace( '/[^a-z0-9]+/', '_', strtolower( $label ) ), '_' );
                        $field['options'][ $city_slug ] = $label;
                    }
                }
            }
        }
        unset( $field );

        $copy = $definition->get( 'copy', array() );
        $upload = $definition->get( 'upload', array() );
        $max_size = isset( $upload['max_size'] ) ? (int) $upload['max_size'] : 10485760;
        if ( function_exists( 'wp_max_upload_size' ) ) {
            $wp_limit = (int) wp_max_upload_size();
            if ( $wp_limit > 0 ) { $max_size = min( $max_size, $wp_limit ); }
        }
        $allowed_types = isset( $upload['allowed_types'] ) && is_array( $upload['allowed_types'] ) ? $upload['allowed_types'] : array( 'image/jpeg', 'image/png', 'image/webp' );
        $allowed_types = array_values( array_intersect( $allowed_types, array( 'image/jpeg', 'image/png', 'image/webp' ) ) );
        $formats = self::format_labels( $allowed_types );
        $accept = implode( ',', $allowed_types );
        $min_width = isset( $upload['min_width'] ) ? (int) $upload['min_width'] : 1;
        $min_height = isset( $upload['min_height'] ) ? (int) $upload['min_height'] : 1;
        $instance = 'rkav-' . ++self::$render_count;
        $file_id = $instance . '-upload';
        $slug = $definition->get( 'slug' );
        $api_url = rest_url( 'rk-ai/v1/visualizer/' . rawurlencode( $slug ) . '/' );
        $title = $definition->get( 'name' );
        $description = $definition->get( 'description', '' );
        $upload_label = isset( $copy['upload_label'] ) ? $copy['upload_label'] : 'Upload an image';
        $empty_copy = isset( $copy['preview_empty'] ) ? $copy['preview_empty'] : 'Your photo preview will appear here.';
        $busy_copy = isset( $copy['busy'] ) ? $copy['busy'] : 'Preparing your visualization…';
        $submit_label = '' !== trim( $atts['submit_label'] ) ? $atts['submit_label'] : ( isset( $copy['submit_label'] ) ? $copy['submit_label'] : 'Create visualization' );
        $cta = $definition->get( 'cta', array() );
        $cta_label = '' !== trim( $atts['cta_label'] ) ? $atts['cta_label'] : ( isset( $cta['label'] ) ? $cta['label'] : '' );
        $cta_url = '' !== trim( $atts['cta_url'] ) ? $atts['cta_url'] : ( isset( $cta['url'] ) ? $cta['url'] : '' );
        $dialog_title_id = $instance . '-lead-title';

        ob_start();
        ?>
        <section id="<?php echo esc_attr( $instance ); ?>" class="rkaiviz rk-ai-visualizer" data-rkaiviz="" data-visualizer="<?php echo esc_attr( $slug ); ?>" data-api="<?php echo esc_url( $api_url ); ?>" data-max-size="<?php echo esc_attr( $max_size ); ?>" data-min-width="<?php echo esc_attr( $min_width ); ?>" data-min-height="<?php echo esc_attr( $min_height ); ?>" data-allowed-types="<?php echo esc_attr( $accept ); ?>" data-step="upload">
            <header class="rkaiviz-header">
                <div class="rkaiviz-brand">
                    <span class="rkaiviz-brand-mark" aria-hidden="true">RK</span>
                    <div>
                        <p class="rkaiviz-eyebrow">AI DESIGN STUDIO</p>
                        <h2><?php echo esc_html( $title ); ?></h2>
                        <?php if ( $description ) : ?><p class="rkaiviz-description"><?php echo esc_html( $description ); ?></p><?php endif; ?>
                    </div>
                </div>
                <span class="rkaiviz-ready" data-viz-state role="status" aria-live="polite"><span class="rkaiviz-ready-dot" aria-hidden="true"></span><span data-viz-state-label>Ready to begin</span></span>
            </header>

            <ol class="rkaiviz-steps" aria-label="Visualization steps">
                <li class="is-current" data-viz-step-item="upload" aria-current="step"><span>01</span><span>Add a photo</span></li>
                <li data-viz-step-item="configure"><span>02</span><span>Choose details</span></li>
                <li data-viz-step-item="result"><span>03</span><span>See your result</span></li>
            </ol>

            <form class="rkaiviz-form" data-viz-form enctype="multipart/form-data">
                <section class="rkaiviz-panel rkaiviz-upload-panel" data-viz-upload-panel>
                    <div class="rkaiviz-section-heading">
                        <div><p class="rkaiviz-eyebrow">STEP 01 · YOUR SPACE</p><h3><?php echo esc_html( $upload_label ); ?></h3></div>
                        <span class="rkaiviz-step-tag">Upload</span>
                    </div>
                    <label class="rkaiviz-dropzone" data-viz-dropzone for="<?php echo esc_attr( $file_id ); ?>">
                        <input id="<?php echo esc_attr( $file_id ); ?>" class="rkaiviz-file-input" type="file" name="image" accept="<?php echo esc_attr( $accept ); ?>" required aria-required="true" aria-describedby="<?php echo esc_attr( $instance . '-file-help' ); ?>" data-viz-file>
                        <span class="rkaiviz-upload-icon" aria-hidden="true"><svg viewBox="0 0 48 48" focusable="false"><path d="M24 31V8m0 0-8 8m8-8 8 8M8 29v10h32V29" /></svg></span>
                        <strong>Drop your photo here</strong>
                        <span class="rkaiviz-drop-copy">or <span class="rkaiviz-inline-link">browse files</span> on your device</span>
                        <span class="rkaiviz-drop-caption">Choose a clear, well-lit photo for the best result.</span>
                    </label>
                    <p id="<?php echo esc_attr( $instance . '-file-help' ); ?>" class="rkaiviz-file-help"><?php echo esc_html( $formats ); ?> · Up to <?php echo esc_html( self::format_bytes( $max_size ) ); ?> · Minimum <?php echo esc_html( $min_width . ' × ' . $min_height . ' px' ); ?></p>
                    <p class="rkaiviz-file-status" data-viz-file-status role="status" aria-live="polite" hidden></p>
                    <div class="rkaiviz-upload-actions" data-viz-upload-actions hidden>
                        <label class="rkaiviz-small-button" for="<?php echo esc_attr( $file_id ); ?>">Replace photo</label>
                        <button class="rkaiviz-quiet-button" type="button" data-viz-remove>Remove photo</button>
                    </div>
                    <p class="rkaiviz-error" data-viz-error role="alert" aria-live="assertive" hidden></p>
                </section>

                <aside class="rkaiviz-panel rkaiviz-preview-panel" aria-label="Image preview">
                    <div class="rkaiviz-section-heading">
                        <div><p class="rkaiviz-eyebrow">LIVE CANVAS</p><h3>Your visualization</h3></div>
                        <span class="rkaiviz-preview-badge" data-viz-view-label>Photo preview</span>
                    </div>
                    <div class="rkaiviz-stage" data-viz-stage>
                        <div class="rkaiviz-empty" data-viz-empty>
                            <span class="rkaiviz-empty-mark" aria-hidden="true"><svg viewBox="0 0 48 48" focusable="false"><rect x="6" y="8" width="36" height="32" rx="5"/><circle cx="17" cy="19" r="3"/><path d="m9 35 10-10 7 7 5-5 9 9"/></svg></span>
                            <strong>Your room, reimagined</strong>
                            <span><?php echo esc_html( $empty_copy ); ?></span>
                            <small>Your photo stays in this preview while you configure.</small>
                        </div>
                        <img class="rkaiviz-stage-image" data-viz-img alt="Uploaded photo preview" hidden>
                        <div class="rkaiviz-busy" data-viz-busy role="status" aria-live="polite" hidden>
                            <span class="rkaiviz-spinner" aria-hidden="true"></span>
                            <strong data-viz-busy-title>Preparing your request</strong>
                            <span data-viz-busy-copy><?php echo esc_html( $busy_copy ); ?></span>
                            <small>No progress estimate is shown; timing depends on the image service.</small>
                        </div>
                    </div>
                    <div class="rkaiviz-result" data-viz-done hidden>
                        <div class="rkaiviz-result-heading"><span class="rkaiviz-result-check" aria-hidden="true">✓</span><div><strong>Your visualization is ready</strong><span>Explore the concept, then decide what to do next.</span></div></div>
                        <p class="rkaiviz-disclaimer"><?php echo esc_html( isset( $copy['result_disclaimer'] ) ? $copy['result_disclaimer'] : 'AI-generated concept preview.' ); ?></p>
                        <figure class="rkaiviz-original"><img data-viz-orig alt="Original uploaded photo" hidden><figcaption>Original photo reference</figcaption></figure>
                        <div class="rkaiviz-result-actions">
                            <button class="rkaiviz-action-secondary" type="button" data-viz-retry>Try again</button>
                            <button class="rkaiviz-action-quiet" type="button" data-viz-new>Start another</button>
                            <?php if ( $cta_label && $cta_url ) : ?><a class="rkaiviz-action-primary" href="<?php echo esc_url( $cta_url ); ?>" data-viz-cta><?php echo esc_html( $cta_label ); ?></a><?php endif; ?>
                        </div>
                    </div>
                </aside>

                <section class="rkaiviz-panel rkaiviz-options-panel">
                    <div class="rkaiviz-section-heading">
                        <div><p class="rkaiviz-eyebrow">STEP 02 · MAKE IT YOURS</p><h3>Choose your design details</h3></div>
                        <span class="rkaiviz-step-tag">Personalize</span>
                    </div>
                    <p class="rkaiviz-panel-intro">Tell us what you’d like to explore. You can update any choice before generating.</p>
                    <div class="rkaiviz-fields">
                        <?php foreach ( $fields as $field ) : ?>
                            <?php if ( ! empty( $field['display_if_options'] ) && empty( $field['options'] ) ) { continue; } ?>
                            <?php echo self::field( $field, $instance ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php endforeach; ?>
                    </div>
                    <div class="rkaiviz-review"><span class="rkaiviz-review-label">YOUR CONFIGURATION</span><p data-viz-summary aria-live="polite">Your selected design details will appear here.</p></div>
                    <p class="rkaiviz-error rkaiviz-error-inline" data-viz-form-error role="alert" aria-live="assertive" hidden></p>
                    <p class="rkaiviz-quota" data-viz-quota role="status" aria-live="polite">Checking free generations…</p>
                    <button class="rkaiviz-generate" type="submit" data-viz-submit><span data-viz-submit-label><?php echo esc_html( $submit_label ); ?></span><span aria-hidden="true">→</span></button>
                    <p class="rkaiviz-submit-note">Your photo is used only to create this visualization.</p>
                </section>
            </form>

            <dialog class="rkaiviz-dialog" data-viz-lead aria-labelledby="<?php echo esc_attr( $dialog_title_id ); ?>" aria-describedby="<?php echo esc_attr( $dialog_title_id . '-description' ); ?>">
                <form class="rkaiviz-lead-form" data-viz-leadform>
                    <button class="rkaiviz-dialog-close" type="button" data-viz-close aria-label="Close contact form">×</button>
                    <span class="rkaiviz-lead-mark" aria-hidden="true">+</span>
                    <p class="rkaiviz-eyebrow">ONE MORE IDEA</p>
                    <h3 id="<?php echo esc_attr( $dialog_title_id ); ?>">Unlock another visualization</h3>
                    <p id="<?php echo esc_attr( $dialog_title_id . '-description' ); ?>" class="rkaiviz-panel-intro">Share your contact details to unlock the additional free visualization.</p>
                    <label class="rkaiviz-lead-field" for="<?php echo esc_attr( $instance . '-lead-name' ); ?>">Name<input id="<?php echo esc_attr( $instance . '-lead-name' ); ?>" name="name" maxlength="120" autocomplete="name" required></label>
                    <label class="rkaiviz-lead-field" for="<?php echo esc_attr( $instance . '-lead-email' ); ?>">Email<input id="<?php echo esc_attr( $instance . '-lead-email' ); ?>" name="email" type="email" maxlength="200" autocomplete="email" required></label>
                    <label class="rkaiviz-lead-field" for="<?php echo esc_attr( $instance . '-lead-phone' ); ?>">Phone <span>(optional)</span><input id="<?php echo esc_attr( $instance . '-lead-phone' ); ?>" name="phone" type="tel" maxlength="30" autocomplete="tel"></label>
                    <p class="rkaiviz-error" data-viz-lead-error role="alert" aria-live="assertive" hidden></p>
                    <button class="rkaiviz-generate" type="submit" data-viz-lead-submit><span>Unlock and continue</span><span aria-hidden="true">→</span></button>
                </form>
            </dialog>
        </section>
        <?php
        return ob_get_clean();
    }

    private static function field( array $field, $instance ) {
        $id = $instance . '-' . sanitize_key( $field['id'] );
        $name = esc_attr( $field['id'] );
        $type = $field['type'];
        $label = esc_html( $field['label'] );
        $required = ! empty( $field['required'] );
        $required_attr = $required ? ' required aria-required="true"' : '';
        $condition = '';
        if ( ! empty( $field['visible_when'] ) ) {
            $condition .= ' data-visible-field="' . esc_attr( $field['visible_when']['field'] ) . '" data-visible-equals="' . esc_attr( self::condition_value( $field['visible_when']['equals'] ) ) . '" hidden';
        }
        if ( ! empty( $field['required_when'] ) ) {
            $condition .= ' data-required-field="' . esc_attr( $field['required_when']['field'] ) . '" data-required-equals="' . esc_attr( self::condition_value( $field['required_when']['equals'] ) ) . '"';
        }
        $safe_type = preg_match( '/^[a-z]+$/', $type ) ? $type : 'text';
        $help_id = $id . '-help';
        $has_help = ! empty( $field['help'] ) && is_string( $field['help'] );
        $help_attr = $has_help ? ' aria-describedby="' . esc_attr( $help_id ) . '"' : '';
        $default = isset( $field['default'] ) ? $field['default'] : null;

        ob_start();
        ?>
        <div class="rkaiviz-field rkaiviz-field--<?php echo esc_attr( $safe_type ); ?>" data-viz-field data-field-id="<?php echo esc_attr( $field['id'] ); ?>" data-field-label="<?php echo esc_attr( $field['label'] ); ?>" data-field-type="<?php echo esc_attr( $type ); ?>"<?php echo $condition; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php if ( in_array( $type, array( 'radio', 'cards', 'swatches', 'image' ), true ) ) : ?>
                <fieldset class="rkaiviz-choice-field">
                    <legend class="rkaiviz-field-label"><?php echo $label; ?><?php echo self::required_mark( $required ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></legend>
                    <?php if ( $has_help ) : ?><p class="rkaiviz-field-help" id="<?php echo esc_attr( $help_id ); ?>"><?php echo esc_html( $field['help'] ); ?></p><?php endif; ?>
                    <?php $choice_class = 'image' === $type ? 'rkaiviz-image-grid' : ( 'cards' === $type ? 'rkaiviz-choice-grid' : ( 'swatches' === $type ? 'rkaiviz-swatch-grid' : 'rkaiviz-radio-list' ) ); ?>
                    <div class="<?php echo esc_attr( $choice_class ); ?>">
                        <?php foreach ( (array) $field['options'] as $value => $text ) : ?>
                            <?php $option_id = $id . '-' . sanitize_key( (string) $value ); ?>
                            <label class="rkaiviz-choice<?php echo 'cards' === $type ? ' rkaiviz-choice--card' : ''; ?><?php echo 'swatches' === $type ? ' rkaiviz-choice--swatch' : ''; ?><?php echo 'image' === $type ? ' rkaiviz-choice--image' : ''; ?>" for="<?php echo esc_attr( $option_id ); ?>">
                                <input id="<?php echo esc_attr( $option_id ); ?>" class="rkaiviz-choice-input" type="radio" name="<?php echo $name; ?>" value="<?php echo esc_attr( $value ); ?>"<?php echo $required_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo null !== $default && (string) $default === (string) $value ? ' checked' : ''; ?><?php echo $help_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                                <?php if ( 'image' === $type ) : ?><span class="rkaiviz-image-thumb" aria-hidden="true"><img src="<?php echo esc_url( $field['images'][$value] ); ?>" alt="" loading="lazy" decoding="async"></span><?php endif; ?>
                                <span class="rkaiviz-choice-indicator" aria-hidden="true"></span>
                                <?php if ( 'swatches' === $type ) : ?><span class="rkaiviz-swatch-dot" aria-hidden="true"<?php echo self::swatch_style( $value ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>></span><?php endif; ?>
                                <span class="rkaiviz-choice-label"><?php echo esc_html( $text ); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            <?php else : ?>
                <?php if ( 'toggle' !== $type ) : ?><label class="rkaiviz-field-label" for="<?php echo esc_attr( $id ); ?>"><?php echo $label; ?><?php echo self::required_mark( $required ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label><?php endif; ?>
                <?php if ( $has_help ) : ?><p class="rkaiviz-field-help" id="<?php echo esc_attr( $help_id ); ?>"><?php echo esc_html( $field['help'] ); ?></p><?php endif; ?>
                <?php
                $common = ' id="' . esc_attr( $id ) . '" name="' . $name . '" data-viz-control' . $required_attr . $help_attr;
                $placeholder = isset( $field['placeholder'] ) ? $field['placeholder'] : '';
                ?>
                <?php if ( 'select' === $type ) : ?>
                    <select class="rkaiviz-control"<?php echo $common; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                        <option value=""<?php echo null === $default || '' === (string) $default ? ' selected' : ''; ?>><?php echo esc_html( $required ? 'Choose an option…' : 'Select an option (optional)' ); ?></option>
                        <?php foreach ( (array) $field['options'] as $value => $text ) : ?><option value="<?php echo esc_attr( $value ); ?>"<?php echo null !== $default && (string) $default === (string) $value ? ' selected' : ''; ?>><?php echo esc_html( $text ); ?></option><?php endforeach; ?>
                    </select>
                <?php elseif ( 'textarea' === $type ) : ?>
                    <textarea class="rkaiviz-control"<?php echo $common; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> maxlength="<?php echo esc_attr( isset( $field['max_length'] ) ? (int) $field['max_length'] : 500 ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>"><?php echo esc_html( null === $default ? '' : (string) $default ); ?></textarea>
                <?php elseif ( 'toggle' === $type ) : ?>
                    <?php $checked = null !== $default && in_array( $default, array( true, 1, '1', 'true', 'yes', 'on' ), true ); ?>
                    <label class="rkaiviz-toggle" for="<?php echo esc_attr( $id ); ?>"><input<?php echo $common; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> type="checkbox" value="1"<?php echo $checked ? ' checked' : ''; ?>><span class="rkaiviz-toggle-track" aria-hidden="true"></span><span class="rkaiviz-toggle-copy"><?php echo $label; ?><?php echo self::required_mark( $required ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></label>
                <?php elseif ( 'slider' === $type ) : ?>
                    <?php $min = isset( $field['min'] ) ? $field['min'] : 0; $max = isset( $field['max'] ) ? $field['max'] : 100; $step = isset( $field['step'] ) ? $field['step'] : 1; $value = null !== $default ? $default : $min; ?>
                    <div class="rkaiviz-range-line"><input class="rkaiviz-control rkaiviz-range"<?php echo $common; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> type="range" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" step="<?php echo esc_attr( $step ); ?>" value="<?php echo esc_attr( $value ); ?>"><output class="rkaiviz-range-value" data-viz-range-for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $value ); ?></output></div>
                <?php else : ?>
                    <?php $input_type = in_array( $type, array( 'text', 'number' ), true ) ? $type : 'text'; ?>
                    <input class="rkaiviz-control"<?php echo $common; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> type="<?php echo esc_attr( $input_type ); ?>" value="<?php echo esc_attr( null === $default ? '' : (string) $default ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>"<?php if ( 'number' === $input_type && isset( $field['min'] ) ) : ?> min="<?php echo esc_attr( $field['min'] ); ?>"<?php endif; ?><?php if ( 'number' === $input_type && isset( $field['max'] ) ) : ?> max="<?php echo esc_attr( $field['max'] ); ?>"<?php endif; ?><?php if ( 'number' === $input_type && isset( $field['step'] ) ) : ?> step="<?php echo esc_attr( $field['step'] ); ?>"<?php endif; ?><?php if ( isset( $field['input_mode'] ) && preg_match( '/^[a-z]+$/i', $field['input_mode'] ) ) : ?> inputmode="<?php echo esc_attr( $field['input_mode'] ); ?>"<?php endif; ?><?php if ( isset( $field['max_length'] ) ) : ?> maxlength="<?php echo esc_attr( (int) $field['max_length'] ); ?>"<?php endif; ?><?php if ( isset( $field['pattern'] ) && self::html_pattern( $field['pattern'] ) ) : ?> pattern="<?php echo esc_attr( self::html_pattern( $field['pattern'] ) ); ?>"<?php endif; ?>>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function condition_value( $value ) { return is_bool( $value ) ? ( $value ? '1' : '0' ) : (string) $value; }

    private static function required_mark( $required ) { return $required ? '<span class="rkaiviz-required" aria-hidden="true">*</span>' : ''; }

    private static function format_labels( array $types ) {
        $labels = array( 'image/jpeg' => 'JPG', 'image/png' => 'PNG', 'image/webp' => 'WebP' );
        $values = array();
        foreach ( $types as $type ) { if ( isset( $labels[ $type ] ) ) { $values[] = $labels[ $type ]; } }
        return $values ? implode( ', ', $values ) : 'Supported image formats';
    }

    private static function format_bytes( $bytes ) {
        $number = function_exists( 'number_format_i18n' ) ? number_format_i18n( $bytes / 1048576, 0 ) : number_format( $bytes / 1048576, 0 );
        return $number . ' MB';
    }

    private static function swatch_style( $value ) {
        $value = (string) $value;
        if ( preg_match( '/^#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?$/', $value ) ) { return ' style="--rkaiviz-swatch:' . esc_attr( $value ) . '"'; }
        return '';
    }

    private static function html_pattern( $pattern ) {
        if ( ! is_string( $pattern ) || ! preg_match( '/^\/(.*)\/[a-z]*$/s', $pattern, $matches ) ) { return ''; }
        return $matches[1];
    }
}
