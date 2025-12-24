<?php
/**
 * Anchor Ad Functionality
 * 
 * @package Blocksy_Ad_Manager
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class BAM_Anchor {
    
    /**
     * Initialisiert die Anchor Ad Hooks
     */
    public function init() {
        add_action('wp_footer', [$this, 'render_anchor_ads']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    /**
     * Lädt CSS und JS für Anchor Ads
     */
    public function enqueue_assets() {
        // Prüfen ob Anchor Ads existieren
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
            [],
            BAM_VERSION,
            true
        );
        
        // Lokalisierung für JS
        wp_localize_script('bam-anchor-script', 'bamAnchor', [
            'minimizeText' => __('Minimieren', 'blocksy-ad-manager'),
            'expandText'   => __('Anzeige einblenden', 'blocksy-ad-manager'),
            'closeText'    => __('Schließen', 'blocksy-ad-manager'),
        ]);
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
        // Aktiv-Check
        $is_active = get_post_meta($ad->ID, '_bam_is_active', true);
        if ($is_active === '0') {
            return false;
        }
        
        // Wenn kein Post-Kontext, trotzdem anzeigen (z.B. Archive)
        if (!$current_post) {
            return true;
        }
        
        // Post Type Check
        $allowed_post_types = get_post_meta($ad->ID, '_bam_post_types', true);
        if (!empty($allowed_post_types) && is_array($allowed_post_types)) {
            if (is_singular() && !in_array($current_post->post_type, $allowed_post_types)) {
                return false;
            }
        }
        
        // Excluded IDs Check
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
     * Rendert eine einzelne Anchor Ad
     */
    private function render_single_anchor_ad($ad) {
        $content = get_post_meta($ad->ID, '_bam_ad_content', true);
        
        if (empty($content)) {
            return '';
        }
        
        $content_type = get_post_meta($ad->ID, '_bam_content_type', true) ?: 'html';
        $devices = get_post_meta($ad->ID, '_bam_devices', true);
        $max_height_value = get_post_meta($ad->ID, '_bam_anchor_max_height', true) ?: '150';
        $max_height_unit = get_post_meta($ad->ID, '_bam_anchor_max_height_unit', true) ?: 'px';
        $allow_close = get_post_meta($ad->ID, '_bam_anchor_allow_close', true) ?: '0';
        $close_duration = get_post_meta($ad->ID, '_bam_anchor_close_duration', true) ?: '24';
        
        // Standardwert für Geräte
        if (empty($devices) || !is_array($devices)) {
            $devices = ['desktop', 'tablet', 'mobile'];
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
        
        // Content verarbeiten
        if ($content_type === 'php') {
            ob_start();
            try {
                eval('?>' . $content);
            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    echo '<!-- BAM Anchor Error: ' . esc_html($e->getMessage()) . ' -->';
                }
            }
            $rendered_content = ob_get_clean();
        } else {
            $rendered_content = do_shortcode($content);
        }
        
        if (empty(trim($rendered_content))) {
            return '';
        }
        
        // Max Height Style
        $max_height_style = sprintf('--bam-anchor-max-height: %s%s;', 
            esc_attr($max_height_value), 
            esc_attr($max_height_unit)
        );
        
        // Data Attribute für Close-Dauer
        $data_attrs = sprintf(
            'data-ad-id="%d" data-allow-close="%s" data-close-duration="%s"',
            $ad->ID,
            esc_attr($allow_close),
            esc_attr($close_duration)
        );
        
        $output = sprintf(
            '<div class="%s" style="%s" %s>
                <div class="bam-anchor-controls">
                    <button type="button" class="bam-anchor-minimize" aria-label="%s">
                        <span class="bam-icon-minimize">▼</span>
                        <span class="bam-icon-expand">▲</span>
                    </button>
                    %s
                </div>
                <div class="bam-anchor-content">%s</div>
            </div>',
            esc_attr(implode(' ', $device_classes)),
            $max_height_style,
            $data_attrs,
            esc_attr__('Minimieren', 'blocksy-ad-manager'),
            $allow_close === '1' ? sprintf(
                '<button type="button" class="bam-anchor-close" aria-label="%s">✕</button>',
                esc_attr__('Schließen', 'blocksy-ad-manager')
            ) : '',
            $rendered_content
        );
        
        return $output;
    }
}
