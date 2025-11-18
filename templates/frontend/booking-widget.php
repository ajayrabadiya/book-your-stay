<?php
/**
 * Booking Widget Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$deep_link = BYS_Deep_Link::get_instance();

// Get hotel code and property ID from template scope
$hotel_code = isset($template_hotel_code) ? $template_hotel_code : '';
$property_id = isset($template_property_id) ? $template_property_id : '';

// Default values
$default_checkin = date('Y-m-d', strtotime('+1 day'));
$default_checkout = date('Y-m-d', strtotime('+3 days'));
?>

<div class="bys-booking-widget">
    <div class="bys-booking-header">
        <h3 class="bys-booking-title">
            <span class="bys-booking-icon">📅</span>
            <?php echo esc_html($atts['title']); ?>
        </h3>
    </div>
    
    <form class="bys-booking-form" id="bys-booking-form">
        <div class="bys-booking-fields">
            <div class="bys-booking-field">
                <label for="bys-checkin"><?php _e('Check-In', 'book-your-stay'); ?></label>
                <input type="date" 
                       id="bys-checkin" 
                       name="checkin" 
                       class="bys-booking-input" 
                       value="<?php echo esc_attr($default_checkin); ?>"
                       min="<?php echo date('Y-m-d'); ?>"
                       required>
            </div>
            
            <div class="bys-booking-field">
                <label for="bys-checkout"><?php _e('Check-Out', 'book-your-stay'); ?></label>
                <input type="date" 
                       id="bys-checkout" 
                       name="checkout" 
                       class="bys-booking-input" 
                       value="<?php echo esc_attr($default_checkout); ?>"
                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                       required>
            </div>
            
            <div class="bys-booking-field">
                <label for="bys-adults"><?php _e('Adults', 'book-your-stay'); ?></label>
                <select id="bys-adults" name="adults" class="bys-booking-select" required>
                    <?php for ($i = 1; $i <= 10; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php selected($i, 2); ?>>
                            <?php echo $i; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="bys-booking-field">
                <label for="bys-children"><?php _e('Children', 'book-your-stay'); ?></label>
                <select id="bys-children" name="children" class="bys-booking-select">
                    <?php for ($i = 0; $i <= 5; $i++): ?>
                        <option value="<?php echo $i; ?>">
                            <?php echo $i; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="bys-booking-field bys-booking-field-full">
                <label for="bys-rooms"><?php _e('Rooms', 'book-your-stay'); ?></label>
                <select id="bys-rooms" name="rooms" class="bys-booking-select" required>
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php selected($i, 1); ?>>
                            <?php echo $i; ?> <?php echo $i === 1 ? __('Room', 'book-your-stay') : __('Rooms', 'book-your-stay'); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="bys-booking-field bys-booking-field-full" style="display: none;">
                <label for="bys-promo"><?php _e('Promo Code (Optional)', 'book-your-stay'); ?></label>
                <input type="text" 
                       id="bys-promo" 
                       name="promo" 
                       class="bys-booking-input" 
                       placeholder="<?php esc_attr_e('Enter promo code', 'book-your-stay'); ?>">
            </div>
        </div>
        
        <div class="bys-booking-actions">
            <button type="submit" class="bys-booking-button">
                <?php echo esc_html($atts['button_text']); ?>
            </button>
        </div>
    </form>
</div>

<script type="text/javascript">
(function($) {
    'use strict';
    
    // Wait for both jQuery and bysData to be available
    function initBookingForm() {
        if (typeof $ === 'undefined' || typeof jQuery === 'undefined') {
            setTimeout(initBookingForm, 100);
            return;
        }
        
        var $form = $('#bys-booking-form');
        if ($form.length === 0) {
            return;
        }
        
        var $checkin = $('#bys-checkin');
        var $checkout = $('#bys-checkout');
        
        // Validate checkout date is after checkin date
        $checkin.on('change', function() {
            var checkinDate = new Date($(this).val());
            var checkoutDate = new Date($checkout.val());
            
            if (checkoutDate <= checkinDate) {
                checkoutDate.setDate(checkinDate.getDate() + 1);
                $checkout.val(checkoutDate.toISOString().split('T')[0]);
            }
            
            // Update minimum checkout date
            $checkout.attr('min', new Date(checkinDate.getTime() + 86400000).toISOString().split('T')[0]);
        });
        
        // Handle form submission - Generate deep link via AJAX
        $form.on('submit', function(e) {
            e.preventDefault();
            
            var $button = $(this).find('button[type="submit"]');
            var originalText = $button.text();
            $button.prop('disabled', true).text('<?php esc_attr_e('Generating...', 'book-your-stay'); ?>');
            
            // Get AJAX URL and nonce (with fallback)
            var ajaxUrl = (typeof bysData !== 'undefined' && bysData.ajaxUrl) ? bysData.ajaxUrl : '<?php echo admin_url('admin-ajax.php'); ?>';
            var nonce = (typeof bysData !== 'undefined' && bysData.nonce) ? bysData.nonce : '<?php echo wp_create_nonce('bys_booking_nonce'); ?>';
            
            var formData = {
                action: 'bys_generate_deep_link',
                nonce: nonce,
                checkin: $checkin.val(),
                checkout: $checkout.val(),
                adults: $('#bys-adults').val(),
                children: $('#bys-children').val(),
                rooms: $('#bys-rooms').val(),
                promo: $('#bys-promo').val()
            };
            
            <?php if (!empty($hotel_code)): ?>
            formData.hotel_code = '<?php echo esc_js($hotel_code); ?>';
            <?php elseif (!empty($property_id)): ?>
            formData.property_id = <?php echo intval($property_id); ?>;
            <?php endif; ?>
            
            // Generate deep link via AJAX
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success && response.data.link) {
                        window.location.href = response.data.link;
                    } else {
                        alert('<?php esc_attr_e('Error generating booking link. Please try again.', 'book-your-stay'); ?>');
                        $button.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
                    alert('<?php esc_attr_e('Error generating booking link. Please try again.', 'book-your-stay'); ?>');
                    $button.prop('disabled', false).text(originalText);
                }
            });
        });
    }
    
    // Initialize when DOM is ready
    if (typeof jQuery !== 'undefined') {
        jQuery(document).ready(function($) {
            initBookingForm();
        });
    } else {
        // Fallback if jQuery not loaded yet
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initBookingForm);
        } else {
            initBookingForm();
        }
    }
})(typeof jQuery !== 'undefined' ? jQuery : null);
</script>

