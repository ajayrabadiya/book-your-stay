/**
 * Frontend JavaScript for Book Your Stay
 * Bootstrap Datepicker with month/year dropdowns (Bootstrap + jQuery)
 */

(function($) {
    'use strict';

    var MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    function injectMonthYearDropdowns($input) {
        var dp = $input.data('datepicker');
        if (!dp) return;
        var $picker = (dp.picker && dp.picker.length) ? dp.picker : (dp.$picker && dp.$picker.length) ? dp.$picker : $('body .datepicker.dropdown-menu:visible').last();
        if (!$picker || !$picker.length) return;
        var $container = $input.closest('.bys-date-field');
        if ($container.length && $picker.length && !$container[0].contains($picker[0])) {
            $container.append($picker);
        }
        var $switch = $picker.find('th.datepicker-switch').length ? $picker.find('th.datepicker-switch') : $picker.find('th').has('.datepicker-switch').first();
        if (!$switch.length) $switch = $picker.find('.datepicker-switch').closest('th');
        if (!$switch.length) return;

        var viewDate = dp.viewDate || (dp.dates && dp.dates[0]) || new Date();
        var year = viewDate.getFullYear();
        var month = viewDate.getMonth();
        var startYear = dp.startDate ? dp.startDate.getFullYear() : new Date().getFullYear();
        var endYear = (dp.endDate && dp.endDate.getFullYear() < startYear + 20) ? dp.endDate.getFullYear() : startYear + 20;

        var monthHtml = '<select class="form-control bys-datepicker-month-select">';
        for (var m = 0; m < 12; m++) {
            monthHtml += '<option value="' + m + '"' + (m === month ? ' selected' : '') + '>' + MONTHS[m] + '</option>';
        }
        monthHtml += '</select>';

        var yearHtml = '<select class="form-control bys-datepicker-year-select">';
        for (var y = startYear; y <= endYear; y++) {
            yearHtml += '<option value="' + y + '"' + (y === year ? ' selected' : '') + '>' + y + '</option>';
        }
        yearHtml += '</select>';

        var $wrap = $('<div class="bys-datepicker-month-year"></div>').html(monthHtml + yearHtml);
        $switch.empty().append($wrap);

        $picker.data('bys-input', $input);

        function setViewFromSelects() {
            var $in = $picker.data('bys-input');
            if (!$in || !$in.length) return;
            var $m = $picker.find('.bys-datepicker-month-select');
            var $y = $picker.find('.bys-datepicker-year-select');
            if (!$m.length || !$y.length) return;
            var m = parseInt($m.val(), 10);
            var y = parseInt($y.val(), 10);
            var cur = $in.datepicker('getDate');
            var d = cur ? cur.getDate() : 1;
            var maxDay = new Date(y, m + 1, 0).getDate();
            var newDate = new Date(y, m, Math.min(d, maxDay));
            var dp2 = $in.data('datepicker');
            if (dp2.startDate && newDate < dp2.startDate) newDate = dp2.startDate;
            $in.datepicker('setDate', newDate);
            setTimeout(function() { injectMonthYearDropdowns($in); }, 0);
        }

        $picker.off('change.bysSelect').on('change.bysSelect', '.bys-datepicker-month-select, .bys-datepicker-year-select', function(e) {
            e.stopPropagation();
            setViewFromSelects();
        });
        $picker.off('click.bysSelect').on('click.bysSelect', '.bys-datepicker-month-select, .bys-datepicker-year-select', function(e) {
            e.stopPropagation();
        });

        $picker.off('click.bysNav').on('click.bysNav', 'th.prev, th.next', function() {
            var $in = $picker.data('bys-input');
            if ($in && $in.length) {
                setTimeout(function() { injectMonthYearDropdowns($in); }, 10);
            }
        });

        /* Always show Cancel and Set Date: re-inject footer every time (plugin may replace picker DOM) */
        $picker.find('.bys-datepicker-footer').remove();
        var $footer = $('<div class="bys-datepicker-footer">' +
            '<button type="button" class="bys-calendar-btn bys-calendar-cancel">Cancel</button>' +
            '<button type="button" class="bys-calendar-btn bys-calendar-confirm">Set Date</button>' +
            '</div>');
        $picker.append($footer);
        $picker.off('click.bysFooter').on('click.bysFooter', '.bys-calendar-cancel', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $in = $picker.data('bys-input');
            if ($in && $in.length) $in.datepicker('hide');
        });
        $picker.on('click.bysFooter', '.bys-calendar-confirm', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $in = $picker.data('bys-input');
            if (!$in || !$in.length) return;
            var d = $in.datepicker('getDate');
            if (d) {
                var y = d.getFullYear();
                var m = String(d.getMonth() + 1).padStart(2, '0');
                var day = String(d.getDate()).padStart(2, '0');
                $in.val(y + '-' + m + '-' + day).trigger('change');
            }
            $in.datepicker('hide');
        });
    }

    $(document).ready(function() {
        var $checkin = $('#bys-checkin');
        var $checkout = $('#bys-checkout');

        if ($checkin.length === 0 || $checkout.length === 0) return;

        var today = new Date();
        var checkinVal = $checkin.val() || (today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0'));
        var checkoutVal = $checkout.val();

        function addDays(ymd, days) {
            var d = new Date(ymd);
            d.setDate(d.getDate() + days);
            var y = d.getFullYear(), m = String(d.getMonth() + 1).padStart(2, '0'), day = String(d.getDate()).padStart(2, '0');
            return y + '-' + m + '-' + day;
        }
        function dayAfter(ymd) { return addDays(ymd, 1); }

        if (!checkoutVal) checkoutVal = addDays(checkinVal, 2);

        $checkin.datepicker({
            format: 'yyyy-mm-dd',
            startDate: today,
            autoclose: false,
            todayHighlight: true,
            orientation: 'auto bottom',
            container: '#bys-date-field-checkin'
        }).datepicker('setDate', checkinVal);

        $checkout.datepicker({
            format: 'yyyy-mm-dd',
            startDate: dayAfter(checkinVal),
            autoclose: false,
            todayHighlight: true,
            orientation: 'auto bottom',
            container: '#bys-date-field-checkout'
        }).datepicker('setDate', checkoutVal);

        $checkin.on('show', function() {
            var $el = $(this);
            setTimeout(function() { injectMonthYearDropdowns($el); }, 0);
        });
        $checkout.on('show', function() {
            var $el = $(this);
            setTimeout(function() { injectMonthYearDropdowns($el); }, 0);
        });

        $checkin.on('change', function() {
            var start = dayAfter($(this).val());
            $checkout.datepicker('setStartDate', start);
            var current = $checkout.datepicker('getDate');
            var startDate = new Date(start);
            if (current && current <= startDate) {
                $checkout.datepicker('setDate', start);
                $checkout.trigger('change');
            }
        });

        $checkout.on('change', function() {
            var cin = new Date($checkin.val());
            var cout = new Date($checkout.val());
            if (cout <= cin) {
                $checkout.datepicker('setDate', dayAfter($checkin.val()));
            }
        });

        $(document).on('click', '.bys-booking-widget-wrapper .bys-date-picker-wrapper', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $input = $(this).find('.bys-date-input');
            if (!$input.length) return;
            if ($(e.target).closest('.datepicker').length) return;
            var $container = $input.closest('.bys-date-field');
            var dp = $input.data('datepicker');
            if (dp && dp.picker && dp.picker.length && $container.length) {
                if (!$container[0].contains(dp.picker[0])) {
                    $container.append(dp.picker);
                }
            }
            $input.datepicker('show');
            setTimeout(function() { injectMonthYearDropdowns($input); }, 20);
        });
    });
})(jQuery);
