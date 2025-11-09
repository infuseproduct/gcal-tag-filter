/**
 * Calendar Navigation Handler
 *
 * Handles calendar navigation and event registration.
 */

(function(window, document) {
    'use strict';

    /**
     * GCal Calendar Navigation
     */
    const GCalNavigation = {
        /**
         * Initialize all calendar instances
         */
        init: function() {
            this.initializeCalendars();
            this.registerEventData();
        },

        /**
         * Initialize calendar instances
         */
        initializeCalendars: function() {
            const calendarWrappers = document.querySelectorAll('.gcal-calendar-wrapper');

            calendarWrappers.forEach(wrapper => {
                this.initializeCalendar(wrapper);
            });
        },

        /**
         * Initialize a single calendar instance
         *
         * @param {HTMLElement} wrapper - Calendar wrapper element
         */
        initializeCalendar: function(wrapper) {
            const instanceId = wrapper.id;
            const period = wrapper.dataset.period;

            if (!instanceId) {
                console.warn('Calendar wrapper missing ID');
                return;
            }

            // Set up navigation buttons
            const prevButton = wrapper.querySelector('.gcal-nav-prev');
            const nextButton = wrapper.querySelector('.gcal-nav-next');

            if (prevButton) {
                prevButton.addEventListener('click', () => {
                    this.navigatePeriod(wrapper, -1);
                });
            }

            if (nextButton) {
                nextButton.addEventListener('click', () => {
                    this.navigatePeriod(wrapper, 1);
                });
            }

            // Set initial title
            this.updateTitle(wrapper);

            // Set up view toggle buttons
            const viewButtons = wrapper.querySelectorAll('.gcal-view-btn');
            viewButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const newView = button.dataset.view;
                    this.switchView(wrapper, newView);
                });
            });
        },

        /**
         * Navigate to next/previous period
         *
         * @param {HTMLElement} wrapper - Calendar wrapper
         * @param {number} direction - Direction (-1 for prev, 1 for next)
         */
        navigatePeriod: function(wrapper, direction) {
            const period = wrapper.dataset.period;
            const currentDate = this.getCurrentDate(wrapper);

            let newDate;

            if (period === 'week') {
                // Move by 7 days
                newDate = new Date(currentDate);
                newDate.setDate(newDate.getDate() + (7 * direction));
            } else if (period === 'month') {
                // Move by 1 month
                newDate = new Date(currentDate);
                newDate.setMonth(newDate.getMonth() + direction);
            } else {
                // Future period doesn't navigate
                return;
            }

            // Store new date
            wrapper.dataset.currentDate = newDate.toISOString();

            // Update calendar display
            this.updateCalendar(wrapper, newDate);
        },

        /**
         * Switch calendar view (week/month/future)
         *
         * @param {HTMLElement} wrapper - Calendar wrapper
         * @param {string} newView - New view type (week/month/future)
         */
        switchView: function(wrapper, newView) {
            const currentView = wrapper.dataset.period;

            if (currentView === newView) return;

            // Update dataset
            wrapper.dataset.period = newView;

            // Update active button
            const viewButtons = wrapper.querySelectorAll('.gcal-view-btn');
            viewButtons.forEach(btn => {
                btn.classList.toggle('active', btn.dataset.view === newView);
            });

            // Update grid data attribute
            const grid = wrapper.querySelector('.gcal-calendar-grid');
            if (grid) {
                grid.dataset.currentView = newView;
            }

            // Update title
            this.updateTitle(wrapper);

            // Show loading and reload page to get correct view
            wrapper.classList.add('loading');

            // Reload the page to render the correct view
            // We could implement AJAX loading later
            setTimeout(() => {
                location.reload();
            }, 200);
        },

        /**
         * Get current date for calendar
         *
         * @param {HTMLElement} wrapper - Calendar wrapper
         * @returns {Date} Current date
         */
        getCurrentDate: function(wrapper) {
            const storedDate = wrapper.dataset.currentDate;

            if (storedDate) {
                return new Date(storedDate);
            }

            return new Date();
        },

        /**
         * Update calendar with new date
         *
         * @param {HTMLElement} wrapper - Calendar wrapper
         * @param {Date} date - New date
         */
        updateCalendar: function(wrapper, date) {
            // Show loading state
            wrapper.classList.add('loading');

            // Update title
            this.updateTitle(wrapper, date);

            // In a real implementation, this would fetch new events from the server
            // For now, we'll just update the title and hide loading
            setTimeout(() => {
                wrapper.classList.remove('loading');
            }, 500);
        },

        /**
         * Update calendar title
         *
         * @param {HTMLElement} wrapper - Calendar wrapper
         * @param {Date} date - Optional date (defaults to current)
         */
        updateTitle: function(wrapper, date = null) {
            const titleElement = wrapper.querySelector('.gcal-calendar-title');

            if (!titleElement) return;

            const currentDate = date || this.getCurrentDate(wrapper);
            const period = wrapper.dataset.period;

            let title = '';

            if (period === 'week') {
                // Show week range
                const weekStart = new Date(currentDate);
                const weekEnd = new Date(currentDate);
                weekEnd.setDate(weekEnd.getDate() + 6);

                const formatter = new Intl.DateTimeFormat('fr-FR', {
                    month: 'short',
                    day: 'numeric'
                });

                title = formatter.format(weekStart) + ' - ' + formatter.format(weekEnd);
            } else if (period === 'month') {
                // Show month and year
                const formatter = new Intl.DateTimeFormat('fr-FR', {
                    month: 'long',
                    year: 'numeric'
                });

                title = formatter.format(currentDate);
            } else {
                // Future/year view
                const formatter = new Intl.DateTimeFormat('fr-FR', {
                    year: 'numeric'
                });
                title = formatter.format(currentDate);
            }

            titleElement.textContent = title;
        },

        /**
         * Register event data with modal handler
         */
        registerEventData: function() {
            // Find all calendar instances with event data
            const instances = document.querySelectorAll('[id^="gcal-"]');

            instances.forEach(instance => {
                const instanceId = instance.id;
                const varName = 'gcalEvents_' + instanceId;

                // Check if events data exists in global scope
                if (window[varName]) {
                    const events = window[varName];

                    // Register with modal handler
                    if (window.GCalEventModal) {
                        window.GCalEventModal.registerEvents(instanceId, events);
                    }

                    // Format events with timezone
                    if (window.GCalTimezone) {
                        window[varName] = window.GCalTimezone.formatEvents(events);
                    }
                }
            });
        },

        /**
         * Refresh calendar data (manual refresh)
         *
         * @param {HTMLElement} wrapper - Calendar wrapper
         */
        refreshCalendar: function(wrapper) {
            wrapper.classList.add('loading');

            // In a real implementation, this would fetch fresh data from server
            // For now, just reload the page
            location.reload();
        }
    };

    // Initialize on load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            GCalNavigation.init();
        });
    } else {
        GCalNavigation.init();
    }

    // Expose to global scope
    window.GCalNavigation = GCalNavigation;

})(window, document);
