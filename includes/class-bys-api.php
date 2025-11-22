<?php
/**
 * API Integration for SHR Shop API and IDS Distribution Pull API
 */

if (!defined('ABSPATH')) {
    exit;
}

class BYS_API {
    
    private static $instance = null;
    private $oauth;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->oauth = BYS_OAuth::get_instance();
    }
    
    /**
     * Get base API URL based on environment
     */
    private function get_api_base_url() {
        $environment = get_option('bys_environment', 'uat');
        return ($environment === 'production') 
            ? 'https://api.shrglobal.com'
            : 'https://apiuat.shrglobal.com';
    }
    
    /**
     * Get Shop API base URL based on environment
     */
    private function get_shop_api_base_url() {
        $environment = get_option('bys_environment', 'uat');
        return ($environment === 'production') 
            ? 'https://api.shrglobal.com/shop'
            : 'https://apiuat.shrglobal.com/shop';
    }
    
    /**
     * Get IDS Distribution Pull API base URL
     */
    private function get_ids_base_url() {
        $environment = get_option('bys_environment', 'uat');
        // IDS API typically uses different endpoints - adjust based on actual documentation
        return ($environment === 'production') 
            ? 'https://ids.shrglobal.com'
            : 'https://idsuat.shrglobal.com';
    }
    
    /**
     * Make authenticated API request
     */
    private function make_api_request($endpoint, $method = 'GET', $body = null, $is_ids = false) {
        $access_token = $this->oauth->get_access_token();
        
        if (!$access_token) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Book Your Stay API: No access token available');
                error_log('Book Your Stay API: Token fetch failed. Check OAuth credentials and scope.');
            }
            return false;
        }
        
        // Log token info for debugging (first 20 chars only for security)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $token_preview = substr($access_token, 0, 20) . '...';
            error_log('Book Your Stay API: Using access token: ' . $token_preview);
        }
        
        // Check if this is a room endpoint and we got a 403 before - force token refresh
        $is_room_endpoint = (strpos($endpoint, '/hotelDetails/') !== false && strpos($endpoint, '/room') !== false);
        $last_api_error_code = get_option('bys_last_api_error_code', '');
        
        if ($is_room_endpoint && $last_api_error_code === '403') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Book Your Stay API: Previous 403 error detected for room endpoint. Forcing token refresh with correct scope.');
            }
            // Clear token and force refresh with correct scope
            $this->oauth->clear_token();
            $access_token = $this->oauth->get_access_token();
            
            if (!$access_token) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Book Your Stay API: Failed to get new token after 403 error');
                }
                return false;
            }
        }
        
        // Determine base URL based on endpoint type
        if ($is_ids) {
            $base_url = $this->get_ids_base_url();
        } elseif (strpos($endpoint, '/hotelDetails/') !== false || strpos($endpoint, '/shop/') === 0) {
            // Use shop API base URL for room endpoints
            $base_url = $this->get_shop_api_base_url();
        } else {
            $base_url = $this->get_api_base_url();
        }
        $url = $base_url . $endpoint;
        
        // Determine Accept header based on endpoint
        // Rate Calendar API returns XML, IDS API may return XML or JSON
        $is_rate_calendar = (strpos($endpoint, 'ratecalendar') !== false);
        $is_room_endpoint = (strpos($endpoint, '/hotelDetails/') !== false && strpos($endpoint, '/room') !== false);
        $accept_header = 'application/json';
        if ($is_ids || $is_rate_calendar) {
            $accept_header = 'application/xml, application/json, text/xml';
        }
        
        // Build headers - for GET requests, don't include Content-Type
        $headers = array(
            'Authorization' => 'Bearer ' . $access_token,
            'Accept' => $accept_header
        );
        
        // Only add Content-Type for POST/PUT requests with body
        if ($method !== 'GET' && $body !== null) {
            $headers['Content-Type'] = 'application/json';
        }
        
        $args = array(
            'method' => $method,
            'headers' => $headers,
            'timeout' => 30,
            'sslverify' => true
        );
        
        if ($body !== null) {
            $args['body'] = is_array($body) ? json_encode($body) : $body;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Book Your Stay API: Making request to ' . $url);
            error_log('Book Your Stay API: Method: ' . $method);
            error_log('Book Your Stay API: Headers: ' . print_r($headers, true));
            if ($is_room_endpoint) {
                error_log('Book Your Stay API: Room endpoint detected - URL: ' . $url);
            }
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Book Your Stay API Error: ' . $response->get_error_message());
            }
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Book Your Stay API: Response code: ' . $response_code);
            error_log('Book Your Stay API: Response body (first 500 chars): ' . substr($response_body, 0, 500));
        }
        
        if ($response_code !== 200) {
            // Try to parse error response for more details
            $error_data = json_decode($response_body, true);
            $error_message = 'HTTP ' . $response_code;
            
            if (is_array($error_data)) {
                if (isset($error_data['error'])) {
                    $error_message = $error_data['error'];
                    if (isset($error_data['error_description'])) {
                        $error_message .= ': ' . $error_data['error_description'];
                    } elseif (isset($error_data['message'])) {
                        $error_message .= ': ' . $error_data['message'];
                    }
                } elseif (isset($error_data['message'])) {
                    $error_message = $error_data['message'];
                }
            } else {
                $error_message .= ': ' . substr($response_body, 0, 200);
            }
            
            // Store error for display
            update_option('bys_last_api_error', $error_message);
            update_option('bys_last_api_error_code', $response_code);
            update_option('bys_last_api_error_url', $url);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Book Your Stay API Error: HTTP ' . $response_code);
                error_log('Book Your Stay API Error: ' . $error_message);
                error_log('Book Your Stay API Error: Full response body: ' . $response_body);
                error_log('Book Your Stay API Error: Request URL: ' . $url);
                error_log('Book Your Stay API Error: Request headers: ' . print_r($args['headers'], true));
                if (is_array($error_data)) {
                    error_log('Book Your Stay API Error Details: ' . print_r($error_data, true));
                }
            }
            
            return false;
        }
        
        // Clear any previous errors on success
        delete_option('bys_last_api_error');
        delete_option('bys_last_api_error_code');
        delete_option('bys_last_api_error_url');
        
        // Try to parse as JSON first
        $decoded = json_decode($response_body, true);
        
        // If JSON parsing failed, try XML (for IDS API and Shop API Rate Calendar)
        $is_rate_calendar = (strpos($endpoint, 'ratecalendar') !== false);
        if ($decoded === null && ($is_ids || $is_rate_calendar)) {
            if (function_exists('simplexml_load_string')) {
                libxml_use_internal_errors(true);
                $xml = simplexml_load_string($response_body);
                if ($xml !== false) {
                    // Convert XML to array
                    $decoded = json_decode(json_encode($xml), true);
                    
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('Book Your Stay API: Successfully parsed XML response');
                    }
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        $errors = libxml_get_errors();
                        error_log('Book Your Stay API: XML parsing errors: ' . print_r($errors, true));
                        error_log('Book Your Stay API: Response body (first 500 chars): ' . substr($response_body, 0, 500));
                    }
                }
            }
        }
        
        return $decoded;
    }
    
    /**
     * Get Rate Calendar from Shop API
     * Used to determine "from" pricing for rooms
     */
    public function get_rate_calendar($params = array()) {
        // Get from params first, then fallback to settings
        $property_id = isset($params['propertyID']) && !empty($params['propertyID']) 
            ? intval($params['propertyID']) 
            : get_option('bys_property_id', '');
        $hotel_code = isset($params['pcode']) && !empty($params['pcode']) 
            ? sanitize_text_field($params['pcode']) 
            : get_option('bys_hotel_code', '');
        
        if (empty($property_id) && empty($hotel_code)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Book Your Stay API: No property ID or hotel code available for rate calendar');
            }
            return false;
        }
        
        // Build query parameters for Shop API Rate Calendar
        $query_params = array();
        
        if (!empty($property_id)) {
            $query_params['propertyID'] = $property_id;
        }
        if (!empty($hotel_code)) {
            $query_params['pcode'] = $hotel_code;
        }
        
        // Add date range if provided (required for rate calendar)
        if (!empty($params['checkin'])) {
            $checkin = sanitize_text_field($params['checkin']);
            $query_params['checkin'] = $checkin;
        } else {
            // Default to tomorrow if not provided
            $query_params['checkin'] = date('Y-m-d', strtotime('+1 day'));
        }
        
        if (!empty($params['checkout'])) {
            $checkout = sanitize_text_field($params['checkout']);
            $query_params['checkout'] = $checkout;
        } else {
            // Default to +3 days if not provided
            $query_params['checkout'] = date('Y-m-d', strtotime('+3 days'));
        }
        
        // Add occupancy if provided
        if (!empty($params['adults'])) {
            $query_params['adults'] = intval($params['adults']);
        }
        if (!empty($params['children'])) {
            $query_params['children'] = intval($params['children']);
        }
        if (!empty($params['rooms'])) {
            $query_params['rooms'] = intval($params['rooms']);
        }
        
        // Add RateReturnType - use MinPerRoom to get lowest rate for each room type
        // This is the best option for displaying "from" prices per room
        $query_params['RateReturnType'] = 'MinPerRoom';
        
        // Add month/year for rate calendar (API computes rates per day for the month)
        if (!empty($params['checkin'])) {
            $checkin_date = new DateTime($params['checkin']);
            $query_params['month'] = $checkin_date->format('m');
            $query_params['year'] = $checkin_date->format('Y');
        } else {
            $query_params['month'] = date('m');
            $query_params['year'] = date('Y');
        }
        
        // Build endpoint with query string
        // Shop API Rate Calendar endpoint - returns XML format
        $endpoint = '/wsapi/shop/ratecalendar';
        if (!empty($query_params)) {
            $endpoint .= '?' . http_build_query($query_params);
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Book Your Stay API: Calling Rate Calendar endpoint: ' . $endpoint);
        }
        
        // Rate Calendar API returns XML, so we need to handle it differently
        $response = $this->make_api_request($endpoint, 'GET', null, false);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if ($response === false) {
                error_log('Book Your Stay API: Failed to get rate calendar from ' . $endpoint);
            } else {
                error_log('Book Your Stay API: Successfully received rate calendar data');
            }
        }
        
        return $response;
    }
    
    /**
     * Get Hotel Descriptive Info from IDS Distribution Pull API
     * Used to get room types, descriptions, amenities, etc.
     */
    public function get_hotel_descriptive_info($params = array()) {
        // Get from params first, then fallback to settings
        $property_id = isset($params['propertyID']) && !empty($params['propertyID']) 
            ? intval($params['propertyID']) 
            : get_option('bys_property_id', '');
        $hotel_code = isset($params['pcode']) && !empty($params['pcode']) 
            ? sanitize_text_field($params['pcode']) 
            : get_option('bys_hotel_code', '');
        
        if (empty($property_id) && empty($hotel_code)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Book Your Stay API: No property ID or hotel code available for descriptive info');
            }
            return false;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_code = !empty($hotel_code) ? 'Hotel Code: ' . $hotel_code : 'Property ID: ' . $property_id;
            error_log('Book Your Stay API: Requesting descriptive info for ' . $log_code);
        }
        
        // IDS API typically uses XML/OTA format
        // Build OTA_HotelDescriptiveInfoRQ request
        $request_data = array(
            'OTA_HotelDescriptiveInfoRQ' => array(
                '@attributes' => array(
                    'Version' => '2.5',
                    'xmlns' => 'http://www.opentravel.org/OTA/2003/05'
                ),
                'HotelDescriptiveInfo' => array(
                    'HotelCode' => !empty($hotel_code) ? $hotel_code : '',
                    'PropertyID' => !empty($property_id) ? $property_id : ''
                )
            )
        );
        
        // IDS Distribution Pull API - HotelDescriptiveInfo endpoint
        // This API typically uses XML/OTA format
        $endpoint = '/ids/hoteldescriptiveinfo';
        
        $query_params = array();
        if (!empty($property_id)) {
            $query_params['propertyID'] = $property_id;
        }
        if (!empty($hotel_code)) {
            $query_params['hotelCode'] = $hotel_code;
        }
        
        // Add hotel code as pcode if needed
        if (!empty($hotel_code) && !isset($query_params['hotelCode'])) {
            $query_params['pcode'] = $hotel_code;
        }
        
        if (!empty($query_params)) {
            $endpoint .= '?' . http_build_query($query_params);
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Book Your Stay API: Calling HotelDescriptiveInfo endpoint: ' . $endpoint);
        }
        
        $response = $this->make_api_request($endpoint, 'GET', null, true);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if ($response === false) {
                error_log('Book Your Stay API: Failed to get hotel descriptive info from ' . $endpoint);
            } else {
                error_log('Book Your Stay API: Successfully received descriptive info');
            }
        }
        
        return $response;
    }
    
    /**
     * Get rooms from Shop API
     * Uses the new /hotelDetails/:hotelCode/room endpoint
     */
    public function get_rooms($params = array()) {
        // Get from params first, then fallback to settings
        $hotel_code = isset($params['pcode']) && !empty($params['pcode']) 
            ? sanitize_text_field($params['pcode']) 
            : get_option('bys_hotel_code', '');
        
        if (empty($hotel_code)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Book Your Stay API: No hotel code available for rooms');
            }
            return false;
        }
        
        // Build endpoint: /hotelDetails/:hotelCode/room?channelId=1
        // Don't URL encode the hotel code in the path - it should be used as-is
        $endpoint = '/hotelDetails/' . $hotel_code . '/room';
        
        // Add query parameters - only channelId is required
        $query_params = array(
            'channelId' => 1
        );
        
        // Add optional date parameters if provided (for filtering)
        if (!empty($params['checkin'])) {
            $query_params['checkin'] = sanitize_text_field($params['checkin']);
        }
        if (!empty($params['checkout'])) {
            $query_params['checkout'] = sanitize_text_field($params['checkout']);
        }
        
        // Note: adults, children, and rooms parameters are NOT included
        // as they are not needed for the room list API endpoint
        
        if (!empty($query_params)) {
            $endpoint .= '?' . http_build_query($query_params);
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Book Your Stay API: Calling rooms endpoint: ' . $endpoint);
            error_log('Book Your Stay API: Hotel code: ' . $hotel_code);
            error_log('Book Your Stay API: Full URL will be: ' . $this->get_shop_api_base_url() . $endpoint);
        }
        
        $response = $this->make_api_request($endpoint, 'GET', null, false);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if ($response === false) {
                error_log('Book Your Stay API: Failed to get rooms from ' . $endpoint);
            } else {
                error_log('Book Your Stay API: Successfully received rooms data');
                error_log('Book Your Stay API: Response type: ' . gettype($response));
                if (is_array($response)) {
                    error_log('Book Your Stay API: Response keys: ' . print_r(array_keys($response), true));
                    if (isset($response['productDetailList'])) {
                        error_log('Book Your Stay API: Found productDetailList with ' . count($response['productDetailList']) . ' items');
                    }
                }
            }
        }
        
        return $response;
    }
    
    /**
     * Get room list with pricing
     * Uses the new Shop API /hotelDetails/:hotelCode/room endpoint
     */
    public function get_room_list($params = array()) {
        // Ensure we have hotel code
        if (empty($params['pcode'])) {
            $hotel_code = get_option('bys_hotel_code', '');
            if (!empty($hotel_code)) {
                $params['pcode'] = $hotel_code;
            }
        }
        
        if (empty($params['pcode'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Book Your Stay API: No hotel code provided');
            }
            return false;
        }
        
        // Log the hotel code being used
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Book Your Stay API: Fetching rooms for ' . $params['pcode']);
        }
        
        // Get rooms from new Shop API endpoint
        $rooms_response = $this->get_rooms($params);
        
        // Cache hotel descriptive info for image fetching (to avoid multiple API calls)
        $descriptive_info_cache = null;
        
        if (!$rooms_response) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Book Your Stay API: Failed to get rooms from API');
                error_log('Book Your Stay API: Hotel code used: ' . (isset($params['pcode']) ? $params['pcode'] : 'not set'));
                
                // Check OAuth status
                $oauth = BYS_OAuth::get_instance();
                $has_token = $oauth->is_token_valid();
                $token_info = $oauth->get_token_info();
                $oauth_error = get_option('bys_last_oauth_error', '');
                
                error_log('Book Your Stay API: OAuth token valid: ' . ($has_token ? 'Yes' : 'No'));
                if (!$has_token) {
                    error_log('Book Your Stay API: OAuth error: ' . ($oauth_error ?: 'No error message'));
                } else {
                    error_log('Book Your Stay API: Token expires in: ' . ($token_info['access_token_expires_in'] > 0 ? round($token_info['access_token_expires_in'] / 60) . ' minutes' : 'Expired'));
                }
            }
            return false;
        }
        
        // Parse the response and format rooms
        $rooms = array();
        
        // Extract room data from response - handle the new API structure
        $room_data = null;
        
        // Check for productDetailList (new API structure)
        if (isset($rooms_response['productDetailList']) && is_array($rooms_response['productDetailList'])) {
            $room_data = $rooms_response['productDetailList'];
        }
        // Fallback to other possible structures
        elseif (isset($rooms_response['data']) && is_array($rooms_response['data'])) {
            $room_data = $rooms_response['data'];
        } elseif (isset($rooms_response['rooms']) && is_array($rooms_response['rooms'])) {
            $room_data = $rooms_response['rooms'];
        } elseif (isset($rooms_response['roomTypes']) && is_array($rooms_response['roomTypes'])) {
            $room_data = $rooms_response['roomTypes'];
        } elseif (is_array($rooms_response) && isset($rooms_response[0])) {
            // Direct array of rooms
            $room_data = $rooms_response;
        } elseif (is_array($rooms_response)) {
            // Try to find rooms using recursive search
            $room_data = $this->find_rooms_in_response($rooms_response);
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if ($room_data) {
                $count = is_array($room_data) ? (isset($room_data[0]) ? count($room_data) : 1) : 1;
                error_log('Book Your Stay API: Found ' . $count . ' room(s) in response');
                error_log('Book Your Stay API: Response structure - ' . (isset($rooms_response['productDetailList']) ? 'productDetailList found' : 'productDetailList not found'));
            } else {
                error_log('Book Your Stay API: No rooms found. Top level keys: ' . print_r(array_keys($rooms_response), true));
            }
        }
        
        if ($room_data) {
            // Ensure it's an array
            if (!is_array($room_data) || (isset($room_data['@attributes']) && !isset($room_data[0]))) {
                $room_data = array($room_data);
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Book Your Stay API: Processing ' . count($room_data) . ' room(s)');
            }
            
            foreach ($room_data as $room_item) {
                if (empty($room_item) || !is_array($room_item)) {
                    continue;
                }
                
                // Filter for roomtype products only (new API structure)
                if (isset($room_item['productType']) && $room_item['productType'] !== 'roomtype') {
                    continue;
                }
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Book Your Stay API: Processing room. Keys: ' . print_r(array_keys($room_item), true));
                }
                
                // Extract room information from new API structure
                // New API structure: code, name, totalOccupancy, adultOccupancy, childOccupancy, bedType, roomCategory, roomAmenities
                $room_code = isset($room_item['code']) ? $room_item['code'] : '';
                $room_name = isset($room_item['name']) ? $room_item['name'] : '';
                $max_occupancy = isset($room_item['totalOccupancy']) ? intval($room_item['totalOccupancy']) : (isset($room_item['adultOccupancy']) ? intval($room_item['adultOccupancy']) : 2);
                $bed_type = isset($room_item['bedType']) ? $room_item['bedType'] : '';
                $room_category = isset($room_item['roomCategory']) ? $room_item['roomCategory'] : '';
                $smoking_pref = isset($room_item['smokingPref']) ? $room_item['smokingPref'] : '';
                
                // Build description from available fields
                $description_parts = array();
                if (!empty($bed_type)) {
                    $description_parts[] = $bed_type . ' bed';
                }
                if (!empty($room_category)) {
                    $description_parts[] = $room_category . ' room';
                }
                if (!empty($smoking_pref) && $smoking_pref !== 'Nonsmoking') {
                    $description_parts[] = $smoking_pref;
                }
                // Add occupancy info
                if (isset($room_item['adultOccupancy']) && isset($room_item['childOccupancy'])) {
                    $occupancy_text = $room_item['adultOccupancy'] . ' adult' . ($room_item['adultOccupancy'] > 1 ? 's' : '');
                    if ($room_item['childOccupancy'] > 0) {
                        $occupancy_text .= ', ' . $room_item['childOccupancy'] . ' child' . ($room_item['childOccupancy'] > 1 ? 'ren' : '');
                    }
                    $description_parts[] = $occupancy_text;
                }
                $description = !empty($description_parts) ? implode(' • ', $description_parts) : '';
                
                // Size and view not available in new API, leave empty
                $size = '';
                $view = '';
                
                // Try to get image URL from room item
                $image_url = '';
                
                // Check for images in the room response
                if (isset($room_item['images']) && is_array($room_item['images']) && !empty($room_item['images'])) {
                    // Get first image URL
                    $first_image = $room_item['images'][0];
                    if (is_array($first_image) && isset($first_image['url'])) {
                        $image_url = $first_image['url'];
                    } elseif (is_array($first_image) && isset($first_image['imageUrl'])) {
                        $image_url = $first_image['imageUrl'];
                    } elseif (is_string($first_image)) {
                        $image_url = $first_image;
                    }
                    
                    if (defined('WP_DEBUG') && WP_DEBUG && !empty($image_url)) {
                        error_log('Book Your Stay API: Found image in room response for ' . $room_code . ': ' . $image_url);
                    }
                } elseif (isset($room_item['imageUrl'])) {
                    $image_url = $room_item['imageUrl'];
                } elseif (isset($room_item['image'])) {
                    $image_url = is_array($room_item['image']) ? (isset($room_item['image']['url']) ? $room_item['image']['url'] : '') : $room_item['image'];
                } elseif (isset($room_item['imageURL'])) {
                    $image_url = $room_item['imageURL'];
                }
                
                // If no image in room response, try to get from hotel descriptive info (IDS API)
                // According to IDS Hotel Descriptive Info API documentation:
                // https://shrdev.atlassian.net/wiki/spaces/SPD/pages/2014380035/IDS+Hotel+Descriptive+Info
                if (empty($image_url)) {
                    // Use cached descriptive info if available, otherwise fetch it
                    if ($descriptive_info_cache === null) {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('Book Your Stay API: Fetching hotel descriptive info (IDS API) for room images');
                        }
                        $descriptive_info_cache = $this->get_hotel_descriptive_info($params);
                        
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            if ($descriptive_info_cache) {
                                error_log('Book Your Stay API: Hotel descriptive info retrieved. Top level keys: ' . print_r(array_keys($descriptive_info_cache), true));
                            } else {
                                error_log('Book Your Stay API: Failed to retrieve hotel descriptive info');
                            }
                        }
                    }
                    
                    if ($descriptive_info_cache) {
                        $image_url = $this->get_room_image_from_descriptive_info($room_code, $descriptive_info_cache);
                    }
                }
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    if (!empty($image_url)) {
                        error_log('Book Your Stay API: Image found for room ' . $room_code . ': ' . $image_url);
                    } else {
                        error_log('Book Your Stay API: No image found for room ' . $room_code . ' (checked room API and IDS descriptive info)');
                    }
                }
                
                // Price not in this response structure (would need separate pricing API call)
                $from_price = null;
                $currency = 'ZAR'; // Default currency
                
                // Handle amenities from roomAmenities array
                $amenities = array();
                if (isset($room_item['roomAmenities']) && is_array($room_item['roomAmenities'])) {
                    foreach ($room_item['roomAmenities'] as $amenity) {
                        if (is_array($amenity) && isset($amenity['amenityName'])) {
                            $amenities[] = $amenity['amenityName'];
                        } elseif (is_string($amenity)) {
                            $amenities[] = $amenity;
                        }
                    }
                }
                
                // Only add room if we have at least a name or code
                if (!empty($room_name) || !empty($room_code)) {
                    $formatted_room = array(
                        'code' => $room_code ?: 'UNKNOWN',
                        'name' => !empty($room_name) ? $room_name : ($room_code ?: 'Room'),
                        'description' => $description,
                        'size' => $size,
                        'view' => $view,
                        'max_occupancy' => $max_occupancy,
                        'amenities' => $amenities,
                        'image' => $image_url,
                        'from_price' => $from_price,
                        'currency' => $currency
                    );
                    
                    $rooms[] = $formatted_room;
                    
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('Book Your Stay API: Added room - ' . $formatted_room['name'] . ' (Code: ' . $formatted_room['code'] . ', Occupancy: ' . $formatted_room['max_occupancy'] . ', Amenities: ' . count($formatted_room['amenities']) . ')');
                    }
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('Book Your Stay API: Skipping room item - no name or code found');
                    }
                }
            }
        } else {
            // If no room types found, try to create fallback rooms from available data
            // This helps when API structure is different or API fails but we know rooms exist
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Book Your Stay API: No room types found in response. Structure keys: ' . print_r(array_keys($rooms_response), true));
                error_log('Book Your Stay API: Full response: ' . print_r($rooms_response, true));
            }
            
            // Fallback: Create basic room entries if we have hotel code but API structure is unexpected
            // This allows the shortcode to still work and generate booking links
            if (!empty($params['pcode'])) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Book Your Stay API: Using fallback room generation');
                }
                
                // Create a generic room entry so users can still book
                // The booking engine will show actual available rooms
                $rooms[] = array(
                    'code' => 'DEFAULT',
                    'name' => 'Available Rooms',
                    'description' => 'Click Book Now to view all available rooms and rates for your selected dates.',
                    'size' => '',
                    'view' => '',
                    'max_occupancy' => 2,
                    'amenities' => array(),
                    'image' => '',
                    'from_price' => null,
                    'currency' => 'ZAR'
                );
            }
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Book Your Stay API: Found ' . count($rooms) . ' rooms');
            if (empty($rooms)) {
                error_log('Book Your Stay API: Rooms array is empty. Response was: ' . print_r($rooms_response, true));
            }
        }
        
        return $rooms;
    }
    
    /**
     * Find rooms in API response using flexible recursive search
     */
    private function find_rooms_in_response($data, $depth = 0, $max_depth = 5) {
        if ($depth >= $max_depth || !is_array($data)) {
            return null;
        }
        
        // Check common structures first (including OTA structure from IDS API)
        // Based on actual IDS Hotel Descriptive Info API response structure
        // OTA structure: HotelDescriptiveContents -> HotelDescriptiveContent -> FacilityInfo -> GuestRooms -> GuestRoom
        $structures = array(
            // Actual OTA IDS API structure (from XML response)
            'HotelDescriptiveContents.HotelDescriptiveContent.FacilityInfo.GuestRooms.GuestRoom',
            'OTA_HotelDescriptiveInfoRS.HotelDescriptiveContents.HotelDescriptiveContent.FacilityInfo.GuestRooms.GuestRoom',
            'HotelDescriptiveContent.FacilityInfo.GuestRooms.GuestRoom',
            'FacilityInfo.GuestRooms.GuestRoom',
            'GuestRooms.GuestRoom',
            // Alternative structures (fallback)
            'HotelDescriptiveContents.HotelDescriptiveContent.RoomTypes.RoomType',
            'OTA_HotelDescriptiveInfoRS.HotelDescriptiveContents.HotelDescriptiveContent.RoomTypes.RoomType',
            'HotelDescriptiveInfoRS.HotelDescriptiveInfo.RoomTypes.RoomType',
            'HotelDescriptiveInfoRS.RoomTypes.RoomType',
            'HotelDescriptiveInfo.RoomTypes.RoomType',
            'RoomTypes.RoomType',
            'roomTypes',
            'rooms',
            'RoomType',
            'GuestRoom',
            'data'
        );
        
        foreach ($structures as $path) {
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
            
            if ($found && !empty($current)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Book Your Stay API: Found rooms in path: ' . $path);
                }
                return $current;
            }
        }
        
        // Recursive search for room-related keys
        foreach ($data as $key => $value) {
            if (!is_array($value)) {
                continue;
            }
            
            $key_lower = strtolower($key);
            
            // Look for room-related keys
            if (strpos($key_lower, 'roomtype') !== false || 
                strpos($key_lower, 'room_type') !== false ||
                (strpos($key_lower, 'room') !== false && is_array($value) && count($value) > 0)) {
                
                // Check if this looks like room data
                $first_item = is_array($value) && isset($value[0]) ? $value[0] : $value;
                if (is_array($first_item)) {
                    // Check for room-like properties
                    $has_room_properties = false;
                    foreach (array('name', 'code', 'description', 'roomtypecode', 'roomtype', 'title') as $prop) {
                        if (isset($first_item[$prop]) || isset($first_item['@attributes'][$prop])) {
                            $has_room_properties = true;
                            break;
                        }
                    }
                    
                    if ($has_room_properties) {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('Book Your Stay API: Found rooms in key: ' . $key);
                        }
                        return $value;
                    }
                }
            }
            
            // Recursively search deeper
            $found = $this->find_rooms_in_response($value, $depth + 1, $max_depth);
            if ($found !== null) {
                return $found;
            }
        }
        
        return null;
    }
    
    /**
     * Recursively search for room data in API response (for debugging)
     */
    private function search_for_rooms_recursive($data, $depth = 0, $max_depth = 3) {
        if ($depth >= $max_depth || !is_array($data)) {
            return;
        }
        
        foreach ($data as $key => $value) {
            $key_lower = strtolower($key);
            // Look for room-related keys
            if (strpos($key_lower, 'room') !== false || strpos($key_lower, 'rate') !== false) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Book Your Stay API: Found potential room key "' . $key . '" at depth ' . $depth . ' (type: ' . gettype($value) . ', is_array: ' . (is_array($value) ? 'yes' : 'no') . ')');
                    if (is_array($value) && count($value) > 0) {
                        $sample = is_array($value) && isset($value[0]) ? $value[0] : $value;
                        if (is_array($sample)) {
                            error_log('Book Your Stay API: Sample keys: ' . print_r(array_keys($sample), true));
                        }
                    }
                }
            }
            
            if (is_array($value)) {
                $this->search_for_rooms_recursive($value, $depth + 1, $max_depth);
            }
        }
    }
    
    /**
     * Get cached room list (with transient cache)
     */
    public function get_cached_room_list($params = array(), $cache_duration = 3600) {
        // Ensure we have hotel code or property ID in params
        if (empty($params['pcode']) && empty($params['propertyID'])) {
            $hotel_code = get_option('bys_hotel_code', '');
            $property_id = get_option('bys_property_id', '');
            
            if (!empty($hotel_code)) {
                $params['pcode'] = $hotel_code;
            }
            if (!empty($property_id)) {
                $params['propertyID'] = intval($property_id);
            }
        }
        
        // Create cache key based on parameters (including hotel code/property ID)
        $cache_key = 'bys_room_list_' . md5(serialize($params));
        
        // Check if cache should be bypassed (for debugging)
        $bypass_cache = isset($_GET['bys_refresh_rooms']) && current_user_can('manage_options');
        
        if (!$bypass_cache) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Book Your Stay API: Using cached room list for ' . $cache_key);
                }
                return $cached;
            }
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Book Your Stay API: Fetching fresh room list (cache bypassed: ' . ($bypass_cache ? 'yes' : 'no') . ')');
        }
        
        $rooms = $this->get_room_list($params);
        
        if ($rooms !== false && !empty($rooms)) {
            set_transient($cache_key, $rooms, $cache_duration);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Book Your Stay API: Cached ' . count($rooms) . ' rooms for ' . $cache_duration . ' seconds');
            }
        }
        
        return $rooms;
    }
    
    /**
     * Clear room list cache
     */
    public function clear_room_cache($params = null) {
        if ($params === null) {
            // Clear all room caches
            global $wpdb;
            $wpdb->query(
                "DELETE FROM {$wpdb->options} 
                WHERE option_name LIKE '_transient_bys_room_list_%' 
                OR option_name LIKE '_transient_timeout_bys_room_list_%'"
            );
        } else {
            // Clear specific cache
            $cache_key = 'bys_room_list_' . md5(serialize($params));
            delete_transient($cache_key);
        }
    }
    
    /**
     * Get room image from hotel descriptive info (OTA format)
     * Based on IDS Hotel Descriptive Info API documentation
     * Uses cached descriptive info to avoid multiple API calls
     * 
     * According to OTA standards, images are in MediaItems/MediaItem structure
     * Reference: https://shrdev.atlassian.net/wiki/spaces/SPD/pages/2014380035/IDS+Hotel+Descriptive+Info
     */
    private function get_room_image_from_descriptive_info($room_code, $descriptive_info) {
        if (empty($room_code) || !$descriptive_info || !is_array($descriptive_info)) {
            return '';
        }
        
        // Search for room images in descriptive info
        // Actual OTA structure: HotelDescriptiveContents -> HotelDescriptiveContent -> FacilityInfo -> GuestRooms -> GuestRoom
        $room_types = $this->find_rooms_in_response($descriptive_info);
        
        if ($room_types) {
            // Ensure it's an array
            if (!is_array($room_types) || (isset($room_types['@attributes']) && !isset($room_types[0]))) {
                $room_types = array($room_types);
            }
            
            foreach ($room_types as $room_type) {
                if (!is_array($room_type)) {
                    continue;
                }
                
                // Check if this is the room we're looking for
                // Actual OTA structure: GuestRoom -> TypeRoom -> @attributes.RoomTypeCode
                // Example: <TypeRoom RoomTypeCode="STUD1" ... />
                $rt_code = $this->get_nested_value($room_type, array(
                    'TypeRoom.@attributes.RoomTypeCode',  // Primary path for actual API
                    'TypeRoom.RoomTypeCode',
                    'RoomTypeCode', 
                    '@attributes.RoomTypeCode',
                    'code', 
                    'roomCode', 
                    'roomTypeCode',
                    '@attributes.code'
                ));
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Book Your Stay API: Comparing room codes - Looking for: ' . $room_code . ', Found: ' . $rt_code);
                }
                
                if ($rt_code === $room_code) {
                    // Found the room, extract image from OTA MultimediaDescriptions structure
                    // Actual OTA format: GuestRoom -> MultimediaDescriptions -> MultimediaDescription -> ImageItems -> ImageItem -> ImageFormat -> URL
                    $image_url = $this->extract_ota_room_image($room_type);
                    
                    if (!empty($image_url)) {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('Book Your Stay API: Found OTA image for room ' . $room_code . ': ' . $image_url);
                        }
                        return $image_url;
                    } else {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('Book Your Stay API: Room ' . $room_code . ' matched but no image found. Room keys: ' . print_r(array_keys($room_type), true));
                        }
                    }
                }
            }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Book Your Stay API: No room types found in descriptive info. Top level keys: ' . print_r(array_keys($descriptive_info), true));
            }
        }
        
        return '';
    }
    
    /**
     * Extract room image from OTA GuestRoom structure
     * Handles actual IDS API structure: MultimediaDescriptions -> ImageItems -> ImageItem -> ImageFormat -> URL
     * Based on actual XML response from IDS Hotel Descriptive Info API
     */
    private function extract_ota_room_image($room_type) {
        if (!is_array($room_type)) {
            return '';
        }
        
        // Actual OTA structure from IDS API:
        // GuestRoom -> MultimediaDescriptions -> MultimediaDescription -> ImageItems -> ImageItem -> ImageFormat -> URL
        $image_paths = array(
            // Actual IDS API structure (primary)
            'MultimediaDescriptions.MultimediaDescription.ImageItems.ImageItem.0.ImageFormat.URL',
            'MultimediaDescriptions.MultimediaDescription.ImageItems.ImageItem.ImageFormat.URL',
            'MultimediaDescriptions.MultimediaDescription.ImageItems.ImageItem.0.ImageFormat.url',
            'MultimediaDescriptions.MultimediaDescription.ImageItems.ImageItem.ImageFormat.url',
            // Alternative paths
            'MultimediaDescriptions.ImageItems.ImageItem.0.ImageFormat.URL',
            'MultimediaDescriptions.ImageItems.ImageItem.ImageFormat.URL',
            'ImageItems.ImageItem.0.ImageFormat.URL',
            'ImageItems.ImageItem.ImageFormat.URL',
            // Fallback to MediaItems structure (if used)
            'MediaItems.MediaItem.0.URL',
            'MediaItems.MediaItem.0.@attributes.URL',
            'MediaItems.MediaItem.URL',
            'MediaItems.MediaItem.@attributes.URL',
            'MediaItems.MediaItem.0.url',
            'MediaItems.MediaItem.0.@attributes.url',
            // Images structure (alternative)
            'Images.Image.0.URL',
            'Images.Image.0.@attributes.URL',
            'Images.Image.URL',
            'Images.Image.@attributes.URL',
            // Direct image fields
            'image', 'Image', 'imageUrl', 'imageURL'
        );
        
        // Try each path
        foreach ($image_paths as $path) {
            $image_url = $this->get_nested_value($room_type, array($path));
            if (!empty($image_url)) {
                return $image_url;
            }
        }
        
        // Manual extraction for actual IDS API structure
        // MultimediaDescriptions -> MultimediaDescription -> ImageItems -> ImageItem -> ImageFormat -> URL
        if (isset($room_type['MultimediaDescriptions']['MultimediaDescription'])) {
            $multimedia_desc = $room_type['MultimediaDescriptions']['MultimediaDescription'];
            
            // Handle array or single MultimediaDescription
            if (!is_array($multimedia_desc) || (isset($multimedia_desc['@attributes']) && !isset($multimedia_desc[0]))) {
                $multimedia_desc = array($multimedia_desc);
            }
            
            foreach ($multimedia_desc as $desc) {
                if (!is_array($desc) || !isset($desc['ImageItems']['ImageItem'])) {
                    continue;
                }
                
                $image_items = $desc['ImageItems']['ImageItem'];
                
                // Ensure it's an array
                if (!is_array($image_items) || (isset($image_items['@attributes']) && !isset($image_items[0]))) {
                    $image_items = array($image_items);
                }
                
                // Get first image from ImageItems
                foreach ($image_items as $image_item) {
                    if (!is_array($image_item)) {
                        continue;
                    }
                    
                    // Check for ImageFormat -> URL structure
                    if (isset($image_item['ImageFormat']['URL'])) {
                        return $image_item['ImageFormat']['URL'];
                    } elseif (isset($image_item['ImageFormat']['url'])) {
                        return $image_item['ImageFormat']['url'];
                    } elseif (isset($image_item['ImageFormat'][0]['URL'])) {
                        return $image_item['ImageFormat'][0]['URL'];
                    } elseif (isset($image_item['ImageFormat'][0]['url'])) {
                        return $image_item['ImageFormat'][0]['url'];
                    }
                }
            }
        }
        
        // Fallback: Try MediaItems structure (if used)
        if (isset($room_type['MediaItems']['MediaItem'])) {
            $media_items = $room_type['MediaItems']['MediaItem'];
            
            if (!is_array($media_items) || (isset($media_items['@attributes']) && !isset($media_items[0]))) {
                $media_items = array($media_items);
            }
            
            foreach ($media_items as $media_item) {
                if (!is_array($media_item)) {
                    continue;
                }
                
                if (isset($media_item['URL'])) {
                    return $media_item['URL'];
                } elseif (isset($media_item['url'])) {
                    return $media_item['url'];
                } elseif (isset($media_item['@attributes']['URL'])) {
                    return $media_item['@attributes']['URL'];
                }
            }
        }
        
        // Fallback: Try Images structure
        if (isset($room_type['Images']['Image'])) {
            $images = $room_type['Images']['Image'];
            if (!is_array($images) || (isset($images['@attributes']) && !isset($images[0]))) {
                $images = array($images);
            }
            
            foreach ($images as $image) {
                if (!is_array($image)) {
                    continue;
                }
                
                if (isset($image['URL'])) {
                    return $image['URL'];
                } elseif (isset($image['url'])) {
                    return $image['url'];
                } elseif (isset($image['@attributes']['URL'])) {
                    return $image['@attributes']['URL'];
                }
            }
        }
        
        return '';
    }
    
    /**
     * Helper method to get nested value from array using multiple possible keys
     * Supports dot notation for nested keys (e.g., 'Images.Image.0.URL')
     */
    private function get_nested_value($data, $keys, $default = null) {
        if (!is_array($data) || empty($keys)) {
            return $default;
        }
        
        foreach ($keys as $key) {
            // Handle dot notation for nested keys
            if (strpos($key, '.') !== false) {
                $parts = explode('.', $key);
                $value = $data;
                $found = true;
                
                foreach ($parts as $part) {
                    if (is_numeric($part)) {
                        $part = intval($part);
                    }
                    
                    if (is_array($value) && (isset($value[$part]) || (is_numeric($part) && isset($value[intval($part)])))) {
                        $value = $value[$part];
                    } else {
                        $found = false;
                        break;
                    }
                }
                
                if ($found && $value !== null && $value !== '') {
                    return $value;
                }
            } else {
                // Simple key lookup
                if (isset($data[$key]) && $data[$key] !== null && $data[$key] !== '') {
                    return $data[$key];
                }
            }
        }
        
        return $default;
    }
}

