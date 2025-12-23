<?php
if (!defined('ABSPATH')) {
    exit;
}

class BAM_Frontend {
    
    public function insert_ads($content) {
        if (!is_singular() || is_admin()) {
            return $content;
        }
        
        global $post;
        
        $ads = $this->get_active_ads($post);
        
        if (empty($ads)) {
            return $content;
        }
        
        foreach ($ads as $ad) {
            $content = $this->insert_ad_into_content($content, $ad);
        }
        
        return $content;
    }
    
    private function get_active_ads($post) {
        $args = [
            'post_type'      => BAM_Post_Type::POST_TYPE,
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'   => '_bam_is_active',
                    'value' => '1',
                ]
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
        // Post Type Check
        $allowed_post_types = get_post_meta($ad->ID, '_bam_post_types', true) ?: [];
        if (!empty($allowed_post_types) && !in_array($current_post->post_type, $allowed_post_types)) {
            return false;
        }
        
        // Excluded IDs Check
        $exclude_ids = get_post_meta($ad->ID, '_bam_exclude_ids', true);
        if (!empty($exclude_ids)) {
            $excluded = array_map('trim', explode(',', $exclude_ids));
            if (in_array($current_post->ID, $excluded)) {
                return false;
            }
        }
        
        // Categories Check
        $allowed_categories = get_post_meta($ad->ID, '_bam_categories', true) ?: [];
        if (!empty($allowed_categories)) {
            $post_categories = wp_get_post_categories($current_post->ID);
            if (empty(array_intersect($allowed_categories, $post_categories))) {
                return false;
            }
        }
        
        // Tags Check
        $allowed_tags = get_post_meta($ad->ID, '_bam_tags', true) ?: [];
        if (!empty($allowed_tags)) {
            $post_tags = wp_get_post_tags($current_post->ID, ['fields' => 'ids']);
            if (empty(array_intersect($allowed_tags, $post_tags))) {
                return false;
            }
        }
        
        return true;
    }
    
    private function insert_ad_into_content($content, $ad) {
        $ad_html = $this->render_ad($ad);
        $position = get_post_meta($ad->ID, '_bam_position', true) ?: 'after_paragraph';
        $number = get_post_meta($ad->ID, '_bam_paragraph_number', true) ?: 2;
        
        switch ($position) {
            case 'before_content':
                $content = $ad_html . $content;
                break;
                
            case 'after_content':
                $content = $content . $ad_html;
                break;
                
            case 'after_paragraph':
                $content = $this->insert_after_element($content, $ad_html, '</p>', $number);
                break;
                
            case 'after_heading':
                $content = $this->insert_after_heading($content, $ad_html, $number);
                break;
                
            case 'middle_content':
                $content = $this->insert_in_middle($content, $ad_html);
                break;
        }
        
        return $content;
    }
    
    private function insert_after_element($content, $ad_html, $tag, $number) {
        $parts = explode($tag, $content);
        
        if (count($parts) < $number) {
            return $content . $ad_html;
        }
        
        $output = '';
        for ($i = 0; $i < count($parts); $i++) {
            $output .= $parts[$i];
            if ($i < count($parts) - 1) {
                $output .= $tag;
            }
            if ($i + 1 === $number) {
                $output .= $ad_html;
            }
        }
        
        return $output;
    }
    
    private function insert_after_heading($content, $ad_html, $number) {
        $pattern = '/<\/h[1-6]>/i';
        $parts = preg_split($pattern, $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_OFFSET_CAPTURE);
        
        preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE);
        
        if (count($matches[0]) < $number) {
            return $content . $ad_html;
        }
        
        $insert_pos = $matches[0][$number - 1][1] + strlen($matches[0][$number - 1][0]);
        
        return substr($content, 0, $insert_pos) . $ad_html . substr($content, $insert_pos);
    }
    
    private function insert_in_middle($content, $ad_html) {
        $parts = explode('</p>', $content);
        $middle = floor(count($parts) / 2);
        
        return $this->insert_after_element($content, $ad_html, '</p>', $middle);
    }
    
    private function render_ad($ad) {
        $content = get_post_meta($ad->ID, '_bam_ad_content', true);
        $content_type = get_post_meta($ad->ID, '_bam_content_type', true) ?: 'html';
        $devices = get_post_meta($ad->ID, '_bam_devices', true) ?: ['desktop', 'tablet', 'mobile'];
        
        // Device Classes
        $device_classes = [];
        if (!in_array('desktop', $devices)) {
            $device_classes[] = 'bam-hide-desktop';
        }
        if (!in_array('tablet', $devices)) {
            $device_classes[] = 'bam-hide-tablet';
        }
        if (!in_array('mobile', $devices)) {
            $device_classes[] = 'bam-hide-mobile';
        }
        
        $class_string = implode(' ', $device_classes);
        
        // Content verarbeiten
        if ($content_type === 'php') {
            ob_start();
            eval($content);
            $rendered_content = ob_get_clean();
        } else {
            // Shortcodes verarbeiten
            $rendered_content = do_shortcode($content);
        }
        
        return sprintf(
            '<div class="bam-ad-container bam-ad-%d %s">%s</div>',
            $ad->ID,
            esc_attr($class_string),
            $rendered_content
        );
    }
    
    public function add_device_styles() {
        ?>
        <style>
            .bam-ad-container {
                margin: 20px 0;
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
