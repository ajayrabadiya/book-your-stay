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

<div class="bys-booking-widget-wrapper">
    <button type="button" class="bys-mobile-toggle-btn" aria-expanded="false">
        <span class="bys-mobile-toggle-icon">
            <svg width="36" height="36" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path
                    d="M10.125 4.5V5.625H5.625V30.375H30.375V5.625H25.875V4.5H23.625V5.625H12.375V4.5H10.125ZM7.875 7.875H10.125V9H12.375V7.875H23.625V9H25.875V7.875H28.125V10.125H7.875V7.875ZM7.875 12.375H28.125V28.125H7.875V12.375ZM14.625 14.625V16.875H16.875V14.625H14.625ZM19.125 14.625V16.875H21.375V14.625H19.125ZM23.625 14.625V16.875H25.875V14.625H23.625ZM10.125 19.125V21.375H12.375V19.125H10.125ZM14.625 19.125V21.375H16.875V19.125H14.625ZM19.125 19.125V21.375H21.375V19.125H19.125ZM23.625 19.125V21.375H25.875V19.125H23.625ZM10.125 23.625V25.875H25.875V23.625H10.125Z"
                    fill="#D3AA74" />
            </svg>
        </span>
        <span class="bys-mobile-toggle-text">Make A Reservation</span>
        <span class="bys-mobile-toggle-chevron">
            <svg width="8" height="14" viewBox="0 0 8 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path
                    d="M-3.14176e-08 12.5L0.71875 13.2187L6.96875 6.96875L7.3125 6.60937L6.96875 6.25L0.718749 -3.14176e-08L-5.46392e-07 0.71875L5.89062 6.60937L-3.14176e-08 12.5Z"
                    fill="#D3AA74" />
            </svg>
        </span>
    </button>
    <div class="bys-booking-widget">
        <div class="bys-booking-header bys-header-toggle" role="button" tabindex="0" aria-expanded="true" aria-controls="bys-booking-form">
            <h3 class="bys-booking-title">
                <span class="bys-booking-icon">
                    <svg width="25" height="26" viewBox="0 0 25 26" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M4.5 0V1.125H0V25.875H24.75V1.125H20.25V0H18V1.125H6.75V0H4.5ZM2.25 3.375H4.5V4.5H6.75V3.375H18V4.5H20.25V3.375H22.5V5.625H2.25V3.375ZM2.25 7.875H22.5V23.625H2.25V7.875ZM9 10.125V12.375H11.25V10.125H9ZM13.5 10.125V12.375H15.75V10.125H13.5ZM18 10.125V12.375H20.25V10.125H18ZM4.5 14.625V16.875H6.75V14.625H4.5ZM9 14.625V16.875H11.25V14.625H9ZM13.5 14.625V16.875H15.75V14.625H13.5ZM18 14.625V16.875H20.25V14.625H18ZM4.5 19.125V21.375H20.25V19.125H4.5Z"
                            fill="#D3AA74" />
                    </svg>
                </span>
                <?php echo esc_html($atts['title']); ?>
            </h3>
        </div>

        <form class="bys-booking-form" id="bys-booking-form">
            <div class="bys-booking-fields">
                <div class="bys-booking-field bys-date-field" id="bys-date-field-checkin">
                    <label for="bys-checkin"><?php _e('Check-In', 'book-your-stay'); ?></label>
                    <div class="bys-date-picker-wrapper">
                        <input type="text" id="bys-checkin" name="checkin" class="bys-date-input"
                            value="<?php echo esc_attr($default_checkin); ?>"
                            data-min-date="<?php echo date('Y-m-d'); ?>" readonly required>
                        <span class="bys-date-icon">
                            <svg width="14" height="8" viewBox="0 0 14 8" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M0.71875 0L0 0.71875L6.25 6.96875L6.60937 7.3125L6.96875 6.96875L13.2188 0.71875L12.5 0L6.60937 5.89063L0.71875 0Z"
                                    fill="#D3AA74" />
                            </svg>
                        </span>
                    </div>
                </div>

                <div class="bys-booking-field bys-date-field" id="bys-date-field-checkout">
                    <label for="bys-checkout"><?php _e('Check-Out', 'book-your-stay'); ?></label>
                    <div class="bys-date-picker-wrapper">
                        <input type="text" id="bys-checkout" name="checkout" class="bys-date-input"
                            value="<?php echo esc_attr($default_checkout); ?>"
                            data-min-date="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" readonly required>
                        <span class="bys-date-icon">
                            <svg width="14" height="8" viewBox="0 0 14 8" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M0.71875 0L0 0.71875L6.25 6.96875L6.60937 7.3125L6.96875 6.96875L13.2188 0.71875L12.5 0L6.60937 5.89063L0.71875 0Z"
                                    fill="#D3AA74" />
                            </svg>
                        </span>
                    </div>
                </div>

                <div class="bys-booking-field bys-booking-field-full">
                    <label for="bys-guests"><?php _e('Guests', 'book-your-stay'); ?></label>
                    <div class="custom-select" data-target="#bys-guests">
                        <?php
                        $default_adults = 1;
                        $default_children = 0;
                        $adults_text = $default_adults . ($default_adults === 1 ? ' Adult' : ' Adults');
                        $children_text = $default_children . ($default_children === 1 ? ' Child' : ' Children');
                        ?>
                        <span class="selected-value">
                            <span
                                class="rooms-text"><?php echo esc_html($adults_text . ', ' . $children_text); ?></span>
                            <span class="chevron-icon">
                                <svg width="14" height="8" viewBox="0 0 14 8" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M0.71875 0L0 0.71875L6.25 6.96875L6.60937 7.3125L6.96875 6.96875L13.2188 0.71875L12.5 0L6.60937 5.89063L0.71875 0Z"
                                        fill="#D3AA74" />
                                </svg>
                            </span>
                        </span>
                        <div class="dropdown-box">
                            <?php ?>
                            <div class="bys-booking-count">
                                <label for=""><?php _e('Adults', 'book-your-stay'); ?></label>
                                <div>
                                    <span class="dec">–</span>
                                    <input type="text" class="qty" value="<?php echo $default_adults; ?>" readonly
                                        data-min="1" data-max="10">
                                    <span class="inc">+</span>
                                </div>
                            </div>
                            <?php ?>
                            <div class="bys-booking-count">
                                <label for=""><?php _e('Children', 'book-your-stay'); ?></label>
                                <div>
                                    <span class="dec">–</span>
                                    <input type="text" class="qty" value="<?php echo $default_children; ?>" readonly
                                        data-min="0" data-max="5">
                                    <span class="inc">+</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" id="bys-guests" name="guests"
                        value='{"adults":<?php echo $default_adults; ?>,"children":<?php echo $default_children; ?>}'>
                </div>

                <div class="bys-booking-field bys-booking-field-full">
                    <label for="bys-rooms"><?php _e('Rooms', 'book-your-stay'); ?></label>
                    <div class="custom-select" data-target="#bys-rooms">
                        <?php
                        $default_rooms = 1;
                        $rooms_text = $default_rooms . ' ' . ($default_rooms === 1 ? __('Room', 'book-your-stay') : __('Rooms', 'book-your-stay'));
                        ?>
                        <span class="selected-value">
                            <span class="rooms-text"><?php echo esc_html($rooms_text); ?></span>
                            <span class="chevron-icon">
                                <svg width="14" height="8" viewBox="0 0 14 8" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M0.71875 0L0 0.71875L6.25 6.96875L6.60937 7.3125L6.96875 6.96875L13.2188 0.71875L12.5 0L6.60937 5.89063L0.71875 0Z"
                                        fill="#D3AA74" />
                                </svg>
                            </span>
                        </span>
                        <div class="dropdown-box">
                            <?php ?>
                            <div class="bys-booking-count">
                                <label for=""><?php _e('Rooms', 'book-your-stay'); ?></label>
                                <div>
                                    <span class="dec">–</span>
                                    <input type="text" class="qty" value="<?php echo $default_rooms; ?>" readonly
                                        data-min="1" data-max="5">
                                    <span class="inc">+</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" id="bys-rooms" name="rooms" value="<?php echo $default_rooms; ?>">
                </div>

                <div class="bys-booking-field bys-booking-field-full" style="display: none;">
                    <label for="bys-promo"><?php _e('Promo Code (Optional)', 'book-your-stay'); ?></label>
                    <input type="text" id="bys-promo" name="promo" class="bys-booking-input"
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
</div>

<script type="text/javascript">
    (function ($) {
        'use strict';

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

            $checkin.on('change', function () {
                var checkinDate = new Date($(this).val());
                var checkoutDate = new Date($checkout.val());

                if (checkoutDate <= checkinDate) {
                    checkoutDate.setDate(checkinDate.getDate() + 1);
                    $checkout.val(checkoutDate.toISOString().split('T')[0]);
                }
                $checkout.attr('min', new Date(checkinDate.getTime() + 86400000).toISOString().split('T')[0]);
            });

            $form.on('submit', function (e) {
                e.preventDefault();

                var $button = $(this).find('button[type="submit"]');
                var originalText = $button.text();
                $button.prop('disabled', true).text('<?php esc_attr_e('Generating...', 'book-your-stay'); ?>');

                var ajaxUrl = (typeof bysData !== 'undefined' && bysData.ajaxUrl) ? bysData.ajaxUrl : '<?php echo admin_url('admin-ajax.php'); ?>';
                var nonce = (typeof bysData !== 'undefined' && bysData.nonce) ? bysData.nonce : '<?php echo wp_create_nonce('bys_booking_nonce'); ?>';

                // Extract adults and children from hidden guests field (JSON)
                var guestsRaw = $('#bys-guests').val();
                var adultsVal = 1;
                var childrenVal = 0;

                if (guestsRaw) {
                    try {
                        var guestsObj = JSON.parse(guestsRaw);
                        if (typeof guestsObj.adults !== 'undefined') {
                            adultsVal = guestsObj.adults;
                        }
                        if (typeof guestsObj.children !== 'undefined') {
                            childrenVal = guestsObj.children;
                        }
                    } catch (err) {
                        // Fallback to defaults if parsing fails
                    }
                }

                var formData = {
                    action: 'bys_generate_deep_link',
                    nonce: nonce,
                    checkin: $checkin.val(),
                    checkout: $checkout.val(),
                    adults: adultsVal,
                    children: childrenVal,
                    rooms: $('#bys-rooms').val(),
                    promo: $('#bys-promo').val()
                };

                <?php if (!empty($hotel_code)): ?>
                    formData.hotel_code = '<?php echo esc_js($hotel_code); ?>';
                <?php elseif (!empty($property_id)): ?>
                    formData.property_id = <?php echo intval($property_id); ?>;
                <?php endif; ?>

                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: formData,
                    success: function (response) {
                        if (response.success && response.data.link) {
                            window.open(response.data.link, '_blank', 'noopener,noreferrer');
                            $button.prop('disabled', false).text(originalText);
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

        if (typeof jQuery !== 'undefined') {
            jQuery(document).ready(function ($) {
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

<script>
    jQuery(function ($) {

        $(".custom-select .selected-value").on("click", function (e) {
            e.stopPropagation();
            var $dropdown = $(this).closest(".custom-select").find(".dropdown-box");
            var $chevron = $(this).find(".chevron-icon svg path");
            var isVisible = $dropdown.is(":visible");

            $(".dropdown-box").not($dropdown).hide();
            $(".custom-select").not($(this).closest(".custom-select")).removeClass("active");

            if (isVisible) {
                $dropdown.hide();
                $(this).closest(".custom-select").removeClass("active");
            } else {
                $dropdown.show();
                $(this).closest(".custom-select").addClass("active");
            }
        });

        $(document).on("click", function (e) {
            if (!$(e.target).closest(".custom-select").length) {
                $(".dropdown-box").hide();
                $(".custom-select").removeClass("active");
            }
        });

        $(".custom-select .dropdown-box").on("click", function (e) {
            e.stopPropagation();
        });

        $(".custom-select").each(function () {
            let $customSelect = $(this);
            if ($customSelect.find(".bys-booking-count").length > 1) {
                let adultsVal = parseInt($customSelect.find(".bys-booking-count").first().find(".qty").val()) || 0;
                let childrenVal = parseInt($customSelect.find(".bys-booking-count").last().find(".qty").val()) || 0;

                let adultsText = adultsVal + (adultsVal === 1 ? " Adult" : " Adults");
                let childrenText = childrenVal + (childrenVal === 1 ? " Child" : " Children");

                $customSelect.find(".rooms-text").text(adultsText + ", " + childrenText);
            } else {
                let roomsVal = parseInt($customSelect.find(".bys-booking-count").find(".qty").val()) || 1;
                let roomsText = roomsVal + " " + (roomsVal === 1 ? "<?php echo esc_js(__('Room', 'book-your-stay')); ?>" : "<?php echo esc_js(__('Rooms', 'book-your-stay')); ?>");
                $customSelect.find(".rooms-text").text(roomsText);
            }
        });

        // Increment - Handle both Rooms and Guests
        $(".custom-select .inc").on("click", function (e) {
            e.stopPropagation();
            let $countBox = $(this).closest(".bys-booking-count");
            let $customSelect = $(this).closest(".custom-select");
            let $input = $countBox.find(".qty");
            let val = parseInt($input.val()) || 0;
            let maxVal = parseInt($input.data("max")) || 10; // Get max from data attribute (set by PHP loop)

            if (val < maxVal) val++;

            $input.val(val);

            if ($customSelect.find(".bys-booking-count").length > 1) {
                let adultsVal = parseInt($customSelect.find(".bys-booking-count").first().find(".qty").val()) || 0;
                let childrenVal = parseInt($customSelect.find(".bys-booking-count").last().find(".qty").val()) || 0;

                let adultsText = adultsVal + (adultsVal === 1 ? " Adult" : " Adults");
                let childrenText = childrenVal + (childrenVal === 1 ? " Child" : " Children");

                $customSelect.find(".rooms-text").text(adultsText + ", " + childrenText);
                $($customSelect.data("target")).val(JSON.stringify({
                    adults: adultsVal,
                    children: childrenVal
                }));
            } else {
                let roomsText = val + " " + (val === 1 ? "<?php echo esc_js(__('Room', 'book-your-stay')); ?>" : "<?php echo esc_js(__('Rooms', 'book-your-stay')); ?>");
                $customSelect.find(".rooms-text").text(roomsText);
                $($customSelect.data("target")).val(val);
            }
        });

        // Decrement - Handle both Rooms and Guests
        $(".custom-select .dec").on("click", function (e) {
            e.stopPropagation();
            let $countBox = $(this).closest(".bys-booking-count");
            let $customSelect = $(this).closest(".custom-select");
            let $input = $countBox.find(".qty");
            let val = parseInt($input.val()) || 0;

            // Check if this is Guests field (has multiple .bys-booking-count)
            if ($customSelect.find(".bys-booking-count").length > 1) {
                // This is Guests field
                let minVal = parseInt($input.data("min")) || 0; // Get min from data attribute (set by PHP loop)

                if (val > minVal) val--;
                $input.val(val);

                let adultsVal = parseInt($customSelect.find(".bys-booking-count").first().find(".qty").val()) || 0;
                let childrenVal = parseInt($customSelect.find(".bys-booking-count").last().find(".qty").val()) || 0;

                let adultsText = adultsVal + (adultsVal === 1 ? " Adult" : " Adults");
                let childrenText = childrenVal + (childrenVal === 1 ? " Child" : " Children");

                $customSelect.find(".rooms-text").text(adultsText + ", " + childrenText);

                // Update hidden input with JSON format
                $($customSelect.data("target")).val(JSON.stringify({
                    adults: adultsVal,
                    children: childrenVal
                }));
            } else {
                // This is Rooms field
                let minVal = parseInt($input.data("min")) || 1; // Get min from data attribute (set by PHP loop)
                let maxVal = parseInt($input.data("max")) || 5; // Get max from data attribute (set by PHP loop)

                if (val > minVal) val--;
                if (val < minVal) val = minVal;

                $input.val(val);
                let roomsText = val + " " + (val === 1 ? "<?php echo esc_js(__('Room', 'book-your-stay')); ?>" : "<?php echo esc_js(__('Rooms', 'book-your-stay')); ?>");
                $customSelect.find(".rooms-text").text(roomsText);
                $($customSelect.data("target")).val(val);
            }
        });

        // Header click: toggle booking form
        function toggleBookingForm() {
            var $header = $(this);
            var $form = $header.next(".bys-booking-form");
            $form.slideToggle(300);
            $header.attr("aria-expanded", $form.is(":visible"));
        }
        $(".bys-booking-widget .bys-header-toggle").on("click", function (e) {
            e.preventDefault();
            e.stopPropagation();
            toggleBookingForm.call(this);
        }).on("keydown", function (e) {
            if (e.key === "Enter" || e.key === " ") {
                e.preventDefault();
                toggleBookingForm.call(this);
            }
        });

        // Mobile Accordion Toggle
        $(".bys-mobile-toggle-btn").on("click", function (e) {
            e.preventDefault();
            e.stopPropagation();

            var $btn = $(this);
            var $wrapper = $btn.closest(".bys-booking-widget-wrapper");
            var $widget = $btn.next(".bys-booking-widget");
            var $chevron = $btn.find(".bys-mobile-toggle-chevron");
            var isExpanded = $btn.attr("aria-expanded") === "true";

            if (isExpanded) {
                $widget.slideUp(300, function () {
                    $wrapper.removeClass("active");
                });
                $btn.attr("aria-expanded", "false");
            } else {
                $wrapper.addClass("active");
                $widget.slideDown(300);
                $btn.attr("aria-expanded", "true");
            }
        });

    });
</script>