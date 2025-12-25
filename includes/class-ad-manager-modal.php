<?php
/**
 * Modal Ad Functionality
 * 
 * @package Blocksy_Ad_Manager
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class BAM_Modal {
    
    /**
     * Initialisiert die Modal Ad Hooks
     */
    public function init() {
        add_action('wp_footer', [$this, 'render_modal_ads']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    /**
     * Lädt CSS und JS für Modal Ads
     */
    public function enqueue_assets() {
        $has_modal_ads = $this->has_active_modal_ads();
        
        if (!$has_modal_ads) {
            return;
        }
        
        wp_enqueue_style(
            'bam-modal-style',
            BAM_PLUGIN_URL . 'assets/css/modal.css',
            [],
            BAM_VERSION
        );
        
        wp_enqueue_script(
            'bam-modal-script',
            BAM_PLUGIN_URL . 'assets/js/modal.js',
            [],
            BAM_VERSION,
            true
        );
        
        wp_localize_script('bam-modal-script', 'bamModal', [
            'closeText'       => __('Schließen', 'blocksy-ad-manager'),
            'dontShowText'    => __('Nicht mehr anzeigen', 'blocksy-ad-manager'),
        ]);
    }
    
    /**
     * Prüft ob aktive Modal Ads vorhanden sind
     */
    private function has_active_modal_ads() {
        $ads = get_posts([
            'post_type'      => BAM_Post_Type::POST_TYPE,
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => '_bam_position',
                    'value' => 'modal',
                ],
                [
                    'relation' => 'OR',
                    [
                        'key'     => '_bam_is_active',
                        'value'   => '1',
                        'compare' => '=',
                    ],
                    [
                        'key'     => '_bam_is_active',
                        'compare' => 'NOT EXISTS',
                    ],
                ],
            ],
        ]);
        
        return !empty($ads);
    }
    
    /**
     * Rendert alle Modal Ads im Footer
     */
    public function render_modal_ads() {
        if (is_admin() || wp_doing_ajax()) {
            return;
        }
        
        global $post;
        
        $args = [
            'post_type'      => BAM_Post_Type::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => '_bam_position',
                    'value' => 'modal',
                ],
                [
                    'relation' => 'OR',
                    [
                        'key'     => '_bam_is_active',
                        'value'   => '1',
                        'compare' => '=',
                    ],
                    [
                        'key'     => '_bam_is_active',
                        'compare' => 'NOT EXISTS',
                    ],
                ],
            ],
        ];
        
        $modal_ads = get_posts($args);
        
        foreach ($modal_ads as $ad) {
            if ($this->should_display_modal_ad($ad, $post)) {
                echo $this->render_single_modal_ad($ad);
            }
        }
    }
    
    /**
     * Prüft ob eine Modal Ad angezeigt werden soll
     */
    private function should_display_modal_ad($ad, $current_post) {
        $is_active = get_post_meta($ad->ID, '_bam_is_active', true);
        if ($is_active === '0') {
            return false;
        }
        
        if (!$current_post) {
            return true;
        }
        
        $allowed_post_types = get_post_meta($ad->ID, '_bam_post_types', true);
        if (!empty($allowed_post_types) && is_array($allowed_post_types)) {
            if (is_singular() && !in_array($current_post->post_type, $allowed_post_types)) {
                return false;
            }
        }
        
        $exclude_ids = get_post_meta($ad->ID, '_bam_exclude_ids', true);
        if (!empty($exclude_ids) && is_singular()) {
            $excluded = array_map('trim', explode(',', $exclude_ids));
            $excluded = array_map('intval', $excluded);
            if (in_array($current_post->ID, $excluded)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Rendert eine einzelne Modal Ad
     */
    private function render_single_modal_ad($ad) {
        $content_type = get_post_meta($ad->ID, '_bam_content_type', true) ?: 'html';
        $devices = get_post_meta($ad->ID, '_bam_devices', true);
        
        // Modal Settings
        $modal_delay = get_post_meta($ad->ID, '_bam_modal_delay', true) ?: '3';
        $modal_width = get_post_meta($ad->ID, '_bam_modal_width', true) ?: '600';
        $modal_width_unit = get_post_meta($ad->ID, '_bam_modal_width_unit', true) ?: 'px';
        $modal_allow_dismiss = get_post_meta($ad->ID, '_bam_modal_allow_dismiss', true) ?: '1';
        $modal_dismiss_duration = get_post_meta($ad->ID, '_bam_modal_dismiss_duration', true) ?: '24';
        $modal_close_outside = get_post_meta($ad->ID, '_bam_modal_close_outside', true) ?: '1';
        $modal_show_overlay = get_post_meta($ad->ID, '_bam_modal_show_overlay', true) ?: '1';
        
        if (empty($devices) || !is_array($devices)) {
            $devices = ['desktop', 'tablet', 'mobile'];
        }
        
        // Content basierend auf Typ rendern
        if ($content_type === 'image') {
            $rendered_content = $this->render_banner_content($ad);
        } else {
            $rendered_content = $this->render_code_content($ad, $content_type);
        }
        
        if (empty(trim($rendered_content))) {
            return '';
        }
        
        // Device Classes
        $device_classes = ['bam-modal-wrapper', 'bam-modal-wrapper-' . $ad->ID];
        
        if (!in_array('desktop', $devices)) {
            $device_classes[] = 'bam-hide-desktop';
        }
        if (!in_array('tablet', $devices)) {
            $device_classes[] = 'bam-hide-tablet';
        }
        if (!in_array('mobile', $devices)) {
            $device_classes[] = 'bam-hide-mobile';
        }
        
        // Modal Width Style
        $modal_style = sprintf('--bam-modal-width: %s%s;', 
            esc_attr($modal_width), 
            esc_attr($modal_width_unit)
        );
        
        // Data Attributes
        $data_attrs = sprintf(
            'data-ad-id="%d" data-delay="%s" data-allow-dismiss="%s" data-dismiss-duration="%s" data-close-outside="%s" data-show-overlay="%s"',
            $ad->ID,
            esc_attr($modal_delay),
            esc_attr($modal_allow_dismiss),
            esc_attr($modal_dismiss_duration),
            esc_attr($modal_close_outside),
            esc_attr($modal_show_overlay)
        );
        
        // Dismiss Checkbox HTML (nur wenn erlaubt)
        $dismiss_checkbox_html = '';
        if ($modal_allow_dismiss === '1') {
            $dismiss_checkbox_html = sprintf(
                '<label class="bam-modal-dismiss-label">
                    <input type="checkbox" class="bam-modal-dismiss-checkbox">
                    <span>%s</span>
                </label>',
                esc_html__('Nicht mehr anzeigen', 'blocksy-ad-manager')
            );
        }
        
        // HTML mit Modal-Layout
        $output = sprintf(
            '<div class="%s" style="%s" %s aria-hidden="true">
                <!-- Overlay -->
                <div class="bam-modal-overlay"></div>
                
                <!-- Modal Dialog -->
                <div class="bam-modal" role="dialog" aria-modal="true" aria-labelledby="bam-modal-title-%d">
                    <!-- Modal Header -->
                    <div class="bam-modal-header">
                        <button type="button" class="bam-modal-close" aria-label="%s">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                    
                    <!-- Modal Body -->
                    <div class="bam-modal-body">%s</div>
                    
                    <!-- Modal Footer -->
                    <div class="bam-modal-footer">
                        %s
                    </div>
                </div>
            </div>',
            esc_attr(implode(' ', $device_classes)),
            $modal_style,
            $data_attrs,
            $ad->ID,
            esc_attr__('Schließen', 'blocksy-ad-manager'),
            $rendered_content,
            $dismiss_checkbox_html
        );
        
        return $output;
    }
    
    /**
     * Rendert Banner/Bild-Content für Modal Ad
     */
    private function render_banner_content($ad) {
        $image_url = get_post_meta($ad->ID, '_bam_banner_image_url', true);
        $image_id = get_post_meta($ad->ID, '_bam_banner_image_id', true);
        $link_url = get_post_meta($ad->ID, '_bam_banner_link', true);
        $alt_text = get_post_meta($ad->ID, '_bam_banner_alt', true);
        $new_tab = get_post_meta($ad->ID, '_bam_banner_new_tab', true) === '1';
        $nofollow = get_post_meta($ad->ID, '_bam_banner_nofollow', true) === '1';
        
        if (empty($image_url) && empty($image_id)) {
            return '';
        }
        
        if ($image_id && !$image_url) {
            $image_url = wp_get_attachment_url($image_id);
        }
        
        if (empty($image_url)) {
            return '';
        }
        
        // Bild-HTML erstellen
        $image_html = '';
        
        if ($image_id) {
            $image_html = wp_get_attachment_image($image_id, 'large', false, [
                'class' => 'bam-modal-banner-img',
                'alt'   => $alt_text,
            ]);
        } else {
            $image_html = sprintf(
                '<img src="%s" alt="%s" class="bam-modal-banner-img" loading="lazy">',
                esc_url($image_url),
                esc_attr($alt_text)
            );
        }
        
        // Ohne Link
        if (empty($link_url)) {
            return sprintf('<div class="bam-modal-banner">%s</div>', $image_html);
        }
        
        // Mit Link
        $link_attrs = [];
        $link_attrs[] = sprintf('href="%s"', esc_url($link_url));
        $link_attrs[] = 'class="bam-modal-banner-link"';
        
        if ($new_tab) {
            $link_attrs[] = 'target="_blank"';
        }
        
        $rel = [];
        if ($new_tab) {
            $rel[] = 'noopener';
            $rel[] = 'noreferrer';
        }
        if ($nofollow) {
            $rel[] = 'nofollow';
        }
        if (!empty($rel)) {
            $link_attrs[] = sprintf('rel="%s"', implode(' ', $rel));
        }
        
        return sprintf(
            '<div class="bam-modal-banner"><a %s>%s</a></div>',
            implode(' ', $link_attrs),
            $image_html
        );
    }
    
    /**
     * Rendert HTML/PHP-Content für Modal Ad
     */
    private function render_code_content($ad, $content_type) {
        $content = get_post_meta($ad->ID, '_bam_ad_content', true);
        
        if (empty($content)) {
            return '';
        }
        
        if ($content_type === 'php') {
            ob_start();
            try {
                eval('?>' . $content);
            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    echo '<!-- BAM Modal Error: ' . esc_html($e->getMessage()) . ' -->';
                }
            }
            return ob_get_clean();
        } else {
            return do_shortcode($content);
        }
    }
}
