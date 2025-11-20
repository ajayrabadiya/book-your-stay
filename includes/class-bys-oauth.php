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
        // Get credentials and ensure they're clean (no slashes, no extra encoding)
        $client_id = get_option('bys_client_id', '');
        $client_secret = get_option('bys_client_secret', '');
        
        // Remove any WordPress-added slashes and trim
        $client_id = trim(stripslashes($client_id));
        $client_secret = trim(stripslashes($client_secret));
        
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
        
        // SHR API requires credentials in request body (form-encoded)
        // Remove only truly problematic characters (null bytes, control chars) but keep valid OAuth chars like underscores
        $client_id = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $client_id);
        $client_secret = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $client_secret);
        
        // Validate credentials are not empty after cleaning
        if (empty($client_id) || empty($client_secret)) {
            $error_message = 'Client ID or Secret is empty after cleaning. Please check for hidden characters.';
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Book Your Stay OAuth Error: ' . $error_message);
            }
            update_option('bys_last_oauth_error', $error_message);
            return false;
        }
        
        // Try different scope combinations - some APIs require specific scopes
        // Based on working Postman config: wsapi.guestrequests.read works
        $scope_variations = array(
            'wsapi.guestrequests.read', // This works in Postman - try first
            'wsapi.guestrequests.read wsapi.shop.ratecalendar', // Original combined
            'wsapi.shop.ratecalendar', // Just shop
            '', // No scope
        );
        
        $last_error = null;
        
        // Try Basic Auth first (most common for OAuth 2.0 client credentials)
        foreach ($scope_variations as $scope) {
            $body_basic = array(
                'grant_type' => 'client_credentials'
            );
            
            if (!empty($scope)) {
                $body_basic['scope'] = $scope;
            }
            
            $args = array(
                'method' => 'POST',
                'headers' => array(
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Accept' => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret)
                ),
                'body' => http_build_query($body_basic),
                'timeout' => 30,
                'sslverify' => true,
                'redirection' => 0,
                'blocking' => true
            );
            
            // Debug: Log request details if WP_DEBUG is enabled
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Book Your Stay OAuth Request (Basic Auth, scope: ' . ($scope ?: 'none') . '): Endpoint=' . $token_endpoint);
            }
            
            $response = wp_remote_post($token_endpoint, $args);
            
            if (is_wp_error($response)) {
                $last_error = $response->get_error_message();
                continue;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            if ($response_code === 200) {
                // Success! Parse and return token
                $data = json_decode($response_body, true);
                if (isset($data['access_token'])) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('Book Your Stay OAuth: Success with Basic Auth, scope: ' . ($scope ?: 'none'));
                    }
                    // Clear any previous errors
                    delete_option('bys_last_oauth_error');
                    
                    $access_token = $data['access_token'];
                    $expires_in = isset($data['expires_in']) ? intval($data['expires_in']) : 3600;
                    
                    // Store refresh token if provided
                    if (isset($data['refresh_token'])) {
                        $refresh_token = $data['refresh_token'];
                        $refresh_expires_in = isset($data['refresh_token_expires_in']) ? intval($data['refresh_token_expires_in']) : (30 * 24 * 60 * 60);
                        
                        update_option($this->refresh_token_key, $refresh_token);
                        update_option($this->refresh_token_expiry_key, time() + $refresh_expires_in);
                    }
                    
                    // Cache the access token
                    set_transient($this->token_cache_key, $access_token, $expires_in - 60);
                    update_option($this->token_expiry_key, time() + $expires_in);
                    
                    return $access_token;
                }
            } else {
                // Log error but continue trying
                $error_data = json_decode($response_body, true);
                if (is_array($error_data) && isset($error_data['error'])) {
                    $last_error = 'HTTP ' . $response_code . ': ' . $error_data['error'];
                    if (isset($error_data['error_description'])) {
                        $last_error .= ' - ' . $error_data['error_description'];
                    }
                } else {
                    $last_error = 'HTTP ' . $response_code . ': ' . substr($response_body, 0, 100);
                }
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Book Your Stay OAuth: Failed with scope "' . ($scope ?: 'none') . '": ' . $last_error);
                }
            }
        }
        
        // If Basic Auth failed, try form-encoded body method (x-www-form-urlencoded)
        // This matches the working Postman configuration exactly
        foreach ($scope_variations as $scope) {
            $body = array(
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'grant_type' => 'client_credentials'
            );
            
            if (!empty($scope)) {
                $body['scope'] = $scope;
            }
            
            $args = array(
                'method' => 'POST',
                'headers' => array(
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Accept' => 'application/json'
                ),
                'body' => http_build_query($body),
                'timeout' => 30,
                'sslverify' => true,
                'redirection' => 0,
                'blocking' => true
            );
            
            // Debug: Log request details if WP_DEBUG is enabled
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Book Your Stay OAuth Request (Form Body, scope: ' . ($scope ?: 'none') . '): Endpoint=' . $token_endpoint);
            }
            
            $response = wp_remote_post($token_endpoint, $args);
            
            if (is_wp_error($response)) {
                $last_error = $response->get_error_message();
                continue;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            if ($response_code === 200) {
                // Success! Parse and return token
                $data = json_decode($response_body, true);
                if (isset($data['access_token'])) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('Book Your Stay OAuth: Success with Form Body, scope: ' . ($scope ?: 'none'));
                    }
                    // Clear any previous errors
                    delete_option('bys_last_oauth_error');
                    
                    $access_token = $data['access_token'];
                    $expires_in = isset($data['expires_in']) ? intval($data['expires_in']) : 3600;
                    
                    // Store refresh token if provided
                    if (isset($data['refresh_token'])) {
                        $refresh_token = $data['refresh_token'];
                        $refresh_expires_in = isset($data['refresh_token_expires_in']) ? intval($data['refresh_token_expires_in']) : (30 * 24 * 60 * 60);
                        
                        update_option($this->refresh_token_key, $refresh_token);
                        update_option($this->refresh_token_expiry_key, time() + $refresh_expires_in);
                    }
                    
                    // Cache the access token
                    set_transient($this->token_cache_key, $access_token, $expires_in - 60);
                    update_option($this->token_expiry_key, time() + $expires_in);
                    
                    return $access_token;
                }
            } else {
                // Log error but continue trying
                $error_data = json_decode($response_body, true);
                if (is_array($error_data) && isset($error_data['error'])) {
                    $last_error = 'HTTP ' . $response_code . ': ' . $error_data['error'];
                    if (isset($error_data['error_description'])) {
                        $last_error .= ' - ' . $error_data['error_description'];
                    }
                } else {
                    $last_error = 'HTTP ' . $response_code . ': ' . substr($response_body, 0, 100);
                }
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Book Your Stay OAuth: Failed with Form Body, scope "' . ($scope ?: 'none') . '": ' . $last_error);
                }
            }
        }
        
        // If we get here, all methods failed - use the last error
        $response_code = 400; // Default error code
        $response_body = '';
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Book Your Stay OAuth Error: ' . $error_message);
            }
            update_option('bys_last_oauth_error', $error_message);
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // Debug: Log response details
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Book Your Stay OAuth Response: Code=' . $response_code . ', Body=' . substr($response_body, 0, 200));
        }
        
        // If form body method also failed, use the last error from Basic Auth attempts
        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            if (is_array($error_data) && isset($error_data['error'])) {
                $last_error = 'HTTP ' . $response_code . ': ' . $error_data['error'];
                if (isset($error_data['error_description'])) {
                    $last_error .= ' - ' . $error_data['error_description'];
                }
            } else {
                $last_error = 'HTTP ' . $response_code . ': ' . substr($response_body, 0, 100);
            }
        }
        
        // If we get here, all methods failed
        if ($response_code !== 200) {
            // Use the last error we collected, or parse current response
            if (empty($last_error)) {
                $error_data = json_decode($response_body, true);
                $error_message = 'HTTP ' . $response_code;
                
                if (is_array($error_data)) {
                    if (isset($error_data['error'])) {
                        $error_message .= ': ' . $error_data['error'];
                        if (isset($error_data['error_description'])) {
                            $error_message .= ' - ' . $error_data['error_description'];
                        }
                    } elseif (isset($error_data['message'])) {
                        $error_message .= ': ' . $error_data['message'];
                    } else {
                        $error_message .= ': ' . $response_body;
                    }
                } else {
                    $error_message .= ': ' . $response_body;
                }
                $last_error = $error_message;
            }
            
            // Add helpful troubleshooting info
            $troubleshooting = '';
            if (strpos($last_error, 'invalid_client') !== false) {
                $troubleshooting = ' Troubleshooting: 1) Verify credentials match the ' . strtoupper($environment) . ' environment, 2) Check for extra spaces or hidden characters, 3) Ensure credentials are active in the SHR system, 4) Contact SHR support to verify your API access.';
            }
            
            $final_error = $last_error . $troubleshooting;
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Book Your Stay OAuth Error (All methods failed): ' . $final_error);
            }
            // Store error for retrieval
            update_option('bys_last_oauth_error', $final_error);
            return false;
        }
        
        $data = json_decode($response_body, true);
        
        if (!isset($data['access_token'])) {
            $error_message = 'No access token in response';
            if (is_array($data) && isset($data['error'])) {
                $error_message = $data['error'];
                if (isset($data['error_description'])) {
                    $error_message .= ': ' . $data['error_description'];
                }
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Book Your Stay OAuth Error: ' . $error_message . ' - Response: ' . $response_body);
            }
            // Store error for retrieval
            update_option('bys_last_oauth_error', $error_message);
            return false;
        }
        
        // Clear any previous errors on success
        delete_option('bys_last_oauth_error');
        
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
        $client_id = trim(get_option('bys_client_id', ''));
        $client_secret = trim(get_option('bys_client_secret', ''));
        $refresh_token = trim($refresh_token);
        
        if (empty($client_id) || empty($client_secret) || empty($refresh_token)) {
            return false;
        }
        
        // Determine token endpoint based on environment
        $token_endpoint = ($environment === 'production') 
            ? 'https://id.shrglobal.com/connect/token'
            : 'https://iduat.shrglobal.com/connect/token';
        
        // SHR API requires credentials in request body (form-encoded)
        $scope = 'wsapi.guestrequests.read wsapi.shop.ratecalendar';
        $body = array(
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh_token
        );
        if (!empty($scope)) {
            $body['scope'] = $scope;
        }
        
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json'
            ),
            'body' => http_build_query($body),
            'timeout' => 30,
            'sslverify' => true
        );
        
        $response = wp_remote_post($token_endpoint, $args);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Book Your Stay OAuth Refresh Error: ' . $error_message);
            }
            // Store error for retrieval
            update_option('bys_last_oauth_error', $error_message);
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // If form-encoded fails, try Basic Auth as fallback
        if ($response_code === 401 || $response_code === 400) {
            // Retry with Basic Authentication
            $body_basic = array(
                'grant_type' => 'refresh_token',
                'refresh_token' => $refresh_token
            );
            if (!empty($scope)) {
                $body_basic['scope'] = $scope;
            }
            
            $args['headers'] = array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret)
            );
            $args['body'] = http_build_query($body_basic);
            
            $response = wp_remote_post($token_endpoint, $args);
            
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Book Your Stay OAuth Refresh Error (Basic Auth retry): ' . $error_message);
                }
                update_option('bys_last_oauth_error', $error_message);
                return false;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
        }
        
        if ($response_code !== 200) {
            // Try to parse error response
            $error_data = json_decode($response_body, true);
            $error_message = 'HTTP ' . $response_code;
            
            if (is_array($error_data)) {
                if (isset($error_data['error'])) {
                    $error_message .= ': ' . $error_data['error'];
                    if (isset($error_data['error_description'])) {
                        $error_message .= ' - ' . $error_data['error_description'];
                    }
                } elseif (isset($error_data['message'])) {
                    $error_message .= ': ' . $error_data['message'];
                } else {
                    $error_message .= ': ' . $response_body;
                }
            } else {
                $error_message .= ': ' . $response_body;
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Book Your Stay OAuth Refresh Error: ' . $error_message);
            }
            // Store error for retrieval
            update_option('bys_last_oauth_error', $error_message);
            return false;
        }
        
        $data = json_decode($response_body, true);
        
        if (!isset($data['access_token'])) {
            $error_message = 'No access token in response';
            if (is_array($data) && isset($data['error'])) {
                $error_message = $data['error'];
                if (isset($data['error_description'])) {
                    $error_message .= ': ' . $data['error_description'];
                }
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Book Your Stay OAuth Refresh Error: ' . $error_message . ' - Response: ' . $response_body);
            }
            // Store error for retrieval
            update_option('bys_last_oauth_error', $error_message);
            return false;
        }
        
        // Clear any previous errors on success
        delete_option('bys_last_oauth_error');
        
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

