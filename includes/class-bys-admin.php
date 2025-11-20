<?php
/**
 * Admin functionality for Book Your Stay
 */

if (!defined('ABSPATH')) {
    exit;
}

class BYS_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_post_bys_save_settings', array($this, 'save_settings'));
        add_action('wp_ajax_bys_generate_deep_link', array($this, 'ajax_generate_deep_link'));
        add_action('wp_ajax_bys_test_token', array($this, 'ajax_test_token'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Book Your Stay Settings', 'book-your-stay'),
            __('Book Your Stay', 'book-your-stay'),
            'manage_options',
            'book-your-stay',
            array($this, 'display_settings'),
            'dashicons-calendar-alt',
            30
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'book-your-stay') === false) {
            return;
        }
        
        wp_enqueue_style(
            'bys-admin-style',
            BYS_PLUGIN_URL . 'assets/css/admin-style.css',
            array(),
            BYS_PLUGIN_VERSION
        );
        
        wp_enqueue_script('jquery');
        
        // Localize script for AJAX
        wp_localize_script('jquery', 'ajaxurl', admin_url('admin-ajax.php'));
    }
    
    /**
     * Display settings page
     */
    public function display_settings() {
        $message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';
        $hotel_code = get_option('bys_hotel_code', '');
        $property_id = get_option('bys_property_id', '');
        $client_id = get_option('bys_client_id', '');
        $client_secret = get_option('bys_client_secret', '');
        $environment = get_option('bys_environment', 'uat');
        
        include BYS_PLUGIN_PATH . 'templates/admin/settings.php';
    }
    
    /**
     * Save settings
     */
    public function save_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        check_admin_referer('bys_settings_nonce');
        
        // Save hotel settings
        $hotel_code = isset($_POST['hotel_code']) ? sanitize_text_field($_POST['hotel_code']) : '';
        update_option('bys_hotel_code', $hotel_code);
        
        $property_id = isset($_POST['property_id']) ? sanitize_text_field($_POST['property_id']) : '';
        update_option('bys_property_id', $property_id);
        
        // Save API settings
        // Use stripslashes to handle WordPress magic quotes, then sanitize
        $client_id = isset($_POST['client_id']) ? sanitize_text_field(stripslashes($_POST['client_id'])) : '';
        // Remove any whitespace that might have been added
        $client_id = trim($client_id);
        update_option('bys_client_id', $client_id);
        
        $client_secret = isset($_POST['client_secret']) ? sanitize_text_field(stripslashes($_POST['client_secret'])) : '';
        // Remove any whitespace that might have been added
        $client_secret = trim($client_secret);
        update_option('bys_client_secret', $client_secret);
        
        $environment = isset($_POST['environment']) ? sanitize_text_field($_POST['environment']) : 'uat';
        update_option('bys_environment', $environment);
        
        // Clear cached token if credentials changed
        if (!empty($client_id) || !empty($client_secret)) {
            $oauth_token = BYS_OAuth::get_instance();
            $oauth_token->clear_token();
        }
        
        wp_redirect(admin_url('admin.php?page=book-your-stay&message=saved'));
        exit;
    }
    
    /**
     * AJAX handler for generating deep link
     */
    public function ajax_generate_deep_link() {
        check_ajax_referer('bys_booking_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'book-your-stay')));
        }
        
        $params = array();
        
        // Get parameters from POST
        if (isset($_POST['checkin'])) {
            $params['checkin'] = sanitize_text_field($_POST['checkin']);
        }
        if (isset($_POST['checkout'])) {
            $params['checkout'] = sanitize_text_field($_POST['checkout']);
        }
        if (isset($_POST['adults'])) {
            $params['adults'] = intval($_POST['adults']);
        }
        if (isset($_POST['children'])) {
            $params['children'] = intval($_POST['children']);
        }
        if (isset($_POST['rooms'])) {
            $params['rooms'] = intval($_POST['rooms']);
        }
        if (isset($_POST['promo']) && !empty($_POST['promo'])) {
            $params['Promo'] = sanitize_text_field($_POST['promo']);
        }
        if (isset($_POST['property_id']) && !empty($_POST['property_id'])) {
            $params['propertyID'] = intval($_POST['property_id']);
        }
        if (isset($_POST['hotel_code']) && !empty($_POST['hotel_code'])) {
            $params['pcode'] = sanitize_text_field($_POST['hotel_code']);
        }
        
        $deep_link = BYS_Deep_Link::get_instance();
        $link = $deep_link->generate_booking_link($params);
        
        wp_send_json_success(array('link' => $link));
    }
    
    /**
     * AJAX handler for testing token
     */
    public function ajax_test_token() {
        check_ajax_referer('bys_test_token', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'book-your-stay')));
        }
        
        $oauth = BYS_OAuth::get_instance();
        $token = $oauth->get_access_token();
        
        if ($token === false) {
            // Get detailed error message if available
            $error_message = get_option('bys_last_oauth_error', '');
            $environment = get_option('bys_environment', 'uat');
            
            if (!empty($error_message)) {
                $message = __('Failed to get access token: ', 'book-your-stay') . $error_message;
                
                // Add helpful hint if invalid_client error
                if (strpos($error_message, 'invalid_client') !== false) {
                    $message .= ' ' . sprintf(
                        __('Please verify: 1) Your credentials match the selected environment (%s), 2) Client ID and Secret are correct, 3) No extra spaces or characters.', 'book-your-stay'),
                        strtoupper($environment)
                    );
                }
            } else {
                $message = __('Failed to get access token. Please check your credentials.', 'book-your-stay');
            }
            wp_send_json_error(array('message' => $message));
        }
        
        $token_info = $oauth->get_token_info();
        
        wp_send_json_success(array(
            'message' => __('Token is valid', 'book-your-stay'),
            'token_info' => $token_info
        ));
    }
}

