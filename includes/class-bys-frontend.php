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
        add_shortcode('hotel_rooms', array($this, 'display_hotel_rooms'));
        add_shortcode('hotel_rooms_debug', array($this, 'display_hotel_rooms_debug'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_filter('script_loader_tag', array($this, 'defer_frontend_script'), 10, 3);
        add_action('wp_ajax_bys_generate_deep_link', array($this, 'ajax_generate_deep_link'));
        add_action('wp_ajax_nopriv_bys_generate_deep_link', array($this, 'ajax_generate_deep_link'));
        add_action('wp_footer', array($this, 'print_footer_scripts'), 999);
        
        // Handle token clear request from frontend
        add_action('init', array($this, 'handle_token_clear_request'));
    }
    
    /**
     * Handle token clear request from frontend (for 403 errors)
     */
    public function handle_token_clear_request() {
        if (isset($_POST['bys_action']) && $_POST['bys_action'] === 'clear_token' && current_user_can('manage_options')) {
            check_admin_referer('bys_clear_token', 'bys_clear_token_nonce');
            
            $oauth = BYS_OAuth::get_instance();
            $oauth->clear_token();
            
            // Also clear API errors
            delete_option('bys_last_api_error');
            delete_option('bys_last_api_error_code');
            delete_option('bys_last_api_error_url');
            
            // Redirect to prevent form resubmission
            $redirect_url = remove_query_arg(array('bys_action'));
            if (isset($_SERVER['REQUEST_URI'])) {
                $redirect_url = $_SERVER['REQUEST_URI'];
            }
            wp_redirect($redirect_url);
            exit;
        }
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_frontend_scripts() {
        // Enqueue custom calendar styles
        wp_enqueue_style(
            'bys-frontend-style',
            BYS_PLUGIN_URL . 'assets/css/frontend-style.css',
            array(),
            BYS_PLUGIN_VERSION
        );
        
        // Enqueue custom calendar JavaScript
        wp_enqueue_script(
            'bys-frontend-script',
            BYS_PLUGIN_URL . 'assets/js/frontend-script.js',
            array('jquery'),
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
     * Add defer to frontend script so it doesn't block page rendering
     */
    public function defer_frontend_script($tag, $handle, $src) {
        if ('bys-frontend-script' !== $handle) {
            return $tag;
        }
        return str_replace(' src', ' defer src', $tag);
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
        if (!wp_style_is('bys-frontend-style', 'enqueued')) {
            wp_enqueue_style(
                'bys-frontend-style',
                BYS_PLUGIN_URL . 'assets/css/frontend-style.css',
                array(),
                BYS_PLUGIN_VERSION
            );
        }
        
        if (!wp_script_is('bys-frontend-script', 'enqueued')) {
            wp_enqueue_script(
                'bys-frontend-script',
                BYS_PLUGIN_URL . 'assets/js/frontend-script.js',
                array('jquery'),
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
    
    /**
     * Display hotel rooms shortcode
     */
    public function display_hotel_rooms($atts) {
        $atts = shortcode_atts(array(
            'hotel_code' => '',
            'property_id' => '',
            'checkin' => '',
            'checkout' => '',
            'adults' => 2,
            'children' => 0,
            'rooms' => 1,
            'cache_duration' => 3600
        ), $atts);
        
        // Ensure scripts and styles are enqueued
        $this->ensure_scripts_enqueued();
        
        // Get hotel code and property ID from shortcode or settings
        $hotel_code = !empty($atts['hotel_code']) ? $atts['hotel_code'] : get_option('bys_hotel_code', '');
        $property_id = !empty($atts['property_id']) ? $atts['property_id'] : get_option('bys_property_id', '');
        
        if (empty($hotel_code) && empty($property_id)) {
            return '<p style="color: red; padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">' . 
                   __('Please configure Hotel Code or Property ID in Book Your Stay settings.', 'book-your-stay') . 
                   '</p>';
        }
        
        // Prepare API parameters
        $api_params = array();
        if (!empty($hotel_code)) {
            $api_params['pcode'] = $hotel_code;
        }
        if (!empty($property_id)) {
            $api_params['propertyID'] = intval($property_id);
        }
        
        // Add date and occupancy parameters if provided
        if (!empty($atts['checkin'])) {
            $api_params['checkin'] = sanitize_text_field($atts['checkin']);
        }
        if (!empty($atts['checkout'])) {
            $api_params['checkout'] = sanitize_text_field($atts['checkout']);
        }
        if (!empty($atts['adults'])) {
            $api_params['adults'] = intval($atts['adults']);
        }
        if (!empty($atts['children'])) {
            $api_params['children'] = intval($atts['children']);
        }
        if (!empty($atts['rooms'])) {
            $api_params['rooms'] = intval($atts['rooms']);
        }
        
        // Get room list from API
        $api = BYS_API::get_instance();
        $cache_duration = !empty($atts['cache_duration']) ? intval($atts['cache_duration']) : 3600;
        
        // Log which hotel code is being used
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_code = !empty($hotel_code) ? 'Hotel Code: ' . $hotel_code : 'Property ID: ' . $property_id;
            error_log('Book Your Stay: Fetching rooms for ' . $log_code);
        }
        
        // Get rooms by merging Shop API (Rate Calendar) and IDS API (HotelDescriptiveInfo)
        $rooms = $api->get_cached_room_list($api_params, $cache_duration);
        
        // If API fails, show fallback room listing with booking link
        if ($rooms === false || empty($rooms)) {
            // Check if we have hotel code or property ID - if so, show fallback room listing
            if (!empty($hotel_code) || !empty($property_id)) {
                // Generate booking parameters
                $default_checkin = !empty($atts['checkin']) ? $atts['checkin'] : date('Y-m-d', strtotime('+1 day'));
                $default_checkout = !empty($atts['checkout']) ? $atts['checkout'] : date('Y-m-d', strtotime('+3 days'));
                $default_adults = !empty($atts['adults']) ? intval($atts['adults']) : 2;
                $default_children = !empty($atts['children']) ? intval($atts['children']) : 0;
                $default_rooms = !empty($atts['rooms']) ? intval($atts['rooms']) : 1;
                
                // Create fallback room listing that links directly to booking engine
                // This provides a better UX even when API is unavailable
                $deep_link = BYS_Deep_Link::get_instance();
                $booking_params = array(
                    'pcode' => $hotel_code,
                    'propertyID' => $property_id,
                    'checkin' => $default_checkin,
                    'checkout' => $default_checkout,
                    'adults' => $default_adults,
                    'children' => $default_children,
                    'rooms' => $default_rooms
                );
                $booking_url = $deep_link->generate_booking_link($booking_params);
                
                // Create a fallback room entry that links to booking engine
                $fallback_rooms = array(
                    array(
                        'code' => 'VIEW_ALL',
                        'name' => __('View All Available Rooms', 'book-your-stay'),
                        'description' => __('Click below to view all available rooms, rates, and special offers for your selected dates.', 'book-your-stay'),
                        'size' => '',
                        'view' => '',
                        'max_occupancy' => $default_adults,
                        'amenities' => array(),
                        'image' => '',
                        'from_price' => null,
                        'currency' => 'ZAR',
                        'booking_url' => $booking_url
                    )
                );
                
                // Make variables available to template
                $template_rooms = $fallback_rooms;
                $template_hotel_code = $hotel_code;
                $template_property_id = $property_id;
                $template_atts = $atts;
                $template_is_fallback = true;
                
                ob_start();
                include BYS_PLUGIN_PATH . 'templates/frontend/hotel-rooms.php';
                $output = ob_get_clean();
                
                // Add a notice for admins if debug is enabled
                if (defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options')) {
                    // Get last OAuth error if available
                    $oauth_error = get_option('bys_last_oauth_error', '');
                    $api = BYS_API::get_instance();
                    $oauth = BYS_OAuth::get_instance();
                    $token_info = $oauth->get_token_info();
                    $has_token = $oauth->is_token_valid();
                    
                    $output .= '<div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; font-size: 12px; font-family: monospace;">';
                    $output .= '<strong>Admin Debug Notice:</strong> API is unavailable. Showing fallback booking link.<br><br>';
                    $output .= '<strong>Configuration:</strong><br>';
                    $output .= 'Hotel Code: ' . esc_html($hotel_code) . '<br>';
                    $output .= 'Property ID: ' . esc_html($property_id ?: 'Not set') . '<br>';
                    $output .= 'Environment: ' . esc_html(get_option('bys_environment', 'uat')) . '<br><br>';
                    
                    $output .= '<strong>OAuth Status:</strong><br>';
                    $output .= 'Has Access Token: ' . ($has_token ? '✓ Yes' : '✗ No') . '<br>';
                    if ($has_token) {
                        $output .= 'Token Expires In: ' . ($token_info['access_token_expires_in'] > 0 ? round($token_info['access_token_expires_in'] / 60) . ' minutes' : 'Expired') . '<br>';
                    }
                    if (!empty($oauth_error)) {
                        $output .= 'OAuth Error: <span style="color: red;">' . esc_html($oauth_error) . '</span><br>';
                    }
                    $output .= '<br>';
                    
                    // Show last API error if available
                    $api_error = get_option('bys_last_api_error', '');
                    $api_error_code = get_option('bys_last_api_error_code', '');
                    $api_error_url = get_option('bys_last_api_error_url', '');
                    if (!empty($api_error)) {
                        $output .= '<strong>Last API Error:</strong><br>';
                        $output .= '<span style="color: red;">HTTP ' . esc_html($api_error_code) . ': ' . esc_html($api_error) . '</span><br>';
                        if (!empty($api_error_url)) {
                            $output .= 'URL: ' . esc_html($api_error_url) . '<br>';
                        }
                        
                        // Special handling for 403 errors - likely scope issue
                        if ($api_error_code === '403') {
                            $output .= '<br>';
                            $output .= '<div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; margin-top: 10px; border-radius: 4px;">';
                            $output .= '<strong style="color: #721c24;">⚠️ 403 Forbidden Error Detected</strong><br>';
                            $output .= 'This usually means the OAuth token does not have the required scope "wsapi.hoteldetails.read".<br><br>';
                            $output .= '<strong>Solution:</strong><br>';
                            $output .= '1. Click the button below to clear the token cache and force a refresh<br>';
                            $output .= '2. The system will request a new token with the correct scope<br>';
                            $output .= '3. Refresh this page after clearing the token<br><br>';
                            $output .= '<form method="post" action="" style="margin: 0;">';
                            $output .= wp_nonce_field('bys_clear_token', 'bys_clear_token_nonce', true, false);
                            $output .= '<input type="hidden" name="bys_action" value="clear_token">';
                            $output .= '<button type="submit" style="background: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: bold;">Clear Token Cache & Force Refresh</button>';
                            $output .= '</form>';
                            $output .= '</div>';
                        }
                        
                        $output .= '<br>';
                    }
                    
                    $output .= '<strong>Troubleshooting:</strong><br>';
                    $output .= '1. Check WordPress debug.log for detailed API request/response logs<br>';
                    $output .= '2. Verify OAuth credentials in plugin settings<br>';
                    $output .= '3. Ensure the scope "wsapi.hoteldetails.read" is included in token request<br>';
                    $output .= '4. Verify hotel code "' . esc_html($hotel_code) . '" is correct<br>';
                    $output .= '5. Check API endpoint: ' . esc_html((get_option('bys_environment', 'uat') === 'production' ? 'https://api.shrglobal.com/shop' : 'https://apiuat.shrglobal.com/shop') . '/hotelDetails/' . $hotel_code . '/room?channelId=1') . '<br>';
                    $output .= '</div>';
                }
                
                return $output;
            } else {
                $error_message = __('No rooms available at this time. Please try again later.', 'book-your-stay');
                
                // Add debug info if WP_DEBUG is enabled
                if (defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options')) {
                    $error_message .= '<br><small style="color: #999;">Debug: Hotel Code: ' . esc_html($hotel_code) . ', Property ID: ' . esc_html($property_id) . '</small>';
                }
                
                return '<p style="color: #666; padding: 15px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px;">' . 
                       $error_message . 
                       '</p>';
            }
        }
        
        // Make variables available to template
        $template_rooms = $rooms;
        $template_hotel_code = $hotel_code;
        $template_property_id = $property_id;
        $template_atts = $atts;
        
        ob_start();
        include BYS_PLUGIN_PATH . 'templates/frontend/hotel-rooms.php';
        return ob_get_clean();
    }
    
    /**
     * Debug version of hotel rooms shortcode (shows API response details)
     */
    public function display_hotel_rooms_debug($atts) {
        // Only show to admins
        if (!current_user_can('manage_options')) {
            return '<p>Debug mode is only available to administrators.</p>';
        }
        
        $atts = shortcode_atts(array(
            'hotel_code' => '',
            'property_id' => '',
        ), $atts);
        
        $hotel_code = !empty($atts['hotel_code']) ? $atts['hotel_code'] : get_option('bys_hotel_code', '');
        $property_id = !empty($atts['property_id']) ? $atts['property_id'] : get_option('bys_property_id', '');
        
        $api = BYS_API::get_instance();
        $oauth = BYS_OAuth::get_instance();
        
        $debug_info = array();
        $debug_info['hotel_code'] = $hotel_code;
        $debug_info['property_id'] = $property_id;
        
        // OAuth Configuration
        $client_id = get_option('bys_client_id', '');
        $client_secret = get_option('bys_client_secret', '');
        $environment = get_option('bys_environment', 'uat');
        
        $debug_info['oauth_config'] = array(
            'client_id_set' => !empty($client_id),
            'client_id_length' => strlen($client_id),
            'client_secret_set' => !empty($client_secret),
            'client_secret_length' => strlen($client_secret),
            'environment' => $environment,
            'token_endpoint' => ($environment === 'production') 
                ? 'https://id.shrglobal.com/connect/token'
                : 'https://iduat.shrglobal.com/connect/token'
        );
        
        // Check for OAuth errors
        $last_oauth_error = get_option('bys_last_oauth_error', '');
        $debug_info['oauth_error'] = $last_oauth_error;
        
        // Token status
        $debug_info['has_token'] = $oauth->is_token_valid();
        $debug_info['token_info'] = $oauth->get_token_info();
        
        // Try to get token (this will attempt to fetch if not available)
        $test_token = $oauth->get_access_token();
        $debug_info['token_fetch_attempt'] = array(
            'success' => !empty($test_token),
            'token_length' => $test_token ? strlen($test_token) : 0,
            'token_preview' => $test_token ? substr($test_token, 0, 20) . '...' : 'No token'
        );
        
        // Update token info after fetch attempt
        $debug_info['token_info_after_fetch'] = $oauth->get_token_info();
        $debug_info['oauth_error_after_fetch'] = get_option('bys_last_oauth_error', '');
        
        // Test API calls
        $api_params = array();
        if (!empty($hotel_code)) {
            $api_params['pcode'] = $hotel_code;
        }
        if (!empty($property_id)) {
            $api_params['propertyID'] = intval($property_id);
        }
        
        $debug_info['api_params'] = $api_params;
        
        // Test descriptive info
        $descriptive_info = $api->get_hotel_descriptive_info($api_params);
        $debug_info['descriptive_info'] = $descriptive_info;
        $debug_info['descriptive_info_keys'] = is_array($descriptive_info) ? array_keys($descriptive_info) : 'Not an array';
        $debug_info['descriptive_info_type'] = gettype($descriptive_info);
        $debug_info['descriptive_info_structure'] = $this->get_array_structure($descriptive_info, 4);
        $debug_info['parsing_analysis'] = $this->analyze_descriptive_info_structure($descriptive_info);
        
        // Test rate calendar
        $rate_calendar = $api->get_rate_calendar($api_params);
        $debug_info['rate_calendar'] = $rate_calendar;
        $debug_info['rate_calendar_type'] = gettype($rate_calendar);
        $debug_info['rate_calendar_keys'] = is_array($rate_calendar) ? array_keys($rate_calendar) : 'Not an array';
        $debug_info['rate_calendar_structure'] = $this->get_array_structure($rate_calendar, 4);
        $debug_info['rate_parsing_analysis'] = $this->analyze_rate_calendar_structure($rate_calendar);
        
        // Test room list
        $rooms = $api->get_room_list($api_params);
        $debug_info['rooms'] = $rooms;
        $debug_info['rooms_count'] = is_array($rooms) ? count($rooms) : 0;
        $debug_info['rooms_type'] = gettype($rooms);
        
        ob_start();
        ?>
        <div style="background: #fff; border: 2px solid #333; padding: 20px; margin: 20px 0; font-family: monospace; font-size: 12px;">
            <h3 style="margin-top: 0;">Hotel Rooms API Debug Information</h3>
            
            <!-- Summary Section -->
            <div style="background: #f0f0f0; padding: 15px; margin-bottom: 20px; border-left: 4px solid #<?php echo !empty($debug_info['token_fetch_attempt']['success']) ? '28a745' : 'dc3545'; ?>;">
                <h4 style="margin-top: 0;">Quick Status</h4>
                <ul style="margin: 0; padding-left: 20px;">
                    <li><strong>OAuth Token:</strong> <?php echo !empty($debug_info['token_fetch_attempt']['success']) ? '<span style="color: green;">✓ Valid</span>' : '<span style="color: red;">✗ Missing/Invalid</span>'; ?></li>
                    <li><strong>Client ID:</strong> <?php echo $debug_info['oauth_config']['client_id_set'] ? '<span style="color: green;">✓ Set</span>' : '<span style="color: red;">✗ Not Set</span>'; ?></li>
                    <li><strong>Client Secret:</strong> <?php echo $debug_info['oauth_config']['client_secret_set'] ? '<span style="color: green;">✓ Set</span>' : '<span style="color: red;">✗ Not Set</span>'; ?></li>
                    <li><strong>Environment:</strong> <?php echo strtoupper($debug_info['oauth_config']['environment']); ?></li>
                    <?php if (!empty($debug_info['oauth_error_after_fetch'])): ?>
                        <li><strong>Last Error:</strong> <span style="color: red;"><?php echo esc_html($debug_info['oauth_error_after_fetch']); ?></span></li>
                    <?php endif; ?>
                    <li><strong>Rooms Found:</strong> <?php echo $debug_info['rooms_count']; ?></li>
                </ul>
                <?php if (empty($debug_info['token_fetch_attempt']['success'])): ?>
                    <p style="background: #fff3cd; padding: 10px; margin-top: 10px; border: 1px solid #ffc107;">
                        <strong>⚠️ Issue Detected:</strong> OAuth token is not available. 
                        <?php if (!$debug_info['oauth_config']['client_id_set'] || !$debug_info['oauth_config']['client_secret_set']): ?>
                            Please configure your <a href="<?php echo admin_url('admin.php?page=book-your-stay'); ?>">OAuth credentials in the plugin settings</a>.
                        <?php else: ?>
                            Check the error message above and verify your credentials are correct for the <?php echo strtoupper($debug_info['oauth_config']['environment']); ?> environment.
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
            
            <!-- API Response Analysis -->
            <?php if (!empty($debug_info['parsing_analysis']) || !empty($debug_info['rate_parsing_analysis'])): ?>
            <div style="margin-top: 20px; padding: 15px; background: #e7f3ff; border-left: 4px solid #2196F3;">
                <h4 style="margin-top: 0;">API Response Structure Analysis</h4>
                <?php if (!empty($debug_info['parsing_analysis'])): ?>
                <div style="margin-bottom: 15px;">
                    <strong>HotelDescriptiveInfo Structure:</strong>
                    <pre style="background: #fff; padding: 10px; margin-top: 5px; font-size: 11px; overflow-x: auto;"><?php echo esc_html(print_r($debug_info['parsing_analysis'], true)); ?></pre>
                </div>
                <?php endif; ?>
                <?php if (!empty($debug_info['rate_parsing_analysis'])): ?>
                <div>
                    <strong>Rate Calendar Structure:</strong>
                    <pre style="background: #fff; padding: 10px; margin-top: 5px; font-size: 11px; overflow-x: auto;"><?php echo esc_html(print_r($debug_info['rate_parsing_analysis'], true)); ?></pre>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Full Debug Data -->
            <details style="margin-top: 20px;">
                <summary style="cursor: pointer; font-weight: bold; padding: 10px; background: #e9ecef; border: 1px solid #ddd;">Show Full Debug Data</summary>
                <div style="margin-top: 10px;">
                    <strong>Response Structures:</strong>
                    <details style="margin-top: 5px;">
                        <summary style="cursor: pointer; padding: 5px; background: #f5f5f5;">HotelDescriptiveInfo Structure</summary>
                        <pre style="background: #f5f5f5; padding: 15px; overflow-x: auto; max-height: 400px; overflow-y: auto; margin-top: 5px; font-size: 11px;"><?php echo esc_html(print_r($debug_info['descriptive_info_structure'], true)); ?></pre>
                    </details>
                    <details style="margin-top: 5px;">
                        <summary style="cursor: pointer; padding: 5px; background: #f5f5f5;">Rate Calendar Structure</summary>
                        <pre style="background: #f5f5f5; padding: 15px; overflow-x: auto; max-height: 400px; overflow-y: auto; margin-top: 5px; font-size: 11px;"><?php echo esc_html(print_r($debug_info['rate_calendar_structure'], true)); ?></pre>
                    </details>
                    <details style="margin-top: 5px;">
                        <summary style="cursor: pointer; padding: 5px; background: #f5f5f5;">Full Response Data</summary>
                        <div style="margin-top: 5px;">
                            <strong>Descriptive Info (Full):</strong>
                            <pre style="background: #f5f5f5; padding: 15px; overflow-x: auto; max-height: 500px; overflow-y: auto; font-size: 11px; white-space: pre-wrap; word-wrap: break-word;"><?php 
                            $desc_print = print_r($debug_info['descriptive_info'], true);
                            echo esc_html($desc_print);
                            ?></pre>
                        </div>
                        <div style="margin-top: 10px;">
                            <strong>Rate Calendar (Full):</strong>
                            <pre style="background: #f5f5f5; padding: 15px; overflow-x: auto; max-height: 500px; overflow-y: auto; font-size: 11px; white-space: pre-wrap; word-wrap: break-word;"><?php 
                            $rate_print = print_r($debug_info['rate_calendar'], true);
                            echo esc_html($rate_print);
                            ?></pre>
                        </div>
                    </details>
                </div>
            </details>
            
            <p style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd;">
                <strong>Note:</strong> Check WordPress debug.log for detailed API request/response logs.<br>
                <strong>Settings:</strong> <a href="<?php echo admin_url('admin.php?page=book-your-stay'); ?>">Configure Book Your Stay Settings</a>
            </p>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get array structure for debugging
     */
    private function get_array_structure($data, $depth = 2, $current_depth = 0) {
        if ($current_depth >= $depth || !is_array($data)) {
            return gettype($data);
        }
        
        $structure = array();
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $structure[$key] = $this->get_array_structure($value, $depth, $current_depth + 1);
            } else {
                $structure[$key] = gettype($value) . (is_string($value) && strlen($value) > 50 ? ' (long string)' : '');
            }
        }
        return $structure;
    }
    
    /**
     * Analyze descriptive info structure to help with parsing
     */
    private function analyze_descriptive_info_structure($data) {
        $analysis = array();
        
        // Check for common structures
        $paths = array(
            'HotelDescriptiveInfoRS.HotelDescriptiveInfo.RoomTypes.RoomType',
            'RoomTypes.RoomType',
            'roomTypes',
            'rooms',
            'data'
        );
        
        foreach ($paths as $path) {
            $keys = explode('.', $path);
            $current = $data;
            $found = true;
            
            foreach ($keys as $key) {
                if (isset($current[$key])) {
                    $current = $current[$key];
                } else {
                    $found = false;
                    break;
                }
            }
            
            if ($found) {
                $analysis[$path] = array(
                    'exists' => true,
                    'type' => gettype($current),
                    'is_array' => is_array($current),
                    'count' => is_array($current) ? count($current) : 'N/A'
                );
            } else {
                $analysis[$path] = array('exists' => false);
            }
        }
        
        return $analysis;
    }
    
    /**
     * Analyze rate calendar structure to help with parsing
     */
    private function analyze_rate_calendar_structure($data) {
        $analysis = array();
        
        // Check for common structures
        $paths = array(
            'RateCalendar.Rates.Rate',
            'RateCalendar.RoomTypes.RoomType',
            'rates',
            'Rate',
            'data'
        );
        
        foreach ($paths as $path) {
            $keys = explode('.', $path);
            $current = $data;
            $found = true;
            
            foreach ($keys as $key) {
                if (isset($current[$key])) {
                    $current = $current[$key];
                } else {
                    $found = false;
                    break;
                }
            }
            
            if ($found) {
                $analysis[$path] = array(
                    'exists' => true,
                    'type' => gettype($current),
                    'is_array' => is_array($current),
                    'count' => is_array($current) ? count($current) : 'N/A'
                );
            } else {
                $analysis[$path] = array('exists' => false);
            }
        }
        
        return $analysis;
    }
}

