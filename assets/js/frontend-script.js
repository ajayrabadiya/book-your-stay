/**
 * Frontend JavaScript for Book Your Stay
 * Custom Calendar Implementation
 */

(function($) {
    'use strict';

    // Keep track of all calendar instances so only one can be open at a time
    var allCalendars = [];

    // Custom Calendar Class
    function CustomCalendar(input, options) {
        this.input = $(input);
        this.options = $.extend({
            minDate: null,
            defaultDate: null,
            onSelect: null,
            onClose: null
        }, options || {});
        
        this.currentDate = this.options.defaultDate ? new Date(this.options.defaultDate) : new Date();
        this.selectedDate = this.options.defaultDate ? new Date(this.options.defaultDate) : null;
        this.minDate = this.options.minDate ? new Date(this.options.minDate) : new Date();
        this.minDate.setHours(0, 0, 0, 0);
        
        this.calendar = null;
        this.isOpen = false;
        
        allCalendars.push(this);
        this.init();
    }
    
    CustomCalendar.prototype = {
        init: function() {
            var self = this;
            var $wrapper = this.input.closest('.bys-date-picker-wrapper');
            var $field = this.input.closest('.bys-date-field');
            var $icon = $field.find('.bys-date-icon');
            
            // Reuse existing calendar div if present (prevents duplicate headers when only one should show)
            var $existing = $field.find('.bys-custom-calendar');
            if ($existing.length) {
                this.calendar = $existing.first();
            } else {
                this.calendar = $('<div class="bys-custom-calendar"></div>');
                $field.append(this.calendar);
            }
            
            // Store references
            this.$field = $field;
            this.$icon = $icon;
            
            // Build calendar
            this.render();

            // Single click handler on wrapper only: open/close this calendar (one place = one toggle, no double fire)
            $wrapper.on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.toggle();
            });
            
            // Close on outside click (any click not inside this date field)
            $(document).on('click.calendar-' + this.input.attr('id'), function(e) {
                if (!$(e.target).closest(self.$field[0]).length && self.isOpen) {
                    self.close();
                }
            });
        },
        
        render: function() {
            var self = this;
            var year = this.currentDate.getFullYear();
            var month = this.currentDate.getMonth();
            
            var monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                            'July', 'August', 'September', 'October', 'November', 'December'];
            var dayNames = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'];
            
            var html = '<div class="bys-calendar-header">';
            html += '<div class="bys-calendar-nav">';
            html += '<button type="button" class="bys-calendar-prev" aria-label="Previous month">';
            html += '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M15 18L9 12L15 6" stroke="#D3AA74" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            html += '</button>';
            html += '<div class="bys-calendar-month-year">';
            html += '<select class="bys-calendar-month-select">';
            for (var i = 0; i < 12; i++) {
                html += '<option value="' + i + '"' + (i === month ? ' selected' : '') + '>' + monthNames[i] + '</option>';
            }
            html += '</select>';
            html += '<select class="bys-calendar-year-select">';
            var currentYear = new Date().getFullYear();
            for (var y = currentYear; y <= currentYear + 10; y++) {
                html += '<option value="' + y + '"' + (y === year ? ' selected' : '') + '>' + y + '</option>';
            }
            html += '</select>';
            html += '</div>';
            html += '<button type="button" class="bys-calendar-next" aria-label="Next month">';
            html += '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M9 18L15 12L9 6" stroke="#D3AA74" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            html += '</button>';
            html += '</div>';
            html += '</div>';
            
            html += '<div class="bys-calendar-weekdays">';
            for (var d = 0; d < 7; d++) {
                html += '<div class="bys-calendar-weekday">' + dayNames[d] + '</div>';
            }
            html += '</div>';
            
            html += '<div class="bys-calendar-days">';
            
            // Get first day of month and number of days
            var firstDay = new Date(year, month, 1).getDay();
            var daysInMonth = new Date(year, month + 1, 0).getDate();
            var daysInPrevMonth = new Date(year, month, 0).getDate();
            
            // Previous month days
            for (var i = firstDay - 1; i >= 0; i--) {
                var day = daysInPrevMonth - i;
                var date = new Date(year, month - 1, day);
                var isDisabled = date < this.minDate;
                html += '<div class="bys-calendar-day prev-month' + (isDisabled ? ' disabled' : '') + '" data-date="' + this.formatDate(date) + '">' + day + '</div>';
            }
            
            // Current month days
            for (var day = 1; day <= daysInMonth; day++) {
                var date = new Date(year, month, day);
                var isDisabled = date < this.minDate;
                var isSelected = this.selectedDate && this.isSameDay(date, this.selectedDate);
                var isToday = this.isSameDay(date, new Date());
                
                html += '<div class="bys-calendar-day' + 
                        (isDisabled ? ' disabled' : '') + 
                        (isSelected ? ' selected' : '') + 
                        (isToday ? ' today' : '') + 
                        '" data-date="' + this.formatDate(date) + '">' + day + '</div>';
            }
            
            // Next month days: only enough to complete the last row (so only one month is visually dominant)
            var minCellsForMonth = firstDay + daysInMonth;
            var totalCells = Math.ceil(minCellsForMonth / 7) * 7;
            var nextMonthDays = Math.max(0, totalCells - minCellsForMonth);
            for (var day = 1; day <= nextMonthDays; day++) {
                var date = new Date(year, month + 1, day);
                html += '<div class="bys-calendar-day next-month" data-date="' + this.formatDate(date) + '">' + day + '</div>';
            }
            
            html += '</div>';
            
            html += '<div class="bys-calendar-footer">';
            html += '<button type="button" class="bys-calendar-btn bys-calendar-cancel">Cancel</button>';
            html += '<button type="button" class="bys-calendar-btn bys-calendar-confirm">Set Date</button>';
            html += '</div>';
            
            this.calendar.html(html);
            
            // Bind events
            this.bindEvents();
        },
        
        bindEvents: function() {
            var self = this;

            // Navigate to previous/next month: use 1st of month to avoid date rollover (e.g. Jan 31 -> Feb = Mar 2)
            function goMonth(direction) {
                var y = self.currentDate.getFullYear();
                var m = self.currentDate.getMonth();
                if (direction === 'prev') {
                    self.currentDate = new Date(y, m - 1, 1);
                } else {
                    self.currentDate = new Date(y, m + 1, 1);
                }
                self.render();
            }

            // Month/Year selectors - use event delegation
            this.calendar.off('change', '.bys-calendar-month-select, .bys-calendar-year-select')
                .on('change', '.bys-calendar-month-select, .bys-calendar-year-select', function() {
                    var month = parseInt(self.calendar.find('.bys-calendar-month-select').val());
                    var year = parseInt(self.calendar.find('.bys-calendar-year-select').val());
                    self.currentDate = new Date(year, month, 1);
                    self.render();
                });
            
            // Navigation buttons
            this.calendar.off('click', '.bys-calendar-prev, .bys-calendar-next')
                .on('click', '.bys-calendar-prev', function(e) {
                    e.stopPropagation();
                    e.preventDefault();
                    goMonth('prev');
                })
                .on('click', '.bys-calendar-next', function(e) {
                    e.stopPropagation();
                    e.preventDefault();
                    goMonth('next');
                });
            
            // Day selection - use event delegation to handle re-renders
            this.calendar.off('click', '.bys-calendar-day').on('click', '.bys-calendar-day:not(.disabled)', function(e) {
                e.stopPropagation();
                e.preventDefault();
                var dateStr = $(this).data('date');
                if (dateStr) {
                    self.selectedDate = new Date(dateStr);
                    self.render();
                }
            });
            
            // Footer buttons - use event delegation
            this.calendar.off('click', '.bys-calendar-cancel, .bys-calendar-confirm')
                .on('click', '.bys-calendar-cancel', function(e) {
                    e.stopPropagation();
                    e.preventDefault();
                    self.close();
                })
                .on('click', '.bys-calendar-confirm', function(e) {
                    e.stopPropagation();
                    e.preventDefault();
                    if (self.selectedDate) {
                        var dateStr = self.formatDate(self.selectedDate);
                        self.input.val(dateStr);
                        // Trigger change event
                        self.input.trigger('change');
                        if (self.options.onSelect) {
                            self.options.onSelect(self.selectedDate, dateStr);
                        }
                    }
                    self.close();
                });
        },
        
        toggle: function() {
            if (this.isOpen) {
                this.close();
            } else {
                this.open();
            }
        },
        
        open: function() {
            // Close any other open calendar so only one is open at a time
            for (var i = 0; i < allCalendars.length; i++) {
                if (allCalendars[i] !== this && allCalendars[i].isOpen) {
                    allCalendars[i].close();
                }
            }
            // Ensure no other calendar in the widget is visible (remove .open from all others)
            this.$field.closest('.bys-booking-widget-wrapper').find('.bys-custom-calendar').not(this.calendar).removeClass('open');
            this.$field.closest('.bys-booking-widget-wrapper').find('.bys-date-field').not(this.$field).removeClass('calendar-open');
            this.isOpen = true;
            this.calendar.addClass('open');
            this.$field.addClass('calendar-open');
            // Update month/year selects to current view
            var month = this.currentDate.getMonth();
            var year = this.currentDate.getFullYear();
            this.calendar.find('.bys-calendar-month-select').val(month);
            this.calendar.find('.bys-calendar-year-select').val(year);
        },
        
        close: function() {
            this.isOpen = false;
            this.calendar.removeClass('open');
            this.$field.removeClass('calendar-open');
            if (this.options.onClose) {
                this.options.onClose();
            }
        },
        
        formatDate: function(date) {
            var year = date.getFullYear();
            var month = String(date.getMonth() + 1).padStart(2, '0');
            var day = String(date.getDate()).padStart(2, '0');
            return year + '-' + month + '-' + day;
        },
        
        isSameDay: function(date1, date2) {
            return date1.getFullYear() === date2.getFullYear() &&
                   date1.getMonth() === date2.getMonth() &&
                   date1.getDate() === date2.getDate();
        },
        
        setDate: function(date) {
            this.selectedDate = new Date(date);
            this.currentDate = new Date(date);
            this.input.val(this.formatDate(date));
            this.render();
        },
        
        setMinDate: function(date) {
            this.minDate = new Date(date);
            this.minDate.setHours(0, 0, 0, 0);
            this.render();
        }
    };
    
    // Initialize calendars
    $(document).ready(function() {
        var $checkin = $('#bys-checkin');
        var $checkout = $('#bys-checkout');
        
        if ($checkin.length === 0 || $checkout.length === 0) {
            return;
        }
        
        var today = new Date();
        today.setHours(0, 0, 0, 0);
        var minDate = today;
        
        // Initialize Check-In calendar
        var checkinCalendar = new CustomCalendar($checkin[0], {
            minDate: minDate,
            defaultDate: $checkin.val() || today,
            onSelect: function(date, dateStr) {
                // Update checkout minimum date
                var minCheckout = new Date(date);
                    minCheckout.setDate(minCheckout.getDate() + 1);
                checkoutCalendar.setMinDate(minCheckout);
                
                // Auto-update checkout if needed
                if (checkoutCalendar.selectedDate && checkoutCalendar.selectedDate <= date) {
                    checkoutCalendar.setDate(minCheckout);
                }
            }
        });
        
        // Initialize Check-Out calendar
        var checkoutMinDate = new Date($checkin.val() || today);
        checkoutMinDate.setDate(checkoutMinDate.getDate() + 1);
        
        var checkoutCalendar = new CustomCalendar($checkout[0], {
            minDate: checkoutMinDate,
            defaultDate: $checkout.val() || (function() {
                var d = new Date($checkin.val() || today);
                d.setDate(d.getDate() + 2);
                return d;
            })(),
            onSelect: function(date, dateStr) {
                // Ensure checkout is after checkin
                var checkinDate = new Date($checkin.val());
                if (date <= checkinDate) {
                    var minCheckout = new Date(checkinDate);
                    minCheckout.setDate(minCheckout.getDate() + 1);
                    checkoutCalendar.setDate(minCheckout);
                }
            }
        });
        
        // Store calendar instances
        $checkin.data('calendar', checkinCalendar);
        $checkout.data('calendar', checkoutCalendar);
        
        // Update checkout when checkin changes
        $checkin.on('change', function() {
            var checkinDate = new Date($(this).val());
            var minCheckout = new Date(checkinDate);
            minCheckout.setDate(minCheckout.getDate() + 1);
            checkoutCalendar.setMinDate(minCheckout);
            
            if (checkoutCalendar.selectedDate && checkoutCalendar.selectedDate <= checkinDate) {
                checkoutCalendar.setDate(minCheckout);
            }
        });
    });
    
})(jQuery);
