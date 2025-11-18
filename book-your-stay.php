<?php
/**
 * Plugin Name: Book Your Stay
 * Plugin URI: https://example.com/book-your-stay
 * Description: Hotel booking widget with SHR CRS deep link integration. Display booking calendar and generate deep links to booking engine.
 * Version: 1.0.0
 * Author: DHR
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: book-your-stay
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('BYS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BYS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('BYS_PLUGIN_VERSION', '1.0.0');

// Include required files
require_once BYS_PLUGIN_PATH . 'includes/class-bys-oauth.php';
require_once BYS_PLUGIN_PATH . 'includes/class-bys-deep-link.php';
require_once BYS_PLUGIN_PATH . 'includes/class-bys-admin.php';
require_once BYS_PLUGIN_PATH . 'includes/class-bys-frontend.php';

/**
 * Main plugin class
 */
class Book_Your_Stay {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Activation hook
        register_activation_hook(__FILE__, array($this, 'activate'));
        
        // Initialize admin
        if (is_admin()) {
            new BYS_Admin();
        }
        
        // Initialize frontend
        new BYS_Frontend();
    }
    
    /**
     * Activate plugin
     */
    public function activate() {
        // Set default options
        if (get_option('bys_hotel_code') === false) {
            update_option('bys_hotel_code', '');
        }
        if (get_option('bys_property_id') === false) {
            update_option('bys_property_id', '');
        }
        if (get_option('bys_client_id') === false) {
            update_option('bys_client_id', '');
        }
        if (get_option('bys_client_secret') === false) {
            update_option('bys_client_secret', '');
        }
        if (get_option('bys_environment') === false) {
            update_option('bys_environment', 'uat');
        }
    }
}

// Initialize the plugin
Book_Your_Stay::get_instance();

