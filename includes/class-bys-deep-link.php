<?php
/**
 * Deep Link Generator for SHR CRS Booking Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

class BYS_Deep_Link {
    
    private static $instance = null;
    
    // Base URLs for different booking engine pages
    private $base_urls = array(
        'index' => 'https://res.windsurfercrs.com/ibe/index.aspx',
        'details' => 'https://res.windsurfercrs.com/ibe/details.aspx',
        'shop' => 'https://res.windsurfercrs.com/ibe/shop.aspx',
        'default' => 'https://res.windsurfercrs.com/ibe/default.aspx',
        'confirm' => 'https://res.windsurfercrs.com/ibe/confirm.aspx'
    );
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Generate deep link URL for booking
     */
    public function generate_booking_link($params = array()) {
        // Default to index page
        $page = isset($params['page']) ? $params['page'] : 'index';
        
        if (!isset($this->base_urls[$page])) {
            $page = 'index';
        }
        
        $url = $this->base_urls[$page];
        $query_params = array();
        
        // Property identification - use settings if not provided
        if (!empty($params['propertyID'])) {
            $query_params['propertyID'] = intval($params['propertyID']);
        } elseif (!empty($params['hotelID'])) {
            $query_params['hotelID'] = intval($params['hotelID']);
        } elseif (!empty($params['pcode'])) {
            $query_params['pcode'] = sanitize_text_field($params['pcode']);
        } else {
            // Use settings
            $property_id = get_option('bys_property_id', '');
            $hotel_code = get_option('bys_hotel_code', '');
            
            if (!empty($property_id)) {
                $query_params['propertyID'] = intval($property_id);
            } elseif (!empty($hotel_code)) {
                $query_params['pcode'] = sanitize_text_field($hotel_code);
            }
        }
        
        // Calendar variables - convert YYYY-MM-DD to MM/DD/YYYY format for booking engine
        if (!empty($params['checkin'])) {
            $checkin = sanitize_text_field($params['checkin']);
            $query_params['checkin'] = $this->convert_date_format($checkin);
        }
        if (!empty($params['checkout'])) {
            $checkout = sanitize_text_field($params['checkout']);
            $query_params['checkout'] = $this->convert_date_format($checkout);
        }
        if (!empty($params['nights'])) {
            $query_params['nights'] = intval($params['nights']);
        }
        
        // Occupancy variables
        if (!empty($params['adults'])) {
            $query_params['adults'] = intval($params['adults']);
        }
        if (!empty($params['children'])) {
            $query_params['children'] = intval($params['children']);
        }
        if (!empty($params['childAges'])) {
            $query_params['childAges'] = sanitize_text_field($params['childAges']);
        }
        if (!empty($params['rooms'])) {
            $query_params['rooms'] = intval($params['rooms']);
        }
        
        // Access codes
        if (!empty($params['Promo'])) {
            $query_params['Promo'] = sanitize_text_field($params['Promo']);
        }
        if (!empty($params['Group'])) {
            $query_params['Group'] = sanitize_text_field($params['Group']);
        }
        if (!empty($params['Corp'])) {
            $query_params['Corp'] = sanitize_text_field($params['Corp']);
        }
        if (!empty($params['Access'])) {
            $query_params['Access'] = sanitize_text_field($params['Access']);
        }
        
        // Language and Currency
        if (!empty($params['langID'])) {
            $query_params['langID'] = intval($params['langID']);
        }
        if (!empty($params['currID'])) {
            $query_params['currID'] = intval($params['currID']);
        }
        
        // Build final URL
        if (!empty($query_params)) {
            $url .= '?' . http_build_query($query_params);
        }
        
        return $url;
    }
    
    /**
     * Convert date format from YYYY-MM-DD to MM/DD/YYYY for booking engine
     */
    private function convert_date_format($date) {
        // If already in MM/DD/YYYY format, return as is
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
            return $date;
        }
        
        // If in YYYY-MM-DD format, convert to MM/DD/YYYY
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date_parts = explode('-', $date);
            if (count($date_parts) === 3) {
                return $date_parts[1] . '/' . $date_parts[2] . '/' . $date_parts[0];
            }
        }
        
        // Try to parse other formats
        $timestamp = strtotime($date);
        if ($timestamp !== false) {
            return date('m/d/Y', $timestamp);
        }
        
        // If all else fails, return original
        return $date;
    }
}

