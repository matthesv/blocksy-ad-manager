<?php
/**
 * Anchor Ad Functionality
 * 
 * @package Blocksy_Ad_Manager
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class BAM_Anchor {
    
    /**
     * Initialisiert die Anchor Ad Hooks
     */
    public function init() {
        add_action('wp_footer', [$this, 'render_anchor_ads'], 99);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    /**
     * Lädt CSS und JS für Anchor Ads
     */
    public function enqueue_assets() {
        $has_anchor_ads = $this->has_active_anchor_ads();
        
        if (!$has_anchor_ads) {
            return;
        }
        
        wp_enqueue_style(
            'bam-anchor-style',
            BAM_PLUGIN_URL . 'assets/css/anchor.css',
            [],
            BAM_VERSION
        );
        
        wp_enqueue_script(
            'bam-anchor-script',
            BAM_PLUGIN_URL . 'assets/js/anchor.js',
            [], // Keine Abhängigkeiten
            BAM_VERSION,
            true // Im Footer laden
        );
    }
    
    /**
     * Prüft ob aktive Anchor Ads vorhanden sind
     */
    private function has_active_anchor_ads() {
        $ads = get_posts([
            'post_type'      => BAM_Post_Type::POST_TYPE,
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => '_bam_position',
                    'value' => 'anchor',
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
     * Rendert alle Anchor Ads im Footer
     */
    public function render_anchor_ads() {
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
                    'value' => 'anchor',
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
        
        $anchor_ads = get_posts($args);
        
        foreach ($anchor_ads as $ad) {
            if ($this->should_display_anchor_ad($ad, $post)) {
                echo $this->render_single_anchor_ad($ad);
            }
        }
    }
    
    /**
     * Prüft ob eine Anchor Ad angezeigt werden soll
     */
    private function should_display_anchor_ad($ad, $current_post) {
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
     * Rendert eine einzelne Anchor Ad mit Tab-Layout
     */
    private function render_single_anchor_ad($ad) {
        $content_type = get_post_meta($ad->ID, '_bam_content_type', true) ?: 'html';
        $devices = get_post_meta($ad->ID, '_bam_devices', true);
        $max_height_value = get_post_meta($ad->ID, '_bam_anchor_max_height', true) ?: '150';
        $max_height_unit = get_post_meta($ad->ID, '_bam_anchor_max_height_unit', true) ?: 'px';
        $allow_close = get_post_meta($ad->ID, '_bam_anchor_allow_close', true) ?: '0';
        $close_duration = get_post_meta($ad->ID, '_bam_anchor_close_duration', true) ?: '24';
        
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
        $device_classes = ['bam-anchor-ad', 'bam-anchor-ad-' . $ad->ID];
        
        if (!in_array('desktop', $devices)) {
            $device_classes[] = 'bam-hide-desktop';
        }
        if (!in_array('tablet', $devices)) {
            $device_classes[] = 'bam-hide-tablet';
        }
        if (!in_array('mobile', $devices)) {
            $device_classes[] = 'bam-hide-mobile';
        }
        
        // Max Height Style
        $max_height_style = sprintf('--bam-anchor-max-height: %s%s;', 
            esc_attr($max_height_value), 
            esc_attr($max_height_unit)
        );
        
        // Data Attributes
        $data_attrs = sprintf(
            'data-ad-id="%d" data-allow-close="%s" data-close-duration="%s"',
            $ad->ID,
            esc_attr($allow_close),
            esc_attr($close_duration)
        );
        
        // Close Button HTML (nur wenn erlaubt)
        $close_button_html = '';
        if ($allow_close === '1') {
            $close_button_html = sprintf(
                '<button type="button" class="bam-anchor-close" aria-label="%s" title="%s">×</button>',
                esc_attr__('Schließen', 'blocksy-ad-manager'),
                esc_attr__('Anzeige schließen', 'blocksy-ad-manager')
            );
        }
        
        // HTML mit Tab-Layout - KEIN aria-hidden verwenden
        $output = sprintf(
            '<div class="%s" style="%s" %s role="complementary" aria-label="%s">
                <!-- Tab Bar (oberhalb der Box) -->
                <div class="bam-anchor-tab">
                    <button type="button" class="bam-anchor-toggle" aria-expanded="true" title="%s">
                        <span class="bam-anchor-tab-icon" aria-hidden="true">▼</span>
                        <span class="bam-anchor-tab-text-minimize">%s</span>
                        <span class="bam-anchor-tab-text-expand">%s</span>
                    </button>
                    %s
                </div>
                <!-- Content Body -->
                <div class="bam-anchor-body">
                    <div class="bam-anchor-content">%s</div>
                </div>
            </div>',
            esc_attr(implode(' ', $device_classes)),
            $max_height_style,
            $data_attrs,
            esc_attr__('Werbeanzeige', 'blocksy-ad-manager'),
            esc_attr__('Anzeige minimieren oder maximieren', 'blocksy-ad-manager'),
            esc_html__('Minimieren', 'blocksy-ad-manager'),
            esc_html__('Anzeige einblenden', 'blocksy-ad-manager'),
            $close_button_html,
            $rendered_content
        );
        
        return $output;
    }
    
    /**
     * Rendert Banner/Bild-Content für Anchor Ad
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
                'class' => 'bam-banner-img',
                'alt'   => $alt_text,
            ]);
        } else {
            $image_html = sprintf(
                '<img src="%s" alt="%s" class="bam-banner-img" loading="lazy">',
                esc_url($image_url),
                esc_attr($alt_text)
            );
        }
        
        // Ohne Link
        if (empty($link_url)) {
            return sprintf('<div class="bam-banner">%s</div>', $image_html);
        }
        
        // Mit Link
        $link_attrs = [];
        $link_attrs[] = sprintf('href="%s"', esc_url($link_url));
        $link_attrs[] = 'class="bam-banner-link"';
        
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
            '<div class="bam-banner"><a %s>%s</a></div>',
            implode(' ', $link_attrs),
            $image_html
        );
    }
    
    /**
     * Rendert HTML/PHP-Content für Anchor Ad
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
                    echo '<!-- BAM Anchor Error: ' . esc_html($e->getMessage()) . ' -->';
                }
            }
            return ob_get_clean();
        } else {
            return do_shortcode($content);
        }
    }
}
