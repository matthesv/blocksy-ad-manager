<?php
if (!defined('ABSPATH')) {
    exit;
}

class BAM_Post_Type {
    
    const POST_TYPE = 'bam_ad';
    
    public function register() {
        $labels = [
            'name'               => __('Anzeigen', 'blocksy-ad-manager'),
            'singular_name'      => __('Anzeige', 'blocksy-ad-manager'),
            'menu_name'          => __('Ad Manager', 'blocksy-ad-manager'),
            'add_new'            => __('Neue Anzeige', 'blocksy-ad-manager'),
            'add_new_item'       => __('Neue Anzeige erstellen', 'blocksy-ad-manager'),
            'edit_item'          => __('Anzeige bearbeiten', 'blocksy-ad-manager'),
            'view_item'          => __('Anzeige ansehen', 'blocksy-ad-manager'),
            'all_items'          => __('Alle Anzeigen', 'blocksy-ad-manager'),
        ];
        
        $args = [
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'menu_icon'           => 'dashicons-megaphone',
            'supports'            => ['title'],
            'capability_type'     => 'post',
            'hierarchical'        => false,
        ];
        
        register_post_type(self::POST_TYPE, $args);
    }
}
