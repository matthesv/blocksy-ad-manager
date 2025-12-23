<?php
if (!defined('ABSPATH')) {
    exit;
}

class BAM_Loader {
    
    private $post_type;
    private $admin;
    private $frontend;
    
    public function __construct() {
        $this->load_dependencies();
    }
    
    private function load_dependencies() {
        require_once BAM_PLUGIN_DIR . 'includes/class-ad-manager-helper.php';
        require_once BAM_PLUGIN_DIR . 'includes/class-ad-manager-post-type.php';
        require_once BAM_PLUGIN_DIR . 'includes/class-ad-manager-admin.php';
        require_once BAM_PLUGIN_DIR . 'includes/class-ad-manager-frontend.php';
        
        $this->post_type = new BAM_Post_Type();
        $this->admin = new BAM_Admin();
        $this->frontend = new BAM_Frontend();
    }
    
    public function run() {
        // Post Type registrieren
        add_action('init', [$this->post_type, 'register']);
        
        // Admin Hooks
        if (is_admin()) {
            add_action('add_meta_boxes', [$this->admin, 'add_meta_boxes']);
            add_action('save_post', [$this->admin, 'save_meta_boxes'], 10, 2);
            add_action('admin_enqueue_scripts', [$this->admin, 'enqueue_scripts']);
        }
        
        // Frontend Hooks
        add_filter('the_content', [$this->frontend, 'insert_ads'], 20);
        add_action('wp_head', [$this->frontend, 'add_device_styles']);
    }
}
