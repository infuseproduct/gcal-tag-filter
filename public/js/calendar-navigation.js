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

            let newDate = new Date(currentDate);

            if (period === 'week') {
                // Move by 7 days
                newDate.setDate(newDate.getDate() + (7 * direction));
            } else if (period === 'month') {
                // Move by 1 month
                newDate.setMonth(newDate.getMonth() + direction);
            } else if (period === 'future') {
                // Move by 1 year
                newDate.setFullYear(newDate.getFullYear() + direction);
            } else {
                return;
            }

            // Store new date
            wrapper.dataset.currentDate = newDate.toISOString();

            // Update title
            this.updateTitle(wrapper, newDate);

            // Show loading state
            wrapper.classList.add('loading');

            // Fetch events for the new month and re-render
            this.fetchAndRenderMonth(wrapper, newDate);
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

            // Show loading
            wrapper.classList.add('loading');

            // Update URL parameter and reload
            const url = new URL(window.location);
            url.searchParams.set('gcal_view', newView);

            // Reload with new view parameter
            window.location.href = url.toString();
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
         * Fetch events for a specific month and render
         *
         * @param {HTMLElement} wrapper - Calendar wrapper
         * @param {Date} date - Date to fetch events for
         */
        fetchAndRenderMonth: function(wrapper, date) {
            const year = date.getFullYear();
            const month = date.getMonth() + 1; // JavaScript months are 0-indexed

            console.log(`Fetching events for ${year}-${month}`);

            // Prepare AJAX request
            const formData = new FormData();
            formData.append('action', 'gcal_fetch_events');
            formData.append('nonce', gcalData.nonce);
            formData.append('year', year);
            formData.append('month', month);

            fetch(gcalData.ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                wrapper.classList.remove('loading');

                if (data.success) {
                    console.log(`Received ${data.data.count} events for ${year}-${month}:`, data.data.events);

                    // Update the wrapper's events data
                    wrapper.dataset.events = JSON.stringify(data.data.events);

                    // Re-render the calendar grid
                    this.renderCalendarGrid(wrapper, date);
                } else {
                    console.error('Failed to fetch events:', data.data.message);
                }
            })
            .catch(error => {
                wrapper.classList.remove('loading');
                console.error('AJAX error:', error);
            });
        },

        /**
         * Re-render calendar grid with new date
         *
         * @param {HTMLElement} wrapper - Calendar wrapper
         * @param {Date} date - Date to render
         */
        renderCalendarGrid: function(wrapper, date) {
            const period = wrapper.dataset.period;
            const gridContainer = wrapper.querySelector('.gcal-calendar-grid');

            if (!gridContainer) return;

            // Get events data
            const eventsJson = wrapper.dataset.events;
            let events = [];

            try {
                events = JSON.parse(eventsJson);
                // Convert object to array if needed
                if (!Array.isArray(events) && typeof events === 'object' && events !== null) {
                    events = Object.values(events);
                }
            } catch (e) {
                console.error('Failed to parse events:', e);
                return;
            }

            // Re-render based on period type
            if (period === 'month') {
                this.renderMonthGrid(gridContainer, date, events);
            } else if (period === 'week') {
                this.renderWeekGrid(gridContainer, date, events);
            } else if (period === 'future') {
                this.renderYearGrid(gridContainer, date, events);
            }

            // Apply contrast colors to newly rendered events
            if (window.GCalContrastHandler) {
                window.GCalContrastHandler.applyContrastColors();
            }
        },

        /**
         * Render month view grid
         */
        renderMonthGrid: function(container, date, events) {
            const year = date.getFullYear();
            const month = date.getMonth();

            console.log(`Rendering month grid for ${year}-${month + 1} with ${events.length} events`);

            // Get first and last day of month
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);

            // Get day of week for first day (0 = Sunday, need to convert to Monday = 0)
            let firstDayOfWeek = firstDay.getDay();
            firstDayOfWeek = firstDayOfWeek === 0 ? 6 : firstDayOfWeek - 1; // Convert to Monday = 0

            const daysInMonth = lastDay.getDate();

            // Group events by date
            const eventsByDate = {};
            events.forEach(event => {
                const eventDate = new Date(event.start);
                const dateKey = `${eventDate.getFullYear()}-${String(eventDate.getMonth() + 1).padStart(2, '0')}-${String(eventDate.getDate()).padStart(2, '0')}`;
                if (!eventsByDate[dateKey]) {
                    eventsByDate[dateKey] = [];
                }
                eventsByDate[dateKey].push(event);
            });

            console.log('Events grouped by date:', eventsByDate);

            // Build calendar HTML matching PHP structure exactly
            let html = '<div class="gcal-weekday-headers">';
            const weekdays = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
            weekdays.forEach(day => {
                html += `<div class="gcal-weekday">${day}</div>`;
            });
            html += '</div><div class="gcal-days-grid">';

            // Add empty cells for days before month starts
            for (let i = 0; i < firstDayOfWeek; i++) {
                html += '<div class="gcal-day gcal-day-other-month"></div>';
            }

            // Add days of month
            for (let day = 1; day <= daysInMonth; day++) {
                const dateKey = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                const dayEvents = eventsByDate[dateKey] || [];
                const isToday = this.isToday(new Date(year, month, day));

                html += `<div class="gcal-day ${isToday ? 'gcal-day-today' : ''} ${dayEvents.length > 0 ? 'gcal-day-has-events' : ''}" data-date="${dateKey}">`;
                html += `<div class="gcal-day-number">${day}</div>`;
                html += '<div class="gcal-day-events">';

                // Render events for this day
                dayEvents.forEach(event => {
                    html += this.renderEventHTML(event);
                });

                html += '</div></div>';
            }

            html += '</div>';
            container.innerHTML = html;
        },

        /**
         * Render week view grid
         */
        renderWeekGrid: function(container, date, events) {
            // Get Monday of the week
            const dayOfWeek = date.getDay();
            const monday = new Date(date);
            const diff = dayOfWeek === 0 ? -6 : 1 - dayOfWeek; // Adjust for Monday start
            monday.setDate(date.getDate() + diff);

            const frenchDays = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

            // Group events by date
            const eventsByDate = {};
            events.forEach(event => {
                const eventDate = new Date(event.start);
                const dateKey = `${eventDate.getFullYear()}-${String(eventDate.getMonth() + 1).padStart(2, '0')}-${String(eventDate.getDate()).padStart(2, '0')}`;
                if (!eventsByDate[dateKey]) {
                    eventsByDate[dateKey] = [];
                }
                eventsByDate[dateKey].push(event);
            });

            let html = '<div class="gcal-week-view">';

            for (let i = 0; i < 7; i++) {
                const currentDay = new Date(monday);
                currentDay.setDate(monday.getDate() + i);
                const dateKey = `${currentDay.getFullYear()}-${String(currentDay.getMonth() + 1).padStart(2, '0')}-${String(currentDay.getDate()).padStart(2, '0')}`;
                const dayEvents = eventsByDate[dateKey] || [];
                const isToday = this.isToday(currentDay);

                html += `<div class="gcal-week-day ${isToday ? 'gcal-day-today' : ''}" data-date="${dateKey}">`;
                html += '<div class="gcal-week-day-header">';
                html += `<div class="gcal-week-day-name">${frenchDays[i]}</div>`;
                html += `<div class="gcal-week-day-number">${currentDay.getDate()}</div>`;
                html += '</div><div class="gcal-week-day-events">';

                if (dayEvents.length > 0) {
                    dayEvents.forEach(event => {
                        html += this.renderEventHTML(event);
                    });
                } else {
                    html += '<div class="gcal-no-events">Aucun événement</div>';
                }

                html += '</div></div>';
            }

            html += '</div>';
            container.innerHTML = html;
        },

        /**
         * Render year view grid
         */
        renderYearGrid: function(container, date, events) {
            // Year view shows 12 months - implementation similar to PHP version
            // For simplicity, just reload the page for year view
            window.location.reload();
        },

        /**
         * Render event HTML
         */
        renderEventHTML: function(event) {
            let categoryColor = '#2271b1';
            if (event.tags && event.tags.length > 0) {
                // You might need to fetch category colors - for now use default
                categoryColor = this.getCategoryColor(event.tags[0]);
            }

            const eventStart = new Date(event.start);
            const eventEnd = new Date(event.end);

            let timeDisplay = '';
            if (!event.isAllDay) {
                const startHours = eventStart.getHours();
                const startMins = eventStart.getMinutes();
                const endHours = eventEnd.getHours();
                const endMins = eventEnd.getMinutes();

                const formatTime = (hours, mins) => {
                    if (mins === 0) {
                        return `${hours}h`;
                    }
                    return `${hours}h${String(mins).padStart(2, '0')}`;
                };

                timeDisplay = `${formatTime(startHours, startMins)} - ${formatTime(endHours, endMins)}`;
            }

            return `<div class="gcal-event-item" data-event-id="${event.id}" style="background-color: ${categoryColor};" role="button" tabindex="0">
                ${timeDisplay ? `<span class="gcal-event-time">${timeDisplay}</span>` : ''}
                <span class="gcal-event-title">${this.escapeHtml(event.title)}</span>
            </div>`;
        },

        /**
         * Get category color (simplified - would need actual category data)
         */
        getCategoryColor: function(tagId) {
            // Default colors - in a full implementation, fetch from server
            const colors = {
                'MESSE-DALTON-SCHOOL': '#81D742',
                'MESSE-MUI-WO': '#DD9933',
                'EDC-GROUPE-1': '#4285F4'
            };
            return colors[tagId] || '#2271b1';
        },

        /**
         * Check if date is today
         */
        isToday: function(date) {
            const today = new Date();
            return date.getDate() === today.getDate() &&
                   date.getMonth() === today.getMonth() &&
                   date.getFullYear() === today.getFullYear();
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
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
