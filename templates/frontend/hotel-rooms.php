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
                    <span class="bys-room-price"></span>
                    <?php if (!empty($room['image'])): ?>
                        <div class="bys-room-image">
                            <?php //echo esc_url($room['image']); ?>
                            <img src="https://dummyimage.com/1024x682/ccc/000"
                                alt="<?php echo esc_attr($room['name']); ?>" loading="lazy">
                            <?php if ($room['from_price'] !== null): ?>
                                <div class="bys-room-price-badge">
                                    <?php
                                    $currency_symbol = 'R'; // Default for ZAR
                                    if ($room['currency'] === 'USD')
                                        $currency_symbol = '$';
                                    elseif ($room['currency'] === 'EUR')
                                        $currency_symbol = '€';
                                    elseif ($room['currency'] === 'GBP')
                                        $currency_symbol = '£';
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
                            <?php //echo esc_url($room['image']); ?>
                            <img src="https://dummyimage.com/1024x682/ccc/000"
                                alt="<?php echo esc_attr($room['name']); ?>" loading="lazy">
                            <div class="bys-room-price-badge">
                                <?php
                                $currency_symbol = 'R'; // Default for ZAR
                                if ($room['currency'] === 'USD')
                                    $currency_symbol = '$';
                                elseif ($room['currency'] === 'EUR')
                                    $currency_symbol = '€';
                                elseif ($room['currency'] === 'GBP')
                                    $currency_symbol = '£';
                                ?>
                                <span class="bys-price-label"><?php _e('FROM', 'book-your-stay'); ?></span>
                                <span class="bys-price-amount">
                                    <?php echo esc_html($currency_symbol . number_format($room['from_price'], 0)); ?>
                                </span>
                                <span class="bys-price-period">/ <?php _e('NIGHT', 'book-your-stay'); ?></span>
                            </div>
                            <?php if ($room['from_price'] !== null): ?>
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
                            <ul class="bys-room-amenities">
                                <?php foreach (array_slice($room['amenities'], 0, 4) as $amenity): ?>
                                    <li class="bys-room-amenity-item">
                                        <span class="bys-amenity-icon">
                                            <?php
                                            // Map common amenities to icons
                                            $amenity_lower = strtolower($amenity);
                                            if (strpos($amenity_lower, 'heating') !== false || strpos($amenity_lower, 'bathroom') !== false) {
                                                echo '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M19.5 2.87012C17.8418 2.87012 16.5 4.21191 16.5 5.87012H15V7.37012H19.5V5.87012H18C18 4.97656 18.6064 4.37012 19.5 4.37012C20.3936 4.37012 21 4.97656 21 5.87012V10.3701H0.75V11.8701H1.64062L2.78906 17.5654V17.5889C2.96191 18.3623 3.53613 18.998 4.28906 19.2529L3.75 20.8701H5.25L5.74219 19.3701H18.2578L18.75 20.8701H20.25L19.7109 19.2529C20.4961 19.0127 21.1055 18.3828 21.2812 17.5889V17.5654L22.3594 11.8701H23.25V10.3701H22.5V5.87012C22.5 4.21191 21.1582 2.87012 19.5 2.87012ZM3.16406 11.8701H20.8594L19.8047 17.2842C19.7168 17.6094 19.4414 17.8701 19.0547 17.8701H5.01562C4.61426 17.8701 4.3418 17.6035 4.26562 17.2607L3.16406 11.8701Z" fill="#D3AA74"/>
                                                    </svg>';
                                            } elseif (strpos($amenity_lower, 'tv') !== false || strpos($amenity_lower, 'television') !== false || strpos($amenity_lower, 'dstv') !== false) {
                                                echo '<svg width="25" height="25" viewBox="0 0 25 25" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M19.57 20.5364H3.5C3.22 20.5364 3 20.3164 3 20.0364V17.8364C3 17.5564 3.22 17.3364 3.5 17.3364H19.57C19.85 17.3364 20.07 17.5564 20.07 17.8364V20.0364C20.07 20.3164 19.85 20.5364 19.57 20.5364ZM4 19.5364H19.07V18.3364H4V19.5364Z" fill="#D3AA74"/>
                                                    <path d="M17.37 13.0663H16.37V5.03638H6.69995V13.0663H5.69995V4.53638C5.69995 4.25638 5.91995 4.03638 6.19995 4.03638H16.87C17.15 4.03638 17.37 4.25638 17.37 4.53638V13.0663Z" fill="#D3AA74"/>
                                                    <path d="M17.9299 13.5664H5.12988C4.84988 13.5664 4.62988 13.3464 4.62988 13.0664C4.62988 12.7864 4.84988 12.5664 5.12988 12.5664H17.9299C18.2099 12.5664 18.4299 12.7864 18.4299 13.0664C18.4299 13.3464 18.2099 13.5664 17.9299 13.5664Z" fill="#D3AA74"/>
                                                    <path d="M5.12988 17.8364C4.84988 17.8364 4.62988 17.6164 4.62988 17.3364V13.0664C4.62988 12.7864 4.84988 12.5664 5.12988 12.5664C5.40988 12.5664 5.62988 12.7864 5.62988 13.0664V17.3364C5.62988 17.6164 5.40988 17.8364 5.12988 17.8364Z" fill="#D3AA74"/>
                                                    <path d="M7.27002 17.8364C6.99002 17.8364 6.77002 17.6164 6.77002 17.3364V13.0664C6.77002 12.7864 6.99002 12.5664 7.27002 12.5664C7.55002 12.5664 7.77002 12.7864 7.77002 13.0664V17.3364C7.77002 17.6164 7.55002 17.8364 7.27002 17.8364Z" fill="#D3AA74"/>
                                                    <path d="M9.3999 17.8364C9.1199 17.8364 8.8999 17.6164 8.8999 17.3364V13.0664C8.8999 12.7864 9.1199 12.5664 9.3999 12.5664C9.6799 12.5664 9.8999 12.7864 9.8999 13.0664V17.3364C9.8999 17.6164 9.6799 17.8364 9.3999 17.8364Z" fill="#D3AA74"/>
                                                    <path d="M11.53 17.8364C11.25 17.8364 11.03 17.6164 11.03 17.3364V13.0664C11.03 12.7864 11.25 12.5664 11.53 12.5664C11.81 12.5664 12.03 12.7864 12.03 13.0664V17.3364C12.03 17.6164 11.81 17.8364 11.53 17.8364Z" fill="#D3AA74"/>
                                                    <path d="M11.53 17.8364C11.25 17.8364 11.03 17.6164 11.03 17.3364V4.53638C11.03 4.25638 11.25 4.03638 11.53 4.03638C11.81 4.03638 12.03 4.25638 12.03 4.53638V17.3364C12.03 17.6164 11.81 17.8364 11.53 17.8364Z" fill="#D3AA74"/>
                                                    <path d="M13.6699 17.8364C13.3899 17.8364 13.1699 17.6164 13.1699 17.3364V13.0664C13.1699 12.7864 13.3899 12.5664 13.6699 12.5664C13.9499 12.5664 14.1699 12.7864 14.1699 13.0664V17.3364C14.1699 17.6164 13.9499 17.8364 13.6699 17.8364Z" fill="#D3AA74"/>
                                                    <path d="M15.8 17.8364C15.52 17.8364 15.3 17.6164 15.3 17.3364V13.0664C15.3 12.7864 15.52 12.5664 15.8 12.5664C16.08 12.5664 16.3 12.7864 16.3 13.0664V17.3364C16.3 17.6164 16.08 17.8364 15.8 17.8364Z" fill="#D3AA74"/>
                                                    <path d="M17.9299 17.8364C17.6499 17.8364 17.4299 17.6164 17.4299 17.3364V13.0664C17.4299 12.7864 17.6499 12.5664 17.9299 12.5664C18.2099 12.5664 18.4299 12.7864 18.4299 13.0664V17.3364C18.4299 17.6164 18.2099 17.8364 17.9299 17.8364Z" fill="#D3AA74"/>
                                                    </svg>';
                                            } elseif (strpos($amenity_lower, 'wifi') !== false || strpos($amenity_lower, 'internet') !== false) {
                                                echo '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M12 5.12012C8.23828 5.12012 4.84277 6.68164 2.39062 9.1748L3.44531 10.2295C5.625 8.00879 8.65137 6.62012 12 6.62012C15.3486 6.62012 18.375 8.00879 20.5547 10.2295L21.6094 9.1748C19.1572 6.68164 15.7617 5.12012 12 5.12012ZM12 8.87012C9.26953 8.87012 6.81152 10.0098 5.03906 11.8232L6.09375 12.8779C7.59375 11.3369 9.68555 10.3701 12 10.3701C14.3145 10.3701 16.4062 11.3369 17.9062 12.8779L18.9609 11.8232C17.1885 10.0098 14.7305 8.87012 12 8.87012ZM12 12.6201C10.3037 12.6201 8.78027 13.3379 7.6875 14.4717L8.74219 15.5264C9.5625 14.665 10.7168 14.1201 12 14.1201C13.2832 14.1201 14.4375 14.665 15.2578 15.5264L16.3125 14.4717C15.2227 13.3379 13.6963 12.6201 12 12.6201ZM12 16.3701C11.3379 16.3701 10.749 16.6631 10.3359 17.1201L12 18.7842L13.6641 17.1201C13.251 16.6631 12.6621 16.3701 12 16.3701Z" fill="#D3AA74"/>
                                                    </svg>';
                                            } elseif (strpos($amenity_lower, 'safe') !== false) {
                                                echo '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M3.44995 3.44995V20.55H20.55V3.44995H3.44995ZM5.0045 5.0045H18.9954V18.9954H5.0045V5.0045Z" fill="#D3AA74"/>
                                                    <path d="M13.71 6.01514V6.96514H16.3521L14.4769 8.8404L15.1597 9.52321L17.035 7.64795V10.2901H17.985V6.01514H13.71Z" fill="#D3AA74"/>
                                                    <path d="M17.985 13.71L17.035 13.71L17.035 16.3521L15.1597 14.4769L14.4769 15.1597L16.3522 17.035L13.71 17.035L13.71 17.985L17.985 17.985L17.985 13.71Z" fill="#D3AA74"/>
                                                    <path d="M10.29 17.9849L10.29 17.0349L7.64785 17.0349L9.52311 15.1596L8.8403 14.4768L6.96504 16.3521L6.96504 13.7099L6.01504 13.7099L6.01504 17.9849L10.29 17.9849Z" fill="#D3AA74"/>
                                                    <path d="M6.01501 10.29L6.96501 10.29L6.96501 7.64785L8.84028 9.52311L9.52309 8.8403L7.64783 6.96504L10.29 6.96504L10.29 6.01504L6.01502 6.01504L6.01501 10.29Z" fill="#D3AA74"/>
                                                    </svg>';
                                            } else {
                                                echo '<svg width="25" height="25" viewBox="0 0 25 25" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M19.0085 8.01001C18.8685 7.77001 18.5585 7.68002 18.3185 7.83002L15.9485 9.20001L16.3585 7.68005C16.4285 7.41005 16.2685 7.13 15.9985 7.06C15.7285 6.99 15.4485 7.15004 15.3785 7.42004L14.7085 9.92004L12.5385 11.17V8.67004L14.3685 6.84003C14.5685 6.64003 14.5685 6.32 14.3685 6.13C14.1685 5.94 13.8485 5.93 13.6585 6.13L12.5485 7.24005V4.51001C12.5485 4.23001 12.3185 4 12.0385 4C11.7585 4 11.5285 4.23001 11.5285 4.51001V7.24005L10.4185 6.13C10.2185 5.93 9.89852 5.93 9.70852 6.13C9.51852 6.33 9.50852 6.65003 9.70852 6.84003L11.5385 8.67004V11.17L9.36852 9.92004L8.69852 7.42004C8.62852 7.15004 8.34852 7 8.07852 7.06C7.80852 7.13 7.64852 7.41005 7.71852 7.68005L8.12852 9.20001L5.75852 7.83002C5.51852 7.69002 5.20852 7.77001 5.06852 8.01001C4.92852 8.25001 5.00852 8.56001 5.25852 8.70001L7.62852 10.07L6.10852 10.48C5.83852 10.55 5.67852 10.83 5.74852 11.1C5.80852 11.32 6.00852 11.47 6.23852 11.47C6.27852 11.47 6.32852 11.47 6.36852 11.45L8.86852 10.78L11.0385 12.03L8.86852 13.28L6.36852 12.61C6.09852 12.54 5.81852 12.7 5.74852 12.97C5.67852 13.24 5.83852 13.52 6.10852 13.59L7.62852 14L5.25852 15.3701C5.01852 15.5101 4.93852 15.82 5.06852 16.06C5.15852 16.22 5.32852 16.31 5.50852 16.31C5.59852 16.31 5.67852 16.2901 5.75852 16.2401L8.12852 14.8701L7.71852 16.39C7.64852 16.66 7.80852 16.94 8.07852 17.01C8.11852 17.02 8.16852 17.03 8.20852 17.03C8.42852 17.03 8.63852 16.88 8.69852 16.66L9.36852 14.16L11.5385 12.91V15.41L9.70852 17.2401C9.50852 17.4401 9.50852 17.76 9.70852 17.95C9.80852 18.05 9.93852 18.1 10.0685 18.1C10.1985 18.1 10.3285 18.05 10.4285 17.95L11.5385 16.84V19.58C11.5385 19.86 11.7685 20.09 12.0485 20.09C12.3285 20.09 12.5585 19.86 12.5585 19.58V16.84L13.6685 17.95C13.8685 18.15 14.1885 18.15 14.3785 17.95C14.5685 17.75 14.5785 17.4301 14.3785 17.2401L12.5485 15.41V12.91L14.7185 14.16L15.3885 16.66C15.4485 16.89 15.6485 17.03 15.8785 17.03C15.9185 17.03 15.9685 17.03 16.0085 17.01C16.2785 16.94 16.4385 16.66 16.3685 16.39L15.9585 14.8701L18.3285 16.2401C18.4085 16.2901 18.4985 16.31 18.5785 16.31C18.7485 16.31 18.9185 16.22 19.0185 16.06C19.1585 15.82 19.0785 15.5101 18.8285 15.3701L16.4585 14L17.9785 13.59C18.2485 13.52 18.4085 13.24 18.3385 12.97C18.2685 12.7 17.9885 12.54 17.7185 12.61L15.2185 13.28L13.0485 12.03L15.2185 10.78L17.7185 11.45C17.7185 11.45 17.8085 11.47 17.8485 11.47C18.0685 11.47 18.2785 11.32 18.3385 11.1C18.4085 10.83 18.2485 10.55 17.9785 10.48L16.4585 10.07L18.8285 8.70001C19.0685 8.56001 19.1485 8.25001 19.0185 8.01001H19.0085Z" fill="#D3AA74"/>
                                                    </svg>';
                                            }
                                            ?>
                                        </span>
                                        <span class="bys-amenity-text"><?php echo esc_html($amenity); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
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
                                <a href="<?php echo esc_url($room['booking_url']); ?>" class="bys-book-now-button" target="_blank">
                                    <?php _e('View Available Rooms & Book Now', 'book-your-stay'); ?>
                                </a>
                            <?php else: ?>
                                <!-- Normal: Generate deep link via AJAX -->
                                <a href="#" class="bys-book-now-link" data-room-code="<?php echo esc_attr($room['code']); ?>"
                                    data-hotel-code="<?php echo esc_attr($hotel_code); ?>"
                                    data-property-id="<?php echo esc_attr($property_id); ?>"
                                    data-checkin="<?php echo esc_attr($default_checkin); ?>"
                                    data-checkout="<?php echo esc_attr($default_checkout); ?>"
                                    data-adults="<?php echo esc_attr($default_adults); ?>"
                                    data-children="<?php echo esc_attr($default_children); ?>"
                                    data-rooms="<?php echo esc_attr($default_rooms); ?>">
                                    <?php _e('Book Now', 'book-your-stay'); ?>
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
    (function ($) {
        'use strict';

        function initRoomBooking() {
            if (typeof $ === 'undefined' || typeof jQuery === 'undefined') {
                setTimeout(initRoomBooking, 100);
                return;
            }

            $('.bys-book-now-link, .bys-book-now-button').on('click', function (e) {
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
                    success: function (response) {
                        if (response.success && response.data.link) {
                            window.location.href = response.data.link;
                        } else {
                            alert('<?php esc_attr_e('Error generating booking link. Please try again.', 'book-your-stay'); ?>');
                            $button.prop('disabled', false).text(originalText);
                        }
                    },
                    error: function () {
                        alert('<?php esc_attr_e('Error generating booking link. Please try again.', 'book-your-stay'); ?>');
                        $button.prop('disabled', false).text(originalText);
                    }
                });
            });
        }

        // Initialize when DOM is ready
        if (typeof jQuery !== 'undefined') {
            jQuery(document).ready(function ($) {
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