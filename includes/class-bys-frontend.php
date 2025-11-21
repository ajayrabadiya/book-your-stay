<?php
/**
 * Frontend functionality for Book Your Stay
 */

if (!defined('ABSPATH')) {
    exit;
}

class BYS_Frontend {
    
    private static $scripts_printed = false;
    
    public function __construct() {
        add_shortcode('book_your_stay', array($this, 'display_booking_widget'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('wp_ajax_bys_generate_deep_link', array($this, 'ajax_generate_deep_link'));
        add_action('wp_ajax_nopriv_bys_generate_deep_link', array($this, 'ajax_generate_deep_link'));
        add_action('wp_footer', array($this, 'print_footer_scripts'), 999);
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_frontend_scripts() {
        // Enqueue Flatpickr CSS
        wp_enqueue_style(
            'flatpickr',
            'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
            array(),
            '4.6.13'
        );
        
        wp_enqueue_style(
            'bys-frontend-style',
            BYS_PLUGIN_URL . 'assets/css/frontend-style.css',
            array('flatpickr'),
            BYS_PLUGIN_VERSION
        );
        
        // Enqueue Flatpickr JS
        wp_enqueue_script(
            'flatpickr',
            'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js',
            array(),
            '4.6.13',
            true
        );
        
        wp_enqueue_script(
            'bys-frontend-script',
            BYS_PLUGIN_URL . 'assets/js/frontend-script.js',
            array('jquery', 'flatpickr'),
            BYS_PLUGIN_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('bys-frontend-script', 'bysData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bys_booking_nonce')
        ));
    }
    
    /**
     * Display booking widget shortcode
     */
    public function display_booking_widget($atts) {
        $atts = shortcode_atts(array(
            'title' => 'Book Your Stay',
            'button_text' => 'Check Availability',
            'hotel_code' => '',
            'property_id' => ''
        ), $atts);
        
        // Ensure scripts and styles are enqueued (even if called via do_shortcode)
        $this->ensure_scripts_enqueued();
        
        // Get hotel code and property ID from shortcode or settings
        $hotel_code = !empty($atts['hotel_code']) ? $atts['hotel_code'] : get_option('bys_hotel_code', '');
        $property_id = !empty($atts['property_id']) ? $atts['property_id'] : get_option('bys_property_id', '');
        
        if (empty($hotel_code) && empty($property_id)) {
            return '<p style="color: red; padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">' . 
                   __('Please configure Hotel Code or Property ID in Book Your Stay settings.', 'book-your-stay') . 
                   '</p>';
        }
        
        // Make variables available to template
        $template_hotel_code = $hotel_code;
        $template_property_id = $property_id;
        
        ob_start();
        include BYS_PLUGIN_PATH . 'templates/frontend/booking-widget.php';
        return ob_get_clean();
    }
    
    /**
     * Ensure scripts and styles are enqueued (for do_shortcode usage)
     */
    private function ensure_scripts_enqueued() {
        static $scripts_enqueued = false;
        
        if ($scripts_enqueued) {
            return;
        }
        
        // Check if scripts are already enqueued
        if (!wp_style_is('flatpickr', 'enqueued')) {
            wp_enqueue_style(
                'flatpickr',
                'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
                array(),
                '4.6.13'
            );
        }
        
        if (!wp_style_is('bys-frontend-style', 'enqueued')) {
            wp_enqueue_style(
                'bys-frontend-style',
                BYS_PLUGIN_URL . 'assets/css/frontend-style.css',
                array('flatpickr'),
                BYS_PLUGIN_VERSION
            );
        }
        
        if (!wp_script_is('flatpickr', 'enqueued')) {
            wp_enqueue_script(
                'flatpickr',
                'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js',
                array(),
                '4.6.13',
                true
            );
        }
        
        if (!wp_script_is('bys-frontend-script', 'enqueued')) {
            wp_enqueue_script(
                'bys-frontend-script',
                BYS_PLUGIN_URL . 'assets/js/frontend-script.js',
                array('jquery', 'flatpickr'),
                BYS_PLUGIN_VERSION,
                true
            );
            
            // Localize script
            wp_localize_script('bys-frontend-script', 'bysData', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('bys_booking_nonce')
            ));
        }
        
        $scripts_enqueued = true;
    }
    
    /**
     * Print scripts in footer if enqueued late (for do_shortcode usage)
     */
    public function print_footer_scripts() {
        // Only print once
        if (self::$scripts_printed) {
            return;
        }
        
        // If scripts were enqueued but not printed, print them now
        if (wp_style_is('bys-frontend-style', 'enqueued') && !wp_style_is('bys-frontend-style', 'done')) {
            wp_print_styles('bys-frontend-style');
        }
        
        if (wp_script_is('bys-frontend-script', 'enqueued') && !wp_script_is('bys-frontend-script', 'done')) {
            wp_print_scripts('bys-frontend-script');
        }
        
        self::$scripts_printed = true;
    }
    
    /**
     * AJAX handler for generating deep link (frontend)
     */
    public function ajax_generate_deep_link() {
        check_ajax_referer('bys_booking_nonce', 'nonce');
        
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
        
        // Use shortcode attributes if provided
        if (isset($_POST['hotel_code']) && !empty($_POST['hotel_code'])) {
            $params['pcode'] = sanitize_text_field($_POST['hotel_code']);
        }
        if (isset($_POST['property_id']) && !empty($_POST['property_id'])) {
            $params['propertyID'] = intval($_POST['property_id']);
        }
        
        $deep_link = BYS_Deep_Link::get_instance();
        $link = $deep_link->generate_booking_link($params);
        
        wp_send_json_success(array('link' => $link));
    }
}

