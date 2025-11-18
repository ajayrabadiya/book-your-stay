/**
 * Frontend JavaScript for Book Your Stay
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Initialize date pickers with calendar widget
        if ($('#bys-checkin').length && $('#bys-checkout').length) {
            initDatePickers();
        }
    });
    
    /**
     * Initialize date pickers with calendar widget
     */
    function initDatePickers() {
        var $checkin = $('#bys-checkin');
        var $checkout = $('#bys-checkout');
        var today = new Date();
        today.setHours(0, 0, 0, 0);
        
        // Set minimum date to today
        var minDate = today.toISOString().split('T')[0];
        $checkin.attr('min', minDate);
        
        // Update checkout minimum when checkin changes
        $checkin.on('change', function() {
            var checkinDate = new Date($(this).val());
            if (checkinDate) {
                var minCheckout = new Date(checkinDate);
                minCheckout.setDate(minCheckout.getDate() + 1);
                $checkout.attr('min', minCheckout.toISOString().split('T')[0]);
                
                // Auto-update checkout if it's before new minimum
                var checkoutDate = new Date($checkout.val());
                if (checkoutDate <= checkinDate) {
                    $checkout.val(minCheckout.toISOString().split('T')[0]);
                }
            }
        });
        
        // Add calendar icon click handlers
        $checkin.on('focus', function() {
            $(this).attr('type', 'date');
        });
        
        $checkout.on('focus', function() {
            $(this).attr('type', 'date');
        });
    }
    
})(jQuery);

