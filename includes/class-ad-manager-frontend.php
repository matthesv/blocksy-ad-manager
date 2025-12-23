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
        // Finde alle </p> Tags (auch mit Gutenberg-Kommentaren)
        $closing_p = '</p>';
        $paragraphs = explode($closing_p, $content);
        
        // Wenn nicht genug Absätze vorhanden, am Ende einfügen
        $total_paragraphs = count($paragraphs) - 1; // -1 weil explode ein leeres Element am Ende erzeugt
        
        if ($total_paragraphs < $paragraph_number) {
            return $content . $ad_html;
        }
        
        // Content neu zusammenbauen
        $output = '';
        
        for ($i = 0; $i < count($paragraphs); $i++) {
            $output .= $paragraphs[$i];
            
            // Nicht nach dem letzten Element
            if ($i < count($paragraphs) - 1) {
                $output .= $closing_p;
            }
            
            // Nach dem gewünschten Absatz einfügen
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
        // Pattern für alle Überschriften (h1-h6)
        $pattern = '/(<\/h[1-6]>)/i';
        
        // Alle Überschriften finden
        preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE);
        
        if (empty($matches[0]) || count($matches[0]) < $heading_number) {
            return $content . $ad_html;
        }
        
        // Position nach der X. Überschrift
        $match = $matches[0][$heading_number - 1];
        $insert_position = $match[1] + strlen($match[0]);
        
        // Content aufteilen und Anzeige einfügen
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
        $content = get_post_meta($ad->ID, '_bam_ad_content', true);
        
        if (empty($content)) {
            return '';
        }
        
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
        
        // Content verarbeiten
        if ($content_type === 'php') {
            ob_start();
            try {
                eval('?>' . $content);
            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    echo '<!-- BAM Error: ' . esc_html($e->getMessage()) . ' -->';
                }
            }
            $rendered_content = ob_get_clean();
        } else {
            // HTML mit Shortcodes
            // Shortcodes explizit ausführen (für Borlabs etc.)
            $rendered_content = do_shortcode($content);
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
     * CSS für Geräte-Sichtbarkeit
     */
    public function add_device_styles() {
        // Nur wenn Anzeigen existieren
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
