<?php
/**
 * Settings template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap bys-admin">
    <h1><?php _e('Book Your Stay Settings', 'book-your-stay'); ?></h1>
    
    <?php if ($message === 'saved'): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Settings saved successfully!', 'book-your-stay'); ?></p>
        </div>
    <?php endif; ?>
    
    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="bys-settings-form">
        <?php wp_nonce_field('bys_settings_nonce'); ?>
        <input type="hidden" name="action" value="bys_save_settings">
        
        <table class="form-table">
            <tr>
                <th colspan="2"><h2><?php _e('Hotel Information', 'book-your-stay'); ?></h2></th>
            </tr>
            
            <tr>
                <th><label for="hotel_code"><?php _e('Hotel Code (pcode)', 'book-your-stay'); ?></label></th>
                <td>
                    <input type="text" id="hotel_code" name="hotel_code" 
                           class="regular-text" value="<?php echo esc_attr($hotel_code); ?>" 
                           placeholder="e.g., ALMD">
                    <p class="description">
                        <?php _e('CRS Property code in Windsurfer CRS (found under Property Description > Address/Contacts > CRS Property ID)', 'book-your-stay'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th><label for="property_id"><?php _e('Property ID (Optional)', 'book-your-stay'); ?></label></th>
                <td>
                    <input type="number" id="property_id" name="property_id" 
                           class="regular-text" value="<?php echo esc_attr($property_id); ?>" 
                           placeholder="e.g., 14035">
                    <p class="description">
                        <?php _e('CRS property numeric ID (found by hovering over the property name in Windsurfer CRS). Use either Hotel Code or Property ID.', 'book-your-stay'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th colspan="2"><h2><?php _e('SHR API Credentials', 'book-your-stay'); ?></h2></th>
            </tr>
            
            <tr>
                <th><label for="environment"><?php _e('Environment', 'book-your-stay'); ?></label></th>
                <td>
                    <select id="environment" name="environment">
                        <option value="uat" <?php selected($environment, 'uat'); ?>>
                            <?php _e('UAT (Testing)', 'book-your-stay'); ?>
                        </option>
                        <option value="production" <?php selected($environment, 'production'); ?>>
                            <?php _e('Production', 'book-your-stay'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php _e('Select the SHR API environment. Use UAT for testing and Production for live sites.', 'book-your-stay'); ?>
                        <br><strong style="color: #d63638;">⚠️ Important:</strong> <?php _e('Your credentials must match the selected environment. Production credentials will NOT work with UAT endpoint and vice versa.', 'book-your-stay'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th><label for="client_id"><?php _e('Client ID', 'book-your-stay'); ?></label></th>
                <td>
                    <input type="text" id="client_id" name="client_id" 
                           class="regular-text" value="<?php echo esc_attr($client_id); ?>" 
                           placeholder="Enter SHR API Client ID">
                    <p class="description">
                        <?php _e('OAuth 2.0 Client ID issued by SHR for API access.', 'book-your-stay'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th><label for="client_secret"><?php _e('Client Secret', 'book-your-stay'); ?></label></th>
                <td>
                    <input type="password" id="client_secret" name="client_secret" 
                           class="regular-text" value="<?php echo esc_attr($client_secret); ?>" 
                           placeholder="Enter SHR API Client Secret">
                    <p class="description">
                        <?php _e('OAuth 2.0 Client Secret issued by SHR for API access. Tokens are automatically refreshed when expired.', 'book-your-stay'); ?>
                    </p>
                </td>
            </tr>
            
            <?php if (!empty($client_id)): ?>
            <tr>
                <th><label><?php _e('Token Status', 'book-your-stay'); ?></label></th>
                <td>
                    <?php 
                    $oauth_token = BYS_OAuth::get_instance();
                    $is_valid = $oauth_token->is_token_valid();
                    $token_info = $oauth_token->get_token_info();
                    ?>
                    <div style="margin-bottom: 10px;">
                        <span class="dashicons <?php echo $is_valid ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" 
                              style="color: <?php echo $is_valid ? 'green' : 'orange'; ?>;"></span>
                        <span>
                            <?php echo $is_valid ? __('Access Token: Valid', 'book-your-stay') : __('Access Token: Expired or not available', 'book-your-stay'); ?>
                        </span>
                        <?php if ($token_info['access_token_expires_in'] > 0): ?>
                            <span style="color: #666; margin-left: 10px;">
                                (<?php echo sprintf(__('Expires in %s', 'book-your-stay'), human_time_diff(time(), $token_info['access_token_expires'])); ?>)
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php if ($token_info['has_refresh_token']): ?>
                    <div style="margin-bottom: 10px;">
                        <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                        <span><?php _e('Refresh Token: Available', 'book-your-stay'); ?></span>
                        <?php if ($token_info['refresh_token_expires_in'] > 0): ?>
                            <span style="color: #666; margin-left: 10px;">
                                (<?php echo sprintf(__('Expires in %s', 'book-your-stay'), human_time_diff(time(), $token_info['refresh_token_expires'])); ?>)
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <p class="description">
                        <?php _e('Access tokens are automatically refreshed when needed using refresh tokens. No manual action required.', 'book-your-stay'); ?>
                    </p>
                    <button type="button" class="button" id="bys-test-token" style="margin-top: 5px;">
                        <?php _e('Test Token', 'book-your-stay'); ?>
                    </button>
                    <div id="bys-token-test-result" style="margin-top: 10px; display: none;"></div>
                    <?php 
                    $last_error = get_option('bys_last_oauth_error', '');
                    if (!empty($last_error)): 
                    ?>
                    <div style="margin-top: 10px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107;">
                        <strong><?php _e('Last OAuth Error:', 'book-your-stay'); ?></strong>
                        <p style="margin: 5px 0 0 0;"><?php echo esc_html($last_error); ?></p>
                    </div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endif; ?>
            
            <?php if (!empty($client_id) && !empty($client_secret)): ?>
            <tr>
                <th colspan="2">
                    <h2><?php _e('Troubleshooting', 'book-your-stay'); ?></h2>
                </th>
            </tr>
            <tr>
                <td colspan="2">
                    <div style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 15px; margin: 10px 0;">
                        <h3 style="margin-top: 0;"><?php _e('If you see "invalid_client" error:', 'book-your-stay'); ?></h3>
                        <ol>
                            <li><?php _e('Verify your credentials match the selected environment:', 'book-your-stay'); ?>
                                <ul>
                                    <li><strong>UAT:</strong> <?php _e('Use UAT credentials from SHR', 'book-your-stay'); ?></li>
                                    <li><strong>Production:</strong> <?php _e('Use Production credentials from SHR', 'book-your-stay'); ?></li>
                                </ul>
                            </li>
                            <li><?php _e('Check for hidden characters:', 'book-your-stay'); ?>
                                <ul>
                                    <li><?php _e('Copy credentials directly from SHR (don\'t type manually)', 'book-your-stay'); ?></li>
                                    <li><?php _e('Remove any extra spaces before/after credentials', 'book-your-stay'); ?></li>
                                    <li><?php _e('Ensure no line breaks or special characters', 'book-your-stay'); ?></li>
                                </ul>
                            </li>
                            <li><?php _e('Verify API access:', 'book-your-stay'); ?>
                                <ul>
                                    <li><?php _e('Confirm your API credentials are active in the SHR system', 'book-your-stay'); ?></li>
                                    <li><?php _e('Check that your account has API access permissions', 'book-your-stay'); ?></li>
                                    <li><?php _e('Contact SHR support to verify your API credentials', 'book-your-stay'); ?></li>
                                </ul>
                            </li>
                            <li><?php _e('Check WordPress debug log:', 'book-your-stay'); ?>
                                <ul>
                                    <li><?php _e('Enable WP_DEBUG in wp-config.php', 'book-your-stay'); ?></li>
                                    <li><?php _e('Check wp-content/debug.log for detailed error messages', 'book-your-stay'); ?></li>
                                </ul>
                            </li>
                        </ol>
                        <p><strong><?php _e('Note:', 'book-your-stay'); ?></strong> <?php _e('The plugin tries multiple authentication methods automatically. If all fail, the credentials are likely incorrect or inactive.', 'book-your-stay'); ?></p>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
            
            <tr>
                <th colspan="2"><h2><?php _e('Deep Link Testing', 'book-your-stay'); ?></h2></th>
            </tr>
            
            <tr>
                <th><label><?php _e('Test Deep Link', 'book-your-stay'); ?></label>
                <td>
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px;"><?php _e('Check-In Date:', 'book-your-stay'); ?></label>
                        <input type="date" id="bys-test-checkin" class="regular-text" 
                               value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" 
                               min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px;"><?php _e('Check-Out Date:', 'book-your-stay'); ?></label>
                        <input type="date" id="bys-test-checkout" class="regular-text" 
                               value="<?php echo date('Y-m-d', strtotime('+3 days')); ?>" 
                               min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px;"><?php _e('Adults:', 'book-your-stay'); ?></label>
                        <input type="number" id="bys-test-adults" class="small-text" value="2" min="1" max="10">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px;"><?php _e('Rooms:', 'book-your-stay'); ?></label>
                        <input type="number" id="bys-test-rooms" class="small-text" value="1" min="1" max="5">
                    </div>
                    <button type="button" class="button button-secondary" id="bys-generate-test-link">
                        <?php _e('Generate Deep Link', 'book-your-stay'); ?>
                    </button>
                    <div id="bys-test-link-result" style="margin-top: 15px; display: none;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('Generated Link:', 'book-your-stay'); ?></label>
                        <input type="text" id="bys-test-link-url" class="large-text" readonly>
                        <button type="button" class="button" id="bys-copy-test-link" style="margin-top: 5px;">
                            <?php _e('Copy Link', 'book-your-stay'); ?>
                        </button>
                        <a href="#" id="bys-open-test-link" target="_blank" class="button" style="margin-top: 5px; margin-left: 5px;">
                            <?php _e('Open Link', 'book-your-stay'); ?>
                        </a>
                    </div>
                </td>
            </tr>
            
            <tr>
                <th><label><?php _e('Shortcode', 'book-your-stay'); ?></label></th>
                <td>
                    <div class="bys-shortcode-wrapper">
                        <input type="text" class="regular-text" 
                               value="[book_your_stay]" 
                               readonly>
                        <button type="button" class="button bys-copy-btn">
                            <?php _e('Copy', 'book-your-stay'); ?>
                        </button>
                    </div>
                    <p class="description">
                        <?php _e('Use this shortcode to display the booking widget on any page or post. You can also customize:', 'book-your-stay'); ?>
                        <br>
                        <code>[book_your_stay title="Book Now" button_text="Search Rooms"]</code>
                    </p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" class="button button-primary" value="<?php _e('Save Settings', 'book-your-stay'); ?>">
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Copy shortcode
    $('.bys-copy-btn').on('click', function() {
        var $input = $(this).prev('input');
        $input.select();
        document.execCommand('copy');
        $(this).text('<?php esc_attr_e('Copied!', 'book-your-stay'); ?>');
        setTimeout(function() {
            $('.bys-copy-btn').text('<?php esc_attr_e('Copy', 'book-your-stay'); ?>');
        }, 2000);
    });
    
    // Test token
    $('#bys-test-token').on('click', function() {
        var $button = $(this);
        var originalText = $button.text();
        $button.prop('disabled', true).text('<?php esc_attr_e('Testing...', 'book-your-stay'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bys_test_token',
                nonce: '<?php echo wp_create_nonce('bys_test_token'); ?>'
            },
            success: function(response) {
                var $result = $('#bys-token-test-result');
                $result.show();
                
                if (response.success) {
                    $result.html('<div style="padding: 10px; background: #d4edda; border-left: 4px solid #28a745; color: #155724;"><strong>✓ Success!</strong> ' + (response.data.message || 'Token is valid and working!') + '</div>');
                } else {
                    var errorMsg = response.data && response.data.message ? response.data.message : 'Unknown error';
                    $result.html('<div style="padding: 10px; background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24;"><strong>✗ Failed:</strong> ' + errorMsg + '</div>');
                }
                $button.prop('disabled', false).text(originalText);
            },
            error: function() {
                alert('<?php esc_attr_e('Error testing token. Please try again.', 'book-your-stay'); ?>');
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Generate test deep link
    $('#bys-generate-test-link').on('click', function() {
        var $button = $(this);
        var originalText = $button.text();
        $button.prop('disabled', true).text('<?php esc_attr_e('Generating...', 'book-your-stay'); ?>');
        
        var formData = {
            action: 'bys_generate_deep_link',
            nonce: '<?php echo wp_create_nonce('bys_booking_nonce'); ?>',
            checkin: $('#bys-test-checkin').val(),
            checkout: $('#bys-test-checkout').val(),
            adults: $('#bys-test-adults').val(),
            rooms: $('#bys-test-rooms').val()
        };
        
        <?php if (!empty($hotel_code)): ?>
        formData.hotel_code = '<?php echo esc_js($hotel_code); ?>';
        <?php elseif (!empty($property_id)): ?>
        formData.property_id = <?php echo intval($property_id); ?>;
        <?php endif; ?>
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success && response.data.link) {
                    $('#bys-test-link-url').val(response.data.link);
                    $('#bys-open-test-link').attr('href', response.data.link);
                    $('#bys-test-link-result').show();
                } else {
                    alert('<?php esc_attr_e('Error generating deep link. Please check your settings.', 'book-your-stay'); ?>');
                }
                $button.prop('disabled', false).text(originalText);
            },
            error: function() {
                alert('<?php esc_attr_e('Error generating deep link. Please try again.', 'book-your-stay'); ?>');
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Copy test link
    $('#bys-copy-test-link').on('click', function() {
        var $input = $('#bys-test-link-url');
        $input.select();
        document.execCommand('copy');
        $(this).text('<?php esc_attr_e('Copied!', 'book-your-stay'); ?>');
        setTimeout(function() {
            $('#bys-copy-test-link').text('<?php esc_attr_e('Copy Link', 'book-your-stay'); ?>');
        }, 2000);
    });
    
    // Update checkout minimum date
    $('#bys-test-checkin').on('change', function() {
        var checkinDate = new Date($(this).val());
        if (checkinDate) {
            var minCheckout = new Date(checkinDate);
            minCheckout.setDate(minCheckout.getDate() + 1);
            $('#bys-test-checkout').attr('min', minCheckout.toISOString().split('T')[0]);
            
            var checkoutDate = new Date($('#bys-test-checkout').val());
            if (checkoutDate <= checkinDate) {
                $('#bys-test-checkout').val(minCheckout.toISOString().split('T')[0]);
            }
        }
    });
});
</script>

