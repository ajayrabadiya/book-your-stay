/**
 * Frontend JavaScript for Book Your Stay
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Wait for Flatpickr to be available
        if (typeof flatpickr !== 'undefined') {
            initDatePickers();
        } else {
            // Retry if Flatpickr not loaded yet
            setTimeout(function() {
                if (typeof flatpickr !== 'undefined') {
                    initDatePickers();
                }
            }, 100);
        }
    });
    
    /**
     * Initialize date pickers with Flatpickr calendar widget
     */
    function initDatePickers() {
        var $checkin = $('#bys-checkin');
        var $checkout = $('#bys-checkout');
        
        if ($checkin.length === 0 || $checkout.length === 0) {
            return;
        }
        
        var today = new Date();
        today.setHours(0, 0, 0, 0);
        var minDate = today.toISOString().split('T')[0];
        
        // Function to add custom footer buttons
        function addCustomFooter(instance) {
            var calendar = instance.calendarContainer;
            var footer = document.createElement('div');
            footer.className = 'flatpickr-calendar-footer';
            
            var cancelBtn = document.createElement('button');
            cancelBtn.type = 'button';
            cancelBtn.className = 'flatpickr-cancel-btn';
            cancelBtn.textContent = 'Cancel';
            cancelBtn.onclick = function() {
                instance.close();
            };
            
            var confirmBtn = document.createElement('button');
            confirmBtn.type = 'button';
            confirmBtn.className = 'flatpickr-confirm-btn';
            confirmBtn.textContent = 'Set Date';
            confirmBtn.onclick = function() {
                instance.close();
            };
            
            footer.appendChild(cancelBtn);
            footer.appendChild(confirmBtn);
            calendar.appendChild(footer);
        }
        
        // Initialize Check-In date picker
        var checkinPicker = flatpickr($checkin[0], {
            dateFormat: 'Y-m-d',
            minDate: minDate,
            defaultDate: $checkin.val() || minDate,
            allowInput: false,
            clickOpens: true,
            appendTo: $checkin.closest('.bys-date-field')[0],
            static: false,
            monthSelectorType: 'dropdown',
            animate: true,
            onReady: function(selectedDates, dateStr, instance) {
                addCustomFooter(instance);
            },
            onChange: function(selectedDates, dateStr, instance) {
                // Update checkout minimum date
                if (selectedDates.length > 0) {
                    var checkinDate = new Date(selectedDates[0]);
                    var minCheckout = new Date(checkinDate);
                    minCheckout.setDate(minCheckout.getDate() + 1);
                    
                    checkoutPicker.set('minDate', minCheckout);
                    
                    // Auto-update checkout if it's before new minimum
                    var checkoutDate = checkoutPicker.selectedDates[0];
                    if (checkoutDate && checkoutDate <= checkinDate) {
                        checkoutPicker.setDate(minCheckout);
                    }
                }
            }
        });
        
        // Initialize Check-Out date picker
        var checkoutPicker = flatpickr($checkout[0], {
            dateFormat: 'Y-m-d',
            minDate: $checkin.val() ? (function() {
                var checkinVal = new Date($checkin.val());
                checkinVal.setDate(checkinVal.getDate() + 1);
                return checkinVal.toISOString().split('T')[0];
            })() : minDate,
            defaultDate: $checkout.val() || (function() {
                var checkinVal = new Date($checkin.val() || minDate);
                checkinVal.setDate(checkinVal.getDate() + 2);
                return checkinVal.toISOString().split('T')[0];
            })(),
            allowInput: false,
            clickOpens: true,
            appendTo: $checkout.closest('.bys-date-field')[0],
            static: false,
            monthSelectorType: 'dropdown',
            animate: true,
            onReady: function(selectedDates, dateStr, instance) {
                addCustomFooter(instance);
            }
        });
        
        // Store picker instances for form submission
        $checkin.data('flatpickr', checkinPicker);
        $checkout.data('flatpickr', checkoutPicker);
        
        // Store picker reference on input element for easy access
        $checkin[0]._flatpickr = checkinPicker;
        $checkout[0]._flatpickr = checkoutPicker;
        
        // Add click handlers for wrapper and icon - Check-In
        $checkin.closest('.bys-date-picker-wrapper').on('click', function(e) {
            e.stopPropagation();
            if (!checkinPicker.isOpen) {
                checkinPicker.open();
            }
        });
        
        $checkin.closest('.bys-date-field').find('.bys-date-icon').on('click', function(e) {
            e.stopPropagation();
            if (!checkinPicker.isOpen) {
                checkinPicker.open();
            }
        });
        
        // Add click handlers for wrapper and icon - Check-Out
        $checkout.closest('.bys-date-picker-wrapper').on('click', function(e) {
            e.stopPropagation();
            if (!checkoutPicker.isOpen) {
                checkoutPicker.open();
            }
        });
        
        $checkout.closest('.bys-date-field').find('.bys-date-icon').on('click', function(e) {
            e.stopPropagation();
            if (!checkoutPicker.isOpen) {
                checkoutPicker.open();
            }
        });
    }
    
})(jQuery);

