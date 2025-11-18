<?php
/**
 * OAuth 2.0 Token Management for SHR API
 */

if (!defined('ABSPATH')) {
    exit;
}

class BYS_OAuth {
    
    private static $instance = null;
    private $token_cache_key = 'bys_shr_access_token';
    private $token_expiry_key = 'bys_shr_token_expiry';
    private $refresh_token_key = 'bys_shr_refresh_token';
    private $refresh_token_expiry_key = 'bys_shr_refresh_token_expiry';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get valid access token (fetches new one if expired, uses refresh token if available)
     */
    public function get_access_token() {
        $cached_token = get_transient($this->token_cache_key);
        $token_expiry = get_option($this->token_expiry_key, 0);
        
        // Check if token exists and is still valid (with 5 minute buffer)
        if ($cached_token && $token_expiry > (time() + 300)) {
            return $cached_token;
        }
        
        // Try to refresh token if available
        $refresh_token = get_option($this->refresh_token_key, '');
        $refresh_expiry = get_option($this->refresh_token_expiry_key, 0);
        
        if (!empty($refresh_token) && $refresh_expiry > time()) {
            $refreshed_token = $this->refresh_access_token($refresh_token);
            if ($refreshed_token !== false) {
                return $refreshed_token;
            }
        }
        
        // Token expired or doesn't exist, fetch new one
        return $this->fetch_new_token();
    }
    
    /**
     * Fetch new access token from SHR OAuth server
     */
    private function fetch_new_token() {
        $environment = get_option('bys_environment', 'uat');
        $client_id = get_option('bys_client_id', '');
        $client_secret = get_option('bys_client_secret', '');
        
        if (empty($client_id) || empty($client_secret)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Book Your Stay: SHR API credentials not configured');
            }
            return false;
        }
        
        // Determine token endpoint based on environment
        $token_endpoint = ($environment === 'production') 
            ? 'https://id.shrglobal.com/connect/token'
            : 'https://iduat.shrglobal.com/connect/token';
        
        // Prepare request
        $body = array(
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'grant_type' => 'client_credentials',
            'scope' => 'wsapi.guestrequests.read wsapi.shop.ratecalendar'
        );
        
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'body' => http_build_query($body),
            'timeout' => 30
        );
        
        $response = wp_remote_post($token_endpoint, $args);
        
        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Book Your Stay OAuth Error: ' . $response->get_error_message());
            }
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Book Your Stay OAuth Error: HTTP ' . $response_code . ' - ' . $response_body);
            }
            return false;
        }
        
        $data = json_decode($response_body, true);
        
        if (!isset($data['access_token'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Book Your Stay OAuth Error: No access token in response');
            }
            return false;
        }
        
        $access_token = $data['access_token'];
        $expires_in = isset($data['expires_in']) ? intval($data['expires_in']) : 3600;
        
        // Store refresh token if provided
        if (isset($data['refresh_token'])) {
            $refresh_token = $data['refresh_token'];
            $refresh_expires_in = isset($data['refresh_token_expires_in']) ? intval($data['refresh_token_expires_in']) : (30 * 24 * 60 * 60); // Default 30 days
            
            update_option($this->refresh_token_key, $refresh_token);
            update_option($this->refresh_token_expiry_key, time() + $refresh_expires_in);
        }
        
        // Cache the access token
        set_transient($this->token_cache_key, $access_token, $expires_in - 60); // Cache with 1 minute buffer
        update_option($this->token_expiry_key, time() + $expires_in);
        
        return $access_token;
    }
    
    /**
     * Refresh access token using refresh token
     */
    private function refresh_access_token($refresh_token) {
        $environment = get_option('bys_environment', 'uat');
        $client_id = get_option('bys_client_id', '');
        $client_secret = get_option('bys_client_secret', '');
        
        if (empty($client_id) || empty($client_secret) || empty($refresh_token)) {
            return false;
        }
        
        // Determine token endpoint based on environment
        $token_endpoint = ($environment === 'production') 
            ? 'https://id.shrglobal.com/connect/token'
            : 'https://iduat.shrglobal.com/connect/token';
        
        // Prepare refresh request
        $body = array(
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh_token,
            'scope' => 'wsapi.guestrequests.read wsapi.shop.ratecalendar'
        );
        
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'body' => http_build_query($body),
            'timeout' => 30
        );
        
        $response = wp_remote_post($token_endpoint, $args);
        
        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Book Your Stay OAuth Refresh Error: ' . $response->get_error_message());
            }
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Book Your Stay OAuth Refresh Error: HTTP ' . $response_code . ' - ' . $response_body);
            }
            return false;
        }
        
        $data = json_decode($response_body, true);
        
        if (!isset($data['access_token'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Book Your Stay OAuth Refresh Error: No access token in response');
            }
            return false;
        }
        
        $access_token = $data['access_token'];
        $expires_in = isset($data['expires_in']) ? intval($data['expires_in']) : 3600;
        
        // Update refresh token if new one provided
        if (isset($data['refresh_token'])) {
            $new_refresh_token = $data['refresh_token'];
            $refresh_expires_in = isset($data['refresh_token_expires_in']) ? intval($data['refresh_token_expires_in']) : (30 * 24 * 60 * 60);
            
            update_option($this->refresh_token_key, $new_refresh_token);
            update_option($this->refresh_token_expiry_key, time() + $refresh_expires_in);
        }
        
        // Cache the new access token
        set_transient($this->token_cache_key, $access_token, $expires_in - 60);
        update_option($this->token_expiry_key, time() + $expires_in);
        
        return $access_token;
    }
    
    /**
     * Clear cached tokens (force refresh on next request)
     */
    public function clear_token() {
        delete_transient($this->token_cache_key);
        delete_option($this->token_expiry_key);
        delete_option($this->refresh_token_key);
        delete_option($this->refresh_token_expiry_key);
    }
    
    /**
     * Check if token is valid
     */
    public function is_token_valid() {
        $token = get_transient($this->token_cache_key);
        $token_expiry = get_option($this->token_expiry_key, 0);
        
        return !empty($token) && $token_expiry > time();
    }
    
    /**
     * Get token expiry information
     */
    public function get_token_info() {
        $token = get_transient($this->token_cache_key);
        $token_expiry = get_option($this->token_expiry_key, 0);
        $refresh_token = get_option($this->refresh_token_key, '');
        $refresh_expiry = get_option($this->refresh_token_expiry_key, 0);
        
        return array(
            'has_access_token' => !empty($token),
            'access_token_expires' => $token_expiry,
            'access_token_expires_in' => $token_expiry > time() ? ($token_expiry - time()) : 0,
            'has_refresh_token' => !empty($refresh_token),
            'refresh_token_expires' => $refresh_expiry,
            'refresh_token_expires_in' => $refresh_expiry > time() ? ($refresh_expiry - time()) : 0,
        );
    }
}

