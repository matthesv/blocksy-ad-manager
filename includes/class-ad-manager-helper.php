<?php
if (!defined('ABSPATH')) {
    exit;
}

class BAM_Helper {
    
    /**
     * Prüft ob ein bestimmtes Gerät aktiv ist
     */
    public static function is_device($device) {
        // Server-seitige Device-Erkennung (optional)
        if (!class_exists('Mobile_Detect')) {
            return true;
        }
        
        $detect = new Mobile_Detect();
        
        switch ($device) {
            case 'mobile':
                return $detect->isMobile() && !$detect->isTablet();
            case 'tablet':
                return $detect->isTablet();
            case 'desktop':
                return !$detect->isMobile();
            default:
                return true;
        }
    }
    
    /**
     * Holt alle aktiven Anzeigen für eine bestimmte Position
     */
    public static function get_ads_by_position($position) {
        return get_posts([
            'post_type'      => BAM_Post_Type::POST_TYPE,
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => '_bam_is_active',
                    'value' => '1',
                ],
                [
                    'key'   => '_bam_position',
                    'value' => $position,
                ]
            ]
        ]);
    }
    
    /**
     * Rendert eine einzelne Anzeige manuell
     */
    public static function render_ad_by_id($ad_id) {
        $ad = get_post($ad_id);
        
        if (!$ad || $ad->post_type !== BAM_Post_Type::POST_TYPE) {
            return '';
        }
        
        $frontend = new BAM_Frontend();
        return $frontend->render_ad($ad);
    }
}

// Shortcode für manuelle Anzeigen-Einbindung
add_shortcode('bam_ad', function($atts) {
    $atts = shortcode_atts(['id' => 0], $atts);
    
    if (empty($atts['id'])) {
        return '';
    }
    
    return BAM_Helper::render_ad_by_id($atts['id']);
});
