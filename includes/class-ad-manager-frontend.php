<?php
if (!defined('ABSPATH')) {
    exit;
}

class BAM_Frontend {
    
    public function insert_ads($content) {
        // Nur auf Singular-Seiten im Frontend
        if (!is_singular() || is_admin() || wp_doing_ajax()) {
            return $content;
        }
        
        // Nicht im Feed
        if (is_feed()) {
            return $content;
        }
        
        global $post;
        
        if (!$post) {
            return $content;
        }
        
        $ads = $this->get_active_ads($post);
        
        if (empty($ads)) {
            return $content;
        }
        
        // Anzeigen nach Position sortieren (höhere Positionen zuerst einfügen)
        usort($ads, function($a, $b) {
            $pos_a = (int) get_post_meta($a->ID, '_bam_paragraph_number', true);
            $pos_b = (int) get_post_meta($b->ID, '_bam_paragraph_number', true);
            return $pos_b - $pos_a; // Absteigend sortieren
        });
        
        foreach ($ads as $ad) {
            $content = $this->insert_ad_into_content($content, $ad);
        }
        
        return $content;
    }
    
    private function get_active_ads($post) {
        $args = [
            'post_type'      => BAM_Post_Type::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                'relation' => 'AND',
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
                // Anchor Ads ausschließen (werden separat behandelt)
                [
                    'relation' => 'OR',
                    [
                        'key'     => '_bam_position',
                        'value'   => 'anchor',
                        'compare' => '!=',
                    ],
                    [
                        'key'     => '_bam_position',
                        'compare' => 'NOT EXISTS',
                    ],
                ],
            ],
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
        ];
        
        $ads = get_posts($args);
        $filtered_ads = [];
        
        foreach ($ads as $ad) {
            if ($this->should_display_ad($ad, $post)) {
                $filtered_ads[] = $ad;
            }
        }
        
        return $filtered_ads;
    }
    
    private function should_display_ad($ad, $current_post) {
        // Aktiv-Check
        $is_active = get_post_meta($ad->ID, '_bam_is_active', true);
        if ($is_active === '0') {
            return false;
        }
        
        // Post Type Check
        $allowed_post_types = get_post_meta($ad->ID, '_bam_post_types', true);
        if (!empty($allowed_post_types) && is_array($allowed_post_types)) {
            if (!in_array($current_post->post_type, $allowed_post_types)) {
                return false;
            }
        }
        
        // Excluded IDs Check
        $exclude_ids = get_post_meta($ad->ID, '_bam_exclude_ids', true);
        if (!empty($exclude_ids)) {
            $excluded = array_map('trim', explode(',', $exclude_ids));
            $excluded = array_map('intval', $excluded);
            if (in_array($current_post->ID, $excluded)) {
                return false;
            }
        }
        
        // Categories Check (nur für Posts)
        $allowed_categories = get_post_meta($ad->ID, '_bam_categories', true);
        if (!empty($allowed_categories) && is_array($allowed_categories) && $current_post->post_type === 'post') {
            $post_categories = wp_get_post_categories($current_post->ID);
            $allowed_categories = array_map('intval', $allowed_categories);
            if (empty(array_intersect($allowed_categories, $post_categories))) {
                return false;
            }
        }
        
        // Tags Check (nur für Posts)
        $allowed_tags = get_post_meta($ad->ID, '_bam_tags', true);
        if (!empty($allowed_tags) && is_array($allowed_tags) && $current_post->post_type === 'post') {
            $post_tags = wp_get_post_tags($current_post->ID, ['fields' => 'ids']);
            $allowed_tags = array_map('intval', $allowed_tags);
            if (empty(array_intersect($allowed_tags, $post_tags))) {
                return false;
            }
        }
        
        return true;
    }
    
    private function insert_ad_into_content($content, $ad) {
        $ad_html = $this->render_ad($ad);
        
        if (empty($ad_html)) {
            return $content;
        }
        
        $position = get_post_meta($ad->ID, '_bam_position', true) ?: 'after_paragraph';
        $number = (int) (get_post_meta($ad->ID, '_bam_paragraph_number', true) ?: 2);
        
        switch ($position) {
            case 'before_content':
                $content = $ad_html . $content;
                break;
                
            case 'after_content':
                $content = $content . $ad_html;
                break;
                
            case 'after_paragraph':
                $content = $this->insert_after_paragraph($content, $ad_html, $number);
                break;
                
            case 'after_heading':
                $content = $this->insert_after_heading($content, $ad_html, $number);
                break;
                
            case 'middle_content':
                $content = $this->insert_in_middle($content, $ad_html);
                break;
                
            default:
                $content = $content . $ad_html;
        }
        
        return $content;
    }
    
    /**
     * Fügt Anzeige nach dem X. Absatz ein
     */
    private function insert_after_paragraph($content, $ad_html, $paragraph_number) {
        $closing_p = '</p>';
        $paragraphs = explode($closing_p, $content);
        
        $total_paragraphs = count($paragraphs) - 1;
        
        if ($total_paragraphs < $paragraph_number) {
            return $content . $ad_html;
        }
        
        $output = '';
        
        for ($i = 0; $i < count($paragraphs); $i++) {
            $output .= $paragraphs[$i];
            
            if ($i < count($paragraphs) - 1) {
                $output .= $closing_p;
            }
            
            if ($i + 1 === $paragraph_number) {
                $output .= $ad_html;
            }
        }
        
        return $output;
    }
    
    /**
     * Fügt Anzeige nach der X. Überschrift ein
     */
    private function insert_after_heading($content, $ad_html, $heading_number) {
        $pattern = '/(<\/h[1-6]>)/i';
        
        preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE);
        
        if (empty($matches[0]) || count($matches[0]) < $heading_number) {
            return $content . $ad_html;
        }
        
        $match = $matches[0][$heading_number - 1];
        $insert_position = $match[1] + strlen($match[0]);
        
        $before = substr($content, 0, $insert_position);
        $after = substr($content, $insert_position);
        
        return $before . $ad_html . $after;
    }
    
    /**
     * Fügt Anzeige in der Mitte des Contents ein
     */
    private function insert_in_middle($content, $ad_html) {
        $closing_p = '</p>';
        $paragraphs = explode($closing_p, $content);
        $total = count($paragraphs) - 1;
        
        if ($total < 2) {
            return $content . $ad_html;
        }
        
        $middle = (int) ceil($total / 2);
        
        return $this->insert_after_paragraph($content, $ad_html, $middle);
    }
    
    /**
     * Rendert eine einzelne Anzeige
     */
    public function render_ad($ad) {
        $content_type = get_post_meta($ad->ID, '_bam_content_type', true) ?: 'html';
        $devices = get_post_meta($ad->ID, '_bam_devices', true);
        
        // Standardwert für Geräte
        if (empty($devices) || !is_array($devices)) {
            $devices = ['desktop', 'tablet', 'mobile'];
        }
        
        // Device Classes
        $device_classes = ['bam-ad-container', 'bam-ad-' . $ad->ID];
        
        if (!in_array('desktop', $devices)) {
            $device_classes[] = 'bam-hide-desktop';
        }
        if (!in_array('tablet', $devices)) {
            $device_classes[] = 'bam-hide-tablet';
        }
        if (!in_array('mobile', $devices)) {
            $device_classes[] = 'bam-hide-mobile';
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
        
        return sprintf(
            '<div class="%s">%s</div>',
            esc_attr(implode(' ', $device_classes)),
            $rendered_content
        );
    }
    
    /**
     * Rendert Banner/Bild-Content
     */
    private function render_banner_content($ad) {
        $image_url = get_post_meta($ad->ID, '_bam_banner_image_url', true);
        $image_id = get_post_meta($ad->ID, '_bam_banner_image_id', true);
        $link_url = get_post_meta($ad->ID, '_bam_banner_link', true);
        $alt_text = get_post_meta($ad->ID, '_bam_banner_alt', true);
        $new_tab = get_post_meta($ad->ID, '_bam_banner_new_tab', true) === '1';
        $nofollow = get_post_meta($ad->ID, '_bam_banner_nofollow', true) === '1';
        
        // Kein Bild vorhanden
        if (empty($image_url) && empty($image_id)) {
            return '';
        }
        
        // Bild-URL aus ID holen (für bessere Kompatibilität)
        if ($image_id && !$image_url) {
            $image_url = wp_get_attachment_url($image_id);
        }
        
        if (empty($image_url)) {
            return '';
        }
        
        // Responsive Bild mit srcset falls möglich
        $image_html = '';
        
        if ($image_id) {
            // WordPress responsive image
            $image_html = wp_get_attachment_image($image_id, 'full', false, [
                'class' => 'bam-banner-img',
                'alt'   => $alt_text,
            ]);
        } else {
            // Fallback: einfaches img-Tag
            $image_html = sprintf(
                '<img src="%s" alt="%s" class="bam-banner-img" loading="lazy">',
                esc_url($image_url),
                esc_attr($alt_text)
            );
        }
        
        // Ohne Link
        if (empty($link_url)) {
            return sprintf(
                '<div class="bam-banner">%s</div>',
                $image_html
            );
        }
        
        // Mit Link
        $link_attrs = [];
        $link_attrs[] = sprintf('href="%s"', esc_url($link_url));
        $link_attrs[] = 'class="bam-banner-link"';
        
        if ($new_tab) {
            $link_attrs[] = 'target="_blank"';
        }
        
        // Rel-Attribute
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
     * Rendert HTML/PHP-Content
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
                    echo '<!-- BAM Error: ' . esc_html($e->getMessage()) . ' -->';
                }
            }
            return ob_get_clean();
        } else {
            return do_shortcode($content);
        }
    }
    
    /**
     * CSS für Geräte-Sichtbarkeit und Banner
     */
    public function add_device_styles() {
        $has_ads = get_posts([
            'post_type'      => BAM_Post_Type::POST_TYPE,
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
        ]);
        
        if (empty($has_ads)) {
            return;
        }
        ?>
        <style id="bam-device-styles">
            .bam-ad-container {
                margin: 1.5em 0;
                clear: both;
            }
            
            /* Banner Styles */
            .bam-banner {
                text-align: center;
            }
            
            .bam-banner-link {
                display: inline-block;
                transition: opacity 0.2s ease;
            }
            
            .bam-banner-link:hover {
                opacity: 0.9;
            }
            
            .bam-banner-img {
                max-width: 100%;
                height: auto;
                display: block;
                margin: 0 auto;
                border-radius: 4px;
            }
            
            /* Desktop (> 1024px) */
            @media (min-width: 1025px) {
                .bam-hide-desktop {
                    display: none !important;
                }
            }
            
            /* Tablet (768px - 1024px) */
            @media (min-width: 768px) and (max-width: 1024px) {
                .bam-hide-tablet {
                    display: none !important;
                }
            }
            
            /* Mobile (< 768px) */
            @media (max-width: 767px) {
                .bam-hide-mobile {
                    display: none !important;
                }
            }
        </style>
        <?php
    }
}
