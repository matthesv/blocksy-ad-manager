<?php
/**
 * Plugin Name: Blocksy Ad Manager
 * Plugin URI: https://github.com/matthesv/blocksy-ad-manager
 * Description: Flexibles Anzeigen-Management für Blocksy Theme
 * Version: 1.4.0
 * Author: Matthes V.
 * Author URI: https://github.com/matthesv
 * Text Domain: blocksy-ad-manager
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BAM_VERSION', '1.4.0');
define('BAM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BAM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BAM_PLUGIN_FILE', __FILE__);

// =========================================
// PLUGIN UPDATE CHECKER (GitHub)
// =========================================
if (file_exists(BAM_PLUGIN_DIR . 'includes/plugin-update-checker/plugin-update-checker.php')) {
    require_once BAM_PLUGIN_DIR . 'includes/plugin-update-checker/plugin-update-checker.php';
    
    $bamUpdateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/matthesv/blocksy-ad-manager/',
        BAM_PLUGIN_FILE,
        'blocksy-ad-manager'
    );
    
    $bamUpdateChecker->setBranch('main');
}

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'BAM_';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    
    $class_name = str_replace($prefix, '', $class);
    $class_name = strtolower(str_replace('_', '-', $class_name));
    $file = BAM_PLUGIN_DIR . 'includes/class-ad-manager-' . $class_name . '.php';
    
    if (file_exists($file)) {
        require_once $file;
    }
});

// Plugin initialisieren
function bam_init() {
    $loader = new BAM_Loader();
    $loader->run();
}
add_action('plugins_loaded', 'bam_init');

// Aktivierung
register_activation_hook(__FILE__, function() {
    flush_rewrite_rules();
});
