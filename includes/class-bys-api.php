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
            }
            return false;
        }
        
        $base_url = $is_ids ? $this->get_ids_base_url() : $this->get_api_base_url();
        $url = $base_url . $endpoint;
        
        // Determine Accept header based on endpoint
        // Rate Calendar API returns XML, IDS API may return XML or JSON
        $is_rate_calendar = (strpos($endpoint, 'ratecalendar') !== false);
        $accept_header = 'application/json';
        if ($is_ids || $is_rate_calendar) {
            $accept_header = 'application/xml, application/json, text/xml';
        }
        
        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
                'Accept' => $accept_header
            ),
            'timeout' => 30,
            'sslverify' => true
        );
        
        if ($body !== null) {
            $args['body'] = is_array($body) ? json_encode($body) : $body;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Book Your Stay API: Making request to ' . $url);
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
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Book Your Stay API Error: HTTP ' . $response_code . ' - ' . $response_body);
            }
            return false;
        }
        
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
     * Get room list with pricing
     * Combines HotelDescriptiveInfo and Rate Calendar data
     */
    public function get_room_list($params = array()) {
        // Ensure we have hotel code or property ID
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
        
        if (empty($params['pcode']) && empty($params['propertyID'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Book Your Stay API: No hotel code or property ID provided');
            }
            return false;
        }
        
        // Log the hotel code being used
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_code = !empty($params['pcode']) ? $params['pcode'] : 'Property ID: ' . $params['propertyID'];
            error_log('Book Your Stay API: Fetching rooms for ' . $log_code);
        }
        
        // Get room types from HotelDescriptiveInfo
        $descriptive_info = $this->get_hotel_descriptive_info($params);
        
        if (!$descriptive_info) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Book Your Stay API: Failed to get hotel descriptive info');
            }
            return false;
        }
        
        // Get pricing from Shop API Rate Calendar
        $rate_calendar = $this->get_rate_calendar($params);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Book Your Stay API: Descriptive info received');
            if ($rate_calendar) {
                error_log('Book Your Stay API: Rate calendar received');
            } else {
                error_log('Book Your Stay API: Rate calendar not available');
            }
        }
        
        // Combine data
        $rooms = array();
        
        // Parse descriptive info to extract room types
        // Use recursive search to find room data in any structure
        $room_types = $this->find_rooms_in_response($descriptive_info);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if ($room_types) {
                error_log('Book Your Stay API: Found ' . (is_array($room_types) ? count($room_types) : 1) . ' room type(s)');
            } else {
                error_log('Book Your Stay API: No room types found. Top level keys: ' . print_r(array_keys($descriptive_info), true));
                // Try to find any nested arrays that might contain rooms
                $this->search_for_rooms_recursive($descriptive_info, 0, 3);
            }
        }
        
        if ($room_types) {
            // Ensure it's an array
            if (!is_array($room_types) || (isset($room_types['@attributes']) && !isset($room_types[0]))) {
                $room_types = array($room_types);
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Book Your Stay API: Processing ' . count($room_types) . ' room type(s)');
            }
            
            foreach ($room_types as $room_type) {
                if (empty($room_type) || !is_array($room_type)) {
                    continue;
                }
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Book Your Stay API: Processing room type. Keys: ' . print_r(array_keys($room_type), true));
                }
                $room_code = isset($room_type['@attributes']['RoomTypeCode']) 
                    ? $room_type['@attributes']['RoomTypeCode'] 
                    : (isset($room_type['RoomTypeCode']) ? $room_type['RoomTypeCode'] : '');
                
                // Get room name - try multiple possible fields
                $room_name = '';
                if (isset($room_type['RoomDescription']['Text'])) {
                    $room_name = $room_type['RoomDescription']['Text'];
                } elseif (isset($room_type['Name'])) {
                    $room_name = $room_type['Name'];
                } elseif (isset($room_type['@attributes']['Name'])) {
                    $room_name = $room_type['@attributes']['Name'];
                } elseif (isset($room_type['Title'])) {
                    $room_name = $room_type['Title'];
                } elseif (isset($room_type['roomName'])) {
                    $room_name = $room_type['roomName'];
                } elseif (isset($room_type['Description']['Text'])) {
                    $room_name = $room_type['Description']['Text'];
                } elseif (isset($room_type['Text'])) {
                    $room_name = $room_type['Text'];
                }
                
                // Get amenities
                $amenities = array();
                if (isset($room_type['Amenities']['Amenity'])) {
                    $amenity_list = $room_type['Amenities']['Amenity'];
                    if (!is_array($amenity_list)) {
                        $amenity_list = array($amenity_list);
                    }
                    foreach ($amenity_list as $amenity) {
                        $amenity_name = isset($amenity['@attributes']['Code']) 
                            ? $amenity['@attributes']['Code'] 
                            : (isset($amenity['Text']) ? $amenity['Text'] : '');
                        if (!empty($amenity_name)) {
                            $amenities[] = $amenity_name;
                        }
                    }
                }
                
                // Get description
                $description = '';
                if (isset($room_type['RoomDescription']['Text'])) {
                    $description = $room_type['RoomDescription']['Text'];
                } elseif (isset($room_type['Description'])) {
                    $description = is_array($room_type['Description']) 
                        ? (isset($room_type['Description']['Text']) ? $room_type['Description']['Text'] : '')
                        : $room_type['Description'];
                }
                
                // Get room size, view, occupancy - try multiple possible fields
                $size = '';
                if (isset($room_type['@attributes']['Size'])) {
                    $size = $room_type['@attributes']['Size'];
                } elseif (isset($room_type['Size'])) {
                    $size = $room_type['Size'];
                } elseif (isset($room_type['Area'])) {
                    $size = $room_type['Area'];
                } elseif (isset($room_type['SquareMeters'])) {
                    $size = $room_type['SquareMeters'] . 'm²';
                }
                
                $view = '';
                if (isset($room_type['View'])) {
                    $view = $room_type['View'];
                } elseif (isset($room_type['RoomView'])) {
                    $view = $room_type['RoomView'];
                } elseif (isset($room_type['@attributes']['View'])) {
                    $view = $room_type['@attributes']['View'];
                }
                
                $max_occupancy = 2;
                if (isset($room_type['@attributes']['MaxOccupancy'])) {
                    $max_occupancy = intval($room_type['@attributes']['MaxOccupancy']);
                } elseif (isset($room_type['MaxOccupancy'])) {
                    $max_occupancy = intval($room_type['MaxOccupancy']);
                } elseif (isset($room_type['Occupancy'])) {
                    $max_occupancy = intval($room_type['Occupancy']);
                } elseif (isset($room_type['MaxGuests'])) {
                    $max_occupancy = intval($room_type['MaxGuests']);
                }
                
                // Get image
                $image_url = '';
                if (isset($room_type['Images']['Image'][0]['URL'])) {
                    $image_url = $room_type['Images']['Image'][0]['URL'];
                } elseif (isset($room_type['Image'])) {
                    $image_url = is_array($room_type['Image']) 
                        ? (isset($room_type['Image']['URL']) ? $room_type['Image']['URL'] : '')
                        : $room_type['Image'];
                }
                
                // Get pricing from Shop API Rate Calendar - merge with room data
                $from_price = null;
                $currency = 'ZAR'; // Default, adjust based on API response
                
                // Parse rate calendar data - Shop API returns XML with RateCalendar structure
                $rates = null;
                if ($rate_calendar) {
                    // Structure 1: XML RateCalendar format (most common for Shop API)
                    if (isset($rate_calendar['RateCalendar'])) {
                        $rate_cal = $rate_calendar['RateCalendar'];
                        
                        // Try different XML structures
                        if (isset($rate_cal['Rates']['Rate'])) {
                            $rates = $rate_cal['Rates']['Rate'];
                        } elseif (isset($rate_cal['RoomTypes']['RoomType'])) {
                            // Room types with rates nested
                            $room_types = $rate_cal['RoomTypes']['RoomType'];
                            if (!is_array($room_types) || (isset($room_types['@attributes']) && !isset($room_types[0]))) {
                                $room_types = array($room_types);
                            }
                            $rates = array();
                            foreach ($room_types as $rt) {
                                if (isset($rt['Rates']['Rate'])) {
                                    $rt_rates = $rt['Rates']['Rate'];
                                    if (!is_array($rt_rates)) {
                                        $rt_rates = array($rt_rates);
                                    }
                                    foreach ($rt_rates as $rate) {
                                        $rate['roomTypeCode'] = isset($rt['@attributes']['Code']) 
                                            ? $rt['@attributes']['Code'] 
                                            : (isset($rt['RoomTypeCode']) ? $rt['RoomTypeCode'] : '');
                                        $rates[] = $rate;
                                    }
                                }
                            }
                        } elseif (isset($rate_cal['Rate'])) {
                            $rates = $rate_cal['Rate'];
                        }
                        
                        // Ensure rates is an array
                        if ($rates && !is_array($rates)) {
                            $rates = array($rates);
                        } elseif ($rates && isset($rates['@attributes']) && !isset($rates[0])) {
                            $rates = array($rates);
                        }
                    }
                    // Structure 2: Direct rates array (JSON format)
                    elseif (isset($rate_calendar['rates']) && is_array($rate_calendar['rates'])) {
                        $rates = $rate_calendar['rates'];
                    }
                    // Structure 3: Direct Rate array
                    elseif (isset($rate_calendar['Rate']) && is_array($rate_calendar['Rate'])) {
                        $rates = $rate_calendar['Rate'];
                    }
                    // Structure 4: Data array
                    elseif (isset($rate_calendar['data']) && is_array($rate_calendar['data'])) {
                        $rates = $rate_calendar['data'];
                    }
                }
                
                if ($rates && is_array($rates)) {
                    // Find lowest price for this room type
                    // Rate Calendar API returns daily rates, we need the minimum
                    foreach ($rates as $rate) {
                        // Get room type code from rate
                        $rate_room_code = '';
                        if (isset($rate['roomTypeCode'])) {
                            $rate_room_code = $rate['roomTypeCode'];
                        } elseif (isset($rate['@attributes']['RoomTypeCode'])) {
                            $rate_room_code = $rate['@attributes']['RoomTypeCode'];
                        } elseif (isset($rate['RoomTypeCode'])) {
                            $rate_room_code = $rate['RoomTypeCode'];
                        } elseif (isset($rate['@attributes']['Code'])) {
                            $rate_room_code = $rate['@attributes']['Code'];
                        }
                        
                        // Match by room code
                        if (!empty($rate_room_code) && $rate_room_code === $room_code) {
                            // Get price - Rate Calendar may have daily rates, get the minimum
                            $rate_price = null;
                            
                            // Check for daily rates array
                            if (isset($rate['DailyRates']['DailyRate'])) {
                                $daily_rates = $rate['DailyRates']['DailyRate'];
                                if (!is_array($daily_rates)) {
                                    $daily_rates = array($daily_rates);
                                }
                                foreach ($daily_rates as $daily_rate) {
                                    $day_price = null;
                                    if (isset($daily_rate['Price'])) {
                                        $day_price = floatval($daily_rate['Price']);
                                    } elseif (isset($daily_rate['@attributes']['Price'])) {
                                        $day_price = floatval($daily_rate['@attributes']['Price']);
                                    }
                                    if ($day_price !== null) {
                                        if ($rate_price === null || $day_price < $rate_price) {
                                            $rate_price = $day_price;
                                        }
                                    }
                                }
                            }
                            // Direct price field
                            elseif (isset($rate['price'])) {
                                $rate_price = floatval($rate['price']);
                            } elseif (isset($rate['Price'])) {
                                $rate_price = floatval($rate['Price']);
                            } elseif (isset($rate['@attributes']['Price'])) {
                                $rate_price = floatval($rate['@attributes']['Price']);
                            } elseif (isset($rate['MinPrice'])) {
                                $rate_price = floatval($rate['MinPrice']);
                            }
                            
                            if ($rate_price !== null) {
                                if ($from_price === null || $rate_price < $from_price) {
                                    $from_price = $rate_price;
                                    // Get currency
                                    if (isset($rate['currency'])) {
                                        $currency = $rate['currency'];
                                    } elseif (isset($rate['Currency'])) {
                                        $currency = $rate['Currency'];
                                    } elseif (isset($rate['@attributes']['Currency'])) {
                                        $currency = $rate['@attributes']['Currency'];
                                    }
                                }
                            }
                        }
                    }
                }
                
                if (defined('WP_DEBUG') && WP_DEBUG && $from_price !== null) {
                    error_log('Book Your Stay API: Found price ' . $from_price . ' ' . $currency . ' for room ' . $room_code);
                }
                
                // Only add room if we have at least a name or code
                if (!empty($room_name) || !empty($room_code)) {
                    $rooms[] = array(
                        'code' => $room_code,
                        'name' => !empty($room_name) ? $room_name : $room_code,
                        'description' => $description,
                        'size' => $size,
                        'view' => $view,
                        'max_occupancy' => $max_occupancy,
                        'amenities' => $amenities,
                        'image' => $image_url,
                        'from_price' => $from_price,
                        'currency' => $currency
                    );
                }
            }
        } else {
            // If no room types found, try to create fallback rooms from available data
            // This helps when API structure is different or API fails but we know rooms exist
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Book Your Stay API: No room types found in response. Structure keys: ' . print_r(array_keys($descriptive_info), true));
                error_log('Book Your Stay API: Full response: ' . print_r($descriptive_info, true));
            }
            
            // Fallback: Create basic room entries if we have hotel code but API structure is unexpected
            // This allows the shortcode to still work and generate booking links
            if (!empty($params['pcode']) || !empty($params['propertyID'])) {
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
                error_log('Book Your Stay API: Rooms array is empty. Response was: ' . print_r($descriptive_info, true));
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
        
        // Check common structures first
        $structures = array(
            'HotelDescriptiveInfoRS.HotelDescriptiveInfo.RoomTypes.RoomType',
            'HotelDescriptiveInfoRS.RoomTypes.RoomType',
            'HotelDescriptiveInfo.RoomTypes.RoomType',
            'RoomTypes.RoomType',
            'roomTypes',
            'rooms',
            'RoomType',
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
}

