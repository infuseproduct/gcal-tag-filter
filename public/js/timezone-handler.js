/**
 * Timezone Handler
 *
 * Handles timezone detection and conversion for event times.
 */

(function(window) {
    'use strict';

    /**
     * GCal Timezone Handler
     */
    const GCalTimezone = {
        /**
         * User's detected timezone
         */
        userTimezone: null,

        /**
         * Initialize timezone detection
         */
        init: function() {
            this.userTimezone = this.detectTimezone();
        },

        /**
         * Detect user's timezone using Intl API
         *
         * @returns {string} IANA timezone name
         */
        detectTimezone: function() {
            try {
                return Intl.DateTimeFormat().resolvedOptions().timeZone;
            } catch (e) {
                console.warn('Could not detect timezone, using UTC', e);
                return 'UTC';
            }
        },

        /**
         * Convert ISO datetime string to user's timezone
         *
         * @param {string} isoString - ISO 8601 datetime string
         * @returns {Date} Date object in user's timezone
         */
        parseDateTime: function(isoString) {
            return new Date(isoString);
        },

        /**
         * Format date for display
         *
         * @param {Date} date - Date object
         * @param {Object} options - Intl.DateTimeFormat options
         * @returns {string} Formatted date string
         */
        formatDate: function(date, options = {}) {
            const defaultOptions = {
                timeZone: this.userTimezone,
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            };

            const formatOptions = Object.assign({}, defaultOptions, options);

            try {
                return new Intl.DateTimeFormat('fr-FR', formatOptions).format(date);
            } catch (e) {
                console.warn('Error formatting date', e);
                return date.toLocaleDateString('fr-FR');
            }
        },

        /**
         * Format time for display
         *
         * @param {Date} date - Date object
         * @param {Object} options - Intl.DateTimeFormat options
         * @returns {string} Formatted time string
         */
        formatTime: function(date, options = {}) {
            // Use WordPress time format setting (12-hour vs 24-hour)
            const is24Hour = (typeof gcalData !== 'undefined' && gcalData.settings && gcalData.settings.is24Hour !== undefined)
                ? gcalData.settings.is24Hour
                : false; // Default to 12-hour if setting not available

            const defaultOptions = {
                timeZone: this.userTimezone,
                hour: 'numeric',
                minute: '2-digit',
                hour12: !is24Hour // Use WordPress setting for 12/24 hour format
            };

            const formatOptions = Object.assign({}, defaultOptions, options);

            try {
                return new Intl.DateTimeFormat('fr-FR', formatOptions).format(date);
            } catch (e) {
                console.warn('Error formatting time', e);
                return date.toLocaleTimeString('fr-FR');
            }
        },

        /**
         * Format date and time together
         *
         * @param {Date} date - Date object
         * @returns {string} Formatted datetime string
         */
        formatDateTime: function(date) {
            return this.formatDate(date) + ' à ' + this.formatTime(date);
        },

        /**
         * Get short month name
         *
         * @param {Date} date - Date object
         * @returns {string} Short month name (e.g., "Jan")
         */
        getShortMonth: function(date) {
            return this.formatDate(date, { month: 'short' });
        },

        /**
         * Get day of month
         *
         * @param {Date} date - Date object
         * @returns {string} Day of month (e.g., "15")
         */
        getDay: function(date) {
            return this.formatDate(date, { day: 'numeric' });
        },

        /**
         * Get short weekday name
         *
         * @param {Date} date - Date object
         * @returns {string} Short weekday name (e.g., "Mon")
         */
        getShortWeekday: function(date) {
            return this.formatDate(date, { weekday: 'short' });
        },

        /**
         * Check if date is all-day event (no time component)
         *
         * @param {string} dateString - Date string from API
         * @returns {boolean} True if all-day event
         */
        isAllDay: function(dateString) {
            // All-day events are in format YYYY-MM-DD (no time)
            return /^\d{4}-\d{2}-\d{2}$/.test(dateString);
        },

        /**
         * Format date range
         *
         * @param {Date} start - Start date
         * @param {Date} end - End date
         * @param {boolean} isAllDay - Whether it's an all-day event
         * @returns {string} Formatted date range
         */
        formatDateRange: function(start, end, isAllDay = false) {
            if (isAllDay) {
                const startDate = this.formatDate(start);
                const endDate = this.formatDate(end);

                if (startDate === endDate) {
                    return startDate;
                }

                return startDate + ' - ' + endDate;
            }

            const startDateTime = this.formatDateTime(start);
            const endTime = this.formatTime(end);

            // Check if same day
            if (start.toDateString() === end.toDateString()) {
                return startDateTime + ' - ' + endTime;
            }

            const endDateTime = this.formatDateTime(end);
            return startDateTime + ' - ' + endDateTime;
        },

        /**
         * Get timezone abbreviation
         *
         * @param {Date} date - Date object
         * @returns {string} Timezone abbreviation (e.g., "PST", "EST")
         */
        getTimezoneAbbr: function(date) {
            try {
                const formatted = new Intl.DateTimeFormat('fr-FR', {
                    timeZone: this.userTimezone,
                    timeZoneName: 'short'
                }).format(date);

                // Extract timezone abbreviation from formatted string
                const parts = formatted.split(' ');
                return parts[parts.length - 1];
            } catch (e) {
                return '';
            }
        },

        /**
         * Convert event object times to user timezone
         *
         * @param {Object} event - Event object with start/end times
         * @returns {Object} Event with formatted times
         */
        formatEvent: function(event) {
            const startDate = this.parseDateTime(event.start);
            const endDate = this.parseDateTime(event.end);
            const isAllDay = event.isAllDay || this.isAllDay(event.start);

            return {
                ...event,
                startDate: startDate,
                endDate: endDate,
                isAllDay: isAllDay,
                formattedDate: this.formatDate(startDate),
                formattedTime: isAllDay ? null : this.formatTime(startDate),
                formattedDateTime: isAllDay ? this.formatDate(startDate) : this.formatDateTime(startDate),
                formattedRange: this.formatDateRange(startDate, endDate, isAllDay),
                shortMonth: this.getShortMonth(startDate),
                day: this.getDay(startDate),
                shortWeekday: this.getShortWeekday(startDate),
                timezoneAbbr: isAllDay ? null : this.getTimezoneAbbr(startDate)
            };
        },

        /**
         * Format events array
         *
         * @param {Array} events - Array of event objects
         * @returns {Array} Array of events with formatted times
         */
        formatEvents: function(events) {
            return events.map(event => this.formatEvent(event));
        }
    };

    // Initialize on load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            GCalTimezone.init();
        });
    } else {
        GCalTimezone.init();
    }

    // Expose to global scope
    window.GCalTimezone = GCalTimezone;

})(window);
