<?php
/**
 * Hotel Rooms List Template
 */
if (!defined('ABSPATH')) {
    exit;
}

$deep_link = BYS_Deep_Link::get_instance();
$rooms = isset($template_rooms) ? $template_rooms : array();
$hotel_code = isset($template_hotel_code) ? $template_hotel_code : '';
$property_id = isset($template_property_id) ? $template_property_id : '';
$atts = isset($template_atts) ? $template_atts : array();
$is_fallback = isset($template_is_fallback) ? $template_is_fallback : false;

// Default booking parameters
$default_checkin = !empty($atts['checkin']) ? $atts['checkin'] : date('Y-m-d', strtotime('+1 day'));
$default_checkout = !empty($atts['checkout']) ? $atts['checkout'] : date('Y-m-d', strtotime('+3 days'));
$default_adults = !empty($atts['adults']) ? intval($atts['adults']) : 2;
$default_children = !empty($atts['children']) ? intval($atts['children']) : 0;
$default_rooms = !empty($atts['rooms']) ? intval($atts['rooms']) : 1;


?>

<div class="bys-hotel-rooms">
    <?php if (empty($rooms)): ?>
        <p class="bys-no-rooms">
            <?php _e('No rooms available at this time. Please try again later.', 'book-your-stay'); ?>
        </p>
    <?php else: ?>
        <div class="bys-rooms-grid">
            <?php foreach ($rooms as $room): ?>
                <div class="bys-room-card <?php echo $is_fallback ? 'bys-room-card-fallback' : ''; ?>">
                    <?php if (!empty($room['image'])): ?>
                        <div class="bys-room-image">
                            <img src="<?php echo esc_url($room['image']); ?>" 
                                 alt="<?php echo esc_attr($room['name']); ?>"
                                 loading="lazy">
                            <?php if ($room['from_price'] !== null): ?>
                                <div class="bys-room-price-badge">
                                    <?php 
                                    $currency_symbol = 'R'; // Default for ZAR
                                    if ($room['currency'] === 'USD') $currency_symbol = '$';
                                    elseif ($room['currency'] === 'EUR') $currency_symbol = '€';
                                    elseif ($room['currency'] === 'GBP') $currency_symbol = '£';
                                    ?>
                                    <span class="bys-price-label"><?php _e('FROM', 'book-your-stay'); ?></span>
                                    <span class="bys-price-amount">
                                        <?php echo esc_html($currency_symbol . number_format($room['from_price'], 0)); ?>
                                    </span>
                                    <span class="bys-price-period">/ <?php _e('NIGHT', 'book-your-stay'); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- Show placeholder when no image is available -->
                        <div class="bys-room-image bys-room-image-placeholder">
                            <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #8B4513; font-size: 48px;">
                                🏨
                            </div>
                            <?php if ($room['from_price'] !== null): ?>
                                <div class="bys-room-price-badge">
                                    <?php 
                                    $currency_symbol = 'R'; // Default for ZAR
                                    if ($room['currency'] === 'USD') $currency_symbol = '$';
                                    elseif ($room['currency'] === 'EUR') $currency_symbol = '€';
                                    elseif ($room['currency'] === 'GBP') $currency_symbol = '£';
                                    ?>
                                    <span class="bys-price-label"><?php _e('FROM', 'book-your-stay'); ?></span>
                                    <span class="bys-price-amount">
                                        <?php echo esc_html($currency_symbol . number_format($room['from_price'], 0)); ?>
                                    </span>
                                    <span class="bys-price-period">/ <?php _e('NIGHT', 'book-your-stay'); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="bys-room-content">
                        <h3 class="bys-room-title"><?php echo esc_html($room['name']); ?></h3>
                        
                        <?php if (!empty($room['size']) || !empty($room['view']) || !empty($room['max_occupancy'])): ?>
                            <div class="bys-room-specs">
                                <?php 
                                $specs = array();
                                if (!empty($room['size'])) {
                                    $specs[] = esc_html($room['size']);
                                }
                                if (!empty($room['view'])) {
                                    $specs[] = esc_html($room['view']);
                                }
                                if (!empty($room['max_occupancy'])) {
                                    $specs[] = esc_html($room['max_occupancy']) . ' ' . __('Guests', 'book-your-stay');
                                }
                                if (!empty($specs)) {
                                    echo '<span class="bys-room-specs-line">' . implode(' / ', $specs) . '</span>';
                                }
                                ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($room['amenities']) && is_array($room['amenities'])): ?>
                            <div class="bys-room-amenities">
                                <?php foreach (array_slice($room['amenities'], 0, 4) as $amenity): ?>
                                    <div class="bys-room-amenity-item">
                                        <span class="bys-amenity-icon">
                                            <?php 
                                            // Map common amenities to icons
                                            $amenity_lower = strtolower($amenity);
                                            if (strpos($amenity_lower, 'heating') !== false || strpos($amenity_lower, 'bathroom') !== false) {
                                                echo '🛁';
                                            } elseif (strpos($amenity_lower, 'tv') !== false || strpos($amenity_lower, 'television') !== false || strpos($amenity_lower, 'dstv') !== false) {
                                                echo '📺';
                                            } elseif (strpos($amenity_lower, 'wifi') !== false || strpos($amenity_lower, 'internet') !== false) {
                                                echo '📶';
                                            } elseif (strpos($amenity_lower, 'safe') !== false) {
                                                echo '🔒';
                                            } else {
                                                echo '✓';
                                            }
                                            ?>
                                        </span>
                                        <span class="bys-amenity-text"><?php echo esc_html($amenity); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($room['description'])): ?>
                            <div class="bys-room-description">
                                <?php 
                                $description = wp_trim_words($room['description'], 25, '...');
                                echo esc_html($description); 
                                ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="bys-room-actions">
                            <?php if ($is_fallback && !empty($room['booking_url'])): ?>
                                <!-- Fallback: Direct link to booking engine -->
                                <a href="<?php echo esc_url($room['booking_url']); ?>" 
                                   class="bys-book-now-button"
                                   target="_blank">
                                    <?php _e('View Available Rooms & Book Now', 'book-your-stay'); ?> &gt;
                                </a>
                            <?php else: ?>
                                <!-- Normal: Generate deep link via AJAX -->
                                <a href="#" 
                                   class="bys-book-now-link" 
                                   data-room-code="<?php echo esc_attr($room['code']); ?>"
                                   data-hotel-code="<?php echo esc_attr($hotel_code); ?>"
                                   data-property-id="<?php echo esc_attr($property_id); ?>"
                                   data-checkin="<?php echo esc_attr($default_checkin); ?>"
                                   data-checkout="<?php echo esc_attr($default_checkout); ?>"
                                   data-adults="<?php echo esc_attr($default_adults); ?>"
                                   data-children="<?php echo esc_attr($default_children); ?>"
                                   data-rooms="<?php echo esc_attr($default_rooms); ?>">
                                    <?php _e('Book Now', 'book-your-stay'); ?> &gt;
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script type="text/javascript">
(function($) {
    'use strict';
    
    function initRoomBooking() {
        if (typeof $ === 'undefined' || typeof jQuery === 'undefined') {
            setTimeout(initRoomBooking, 100);
            return;
        }
        
        $('.bys-book-now-link, .bys-book-now-button').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var originalText = $button.text();
            $button.prop('disabled', true).text('<?php esc_attr_e('Loading...', 'book-your-stay'); ?>');
            
            // Get booking parameters
            var params = {
                checkin: $button.data('checkin'),
                checkout: $button.data('checkout'),
                adults: $button.data('adults'),
                children: $button.data('children'),
                rooms: $button.data('rooms')
            };
            
            // Add hotel identification
            if ($button.data('hotel-code')) {
                params.pcode = $button.data('hotel-code');
            }
            if ($button.data('property-id')) {
                params.propertyID = parseInt($button.data('property-id'));
            }
            
            // Generate deep link
            var ajaxUrl = (typeof bysData !== 'undefined' && bysData.ajaxUrl) ? bysData.ajaxUrl : '<?php echo admin_url('admin-ajax.php'); ?>';
            var nonce = (typeof bysData !== 'undefined' && bysData.nonce) ? bysData.nonce : '<?php echo wp_create_nonce('bys_booking_nonce'); ?>';
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bys_generate_deep_link',
                    nonce: nonce,
                    checkin: params.checkin,
                    checkout: params.checkout,
                    adults: params.adults,
                    children: params.children,
                    rooms: params.rooms,
                    hotel_code: params.pcode || '',
                    property_id: params.propertyID || ''
                },
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
            initRoomBooking();
        });
    } else {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initRoomBooking);
        } else {
            initRoomBooking();
        }
    }
})(typeof jQuery !== 'undefined' ? jQuery : null);
</script>

