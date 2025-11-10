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
            const listWrappers = document.querySelectorAll('.gcal-list-wrapper');

            calendarWrappers.forEach(wrapper => {
                this.initializeCalendar(wrapper);
            });

            listWrappers.forEach(wrapper => {
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

            // Initialize date from URL parameters if available
            this.initializeDateFromURL(wrapper, period);

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
         * Initialize calendar date from URL parameters
         *
         * @param {HTMLElement} wrapper - Calendar wrapper
         * @param {string} period - Period type
         */
        initializeDateFromURL: function(wrapper, period) {
            const url = new URL(window.location);
            const yearParam = url.searchParams.get('gcal_year');
            const monthParam = url.searchParams.get('gcal_month');

            // If no URL parameters, use current date
            if (!yearParam) {
                return;
            }

            let date = new Date();

            try {
                const year = parseInt(yearParam, 10);

                if (period === 'year') {
                    date = new Date(year, 0, 1); // January 1st of the specified year
                } else if (period === 'month' && monthParam) {
                    const month = parseInt(monthParam, 10) - 1; // 0-indexed
                    date = new Date(year, month, 1);
                } else if (period === 'week' && monthParam) {
                    const month = parseInt(monthParam, 10) - 1;
                    const weekParam = url.searchParams.get('gcal_week');
                    const week = weekParam ? parseInt(weekParam, 10) : 1;
                    // Approximate the week start date
                    const day = (week - 1) * 7 + 1;
                    date = new Date(year, month, day);
                }

                // Store the date
                wrapper.dataset.currentDate = date.toISOString();

                console.log(`Initialized ${period} view with date from URL:`, date);
            } catch (e) {
                console.error('Failed to parse date from URL:', e);
            }
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
            } else if (period === 'year') {
                // Move by 1 year
                newDate.setFullYear(newDate.getFullYear() + direction);
            } else {
                return;
            }

            // Store new date
            wrapper.dataset.currentDate = newDate.toISOString();

            // Update URL to reflect new date
            this.updateURL(period, newDate);

            // Update title
            this.updateTitle(wrapper, newDate);

            // Show loading state
            wrapper.classList.add('loading');

            // Fetch events for the new period and re-render
            this.fetchAndRenderMonth(wrapper, newDate);
        },

        /**
         * Update URL with current period and date
         *
         * @param {string} period - Period type (week/month/year)
         * @param {Date} date - Current date
         */
        updateURL: function(period, date) {
            const url = new URL(window.location);

            // Always include the view period
            url.searchParams.set('gcal_view', period);

            // Add date parameters based on period
            if (period === 'year') {
                url.searchParams.set('gcal_year', date.getFullYear());
                // Remove month/week params if they exist
                url.searchParams.delete('gcal_month');
                url.searchParams.delete('gcal_week');
            } else if (period === 'month') {
                url.searchParams.set('gcal_year', date.getFullYear());
                url.searchParams.set('gcal_month', date.getMonth() + 1); // 1-indexed for URL
                url.searchParams.delete('gcal_week');
            } else if (period === 'week') {
                // For week, we need to find which week of the month this date belongs to
                // This MUST match the PHP logic exactly:
                // - Week 1 starts on the Monday BEFORE or ON the 1st of the month
                // - Subsequent weeks are calculated from that Monday
                const year = date.getFullYear();
                const month = date.getMonth(); // 0-indexed

                // Get the first day of the month
                const firstDayOfMonth = new Date(year, month, 1);
                const firstDayWeekday = firstDayOfMonth.getDay(); // 0=Sunday, 1=Monday, etc.

                // Convert to ISO weekday (1=Monday, 7=Sunday)
                const firstDayWeekdayISO = firstDayWeekday === 0 ? 7 : firstDayWeekday;

                // Calculate the Monday that starts week 1
                // If the 1st is Monday, week 1 starts on the 1st
                // Otherwise, week 1 starts on the Monday BEFORE the 1st
                const mondayOfWeek1 = new Date(year, month, 1);
                if (firstDayWeekdayISO !== 1) {
                    // Go back to the previous Monday
                    const daysBack = firstDayWeekdayISO - 1;
                    mondayOfWeek1.setDate(1 - daysBack);
                }

                // Calculate week number based on how many weeks from the Monday of week 1
                const daysDiff = Math.floor((date - mondayOfWeek1) / (1000 * 60 * 60 * 24));
                const weekNumber = Math.floor(daysDiff / 7) + 1;

                url.searchParams.set('gcal_year', year);
                url.searchParams.set('gcal_month', month + 1); // 1-indexed for URL
                url.searchParams.set('gcal_week', weekNumber);
            }

            // Update URL without reload
            window.history.pushState({}, '', url);
        },

        /**
         * Switch calendar view (week/month/year)
         *
         * @param {HTMLElement} wrapper - Calendar wrapper
         * @param {string} newView - New view type (week/month/year)
         */
        switchView: function(wrapper, newView) {
            const currentView = wrapper.dataset.period;

            if (currentView === newView) return;

            // Show loading
            wrapper.classList.add('loading');

            // Get current date to preserve when switching views
            const currentDate = this.getCurrentDate(wrapper);

            // Update URL with new view and preserve date
            this.updateURL(newView, currentDate);

            // Reload with new view parameter
            window.location.reload();
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
            const titleElement = wrapper.querySelector('.gcal-calendar-title, .gcal-list-title');

            if (!titleElement) return;

            const currentDate = date || this.getCurrentDate(wrapper);
            const period = wrapper.dataset.period;

            let title = '';

            if (period === 'week') {
                // Show week range (Monday to Sunday)
                const dayOfWeek = currentDate.getDay();
                const monday = new Date(currentDate);
                // Adjust to get Monday (0=Sunday, 1=Monday, etc.)
                const diff = dayOfWeek === 0 ? -6 : 1 - dayOfWeek;
                monday.setDate(currentDate.getDate() + diff);

                const sunday = new Date(monday);
                sunday.setDate(monday.getDate() + 6);

                const formatter = new Intl.DateTimeFormat('fr-FR', {
                    month: 'short',
                    day: 'numeric'
                });

                title = formatter.format(monday) + ' - ' + formatter.format(sunday);
            } else if (period === 'month') {
                // Show month and year
                const formatter = new Intl.DateTimeFormat('fr-FR', {
                    month: 'long',
                    year: 'numeric'
                });

                title = formatter.format(currentDate);
            } else {
                // Year view
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
            const period = wrapper.dataset.period;
            const year = date.getFullYear();
            const month = date.getMonth() + 1; // JavaScript months are 0-indexed

            // Check if there's a category sidebar
            const hasSidebar = wrapper.closest('.gcal-wrapper-with-sidebar.has-sidebar');

            // If sidebar exists, reload the page so PHP can re-render the sidebar
            // with categories that match the new period's events
            if (hasSidebar) {
                this.updateURL(period, date);
                window.location.reload();
                return;
            }

            // For year view, fetch entire year; for month/week, fetch specific month
            if (period === 'year') {
                console.log(`Fetching events for year ${year}`);
            } else {
                console.log(`Fetching events for ${year}-${month}`);
            }

            // Prepare AJAX request
            const formData = new FormData();
            formData.append('action', 'gcal_fetch_events');
            formData.append('nonce', gcalData.nonce);
            formData.append('year', year);

            // Only add month parameter for month/week views
            if (period !== 'year') {
                formData.append('month', month);
            }

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

                    // Re-apply active category filter if one exists
                    const url = new URL(window.location);
                    const activeCategory = url.searchParams.get('gcal_category');
                    if (activeCategory && window.GCalCategoryFilter) {
                        console.log(`Re-applying category filter: ${activeCategory}`);
                        window.GCalCategoryFilter.filterEvents(activeCategory, wrapper.id);
                    }
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
            const listContainer = wrapper.querySelector('.gcal-list');

            // Check if this is a list or calendar view
            if (listContainer) {
                this.renderListView(wrapper, listContainer, date);
                return;
            }

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
            } else if (period === 'year') {
                this.renderYearGrid(gridContainer, date, events);
            }

            // Apply contrast colors to newly rendered events
            if (window.GCalContrast) {
                window.GCalContrast.applyContrastColors();
            }
        },

        /**
         * Render list view - reload page with new URL parameters
         *
         * @param {HTMLElement} wrapper - List wrapper
         * @param {HTMLElement} container - List container
         * @param {Date} date - Current date
         */
        renderListView: function(wrapper, container, date) {
            // For list view, we reload the page to get proper PHP rendering
            // Update URL and reload
            const period = wrapper.dataset.period;
            this.updateURL(period, date);
            window.location.reload();
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
            const weekdays = gcalData.i18n.weekdaysShort;
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

            const frenchDays = gcalData.i18n.weekdaysShort;

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
                    html += `<div class="gcal-no-events">${gcalData.i18n.noEvents}</div>`;
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
            const year = date.getFullYear();
            const frenchMonths = gcalData.i18n.months;

            // Group events by month
            const eventsByMonth = {};
            events.forEach(event => {
                const eventDate = new Date(event.start);
                const monthKey = `${eventDate.getFullYear()}-${String(eventDate.getMonth() + 1).padStart(2, '0')}`;
                if (!eventsByMonth[monthKey]) {
                    eventsByMonth[monthKey] = [];
                }
                eventsByMonth[monthKey].push(event);
            });

            let html = '<div class="gcal-year-view">';

            for (let month = 1; month <= 12; month++) {
                const monthKey = `${year}-${String(month).padStart(2, '0')}`;
                const monthEvents = eventsByMonth[monthKey] || [];
                const monthName = frenchMonths[month - 1];

                html += `<div class="gcal-year-month">`;
                html += `<div class="gcal-year-month-header">`;
                html += `<h4>${monthName}</h4>`;
                html += `<span class="gcal-year-month-count">${monthEvents.length} ${monthEvents.length === 1 ? gcalData.i18n.event : gcalData.i18n.events}</span>`;
                html += `</div>`;
                html += `<div class="gcal-year-month-events">`;

                if (monthEvents.length > 0) {
                    monthEvents.forEach((event, index) => {
                        const eventDate = new Date(event.start);
                        const dayOfMonth = eventDate.getDate();
                        const isHidden = index >= 20;
                        const categoryColor = this.getCategoryColor(event.tags && event.tags.length > 0 ? event.tags[0] : null);

                        html += `<div class="gcal-year-event gcal-event-item ${isHidden ? 'gcal-year-event-hidden' : ''}" data-event-id="${event.id}" role="button" tabindex="0">`;
                        if (event.tags && event.tags.length > 0) {
                            html += `<span class="gcal-year-event-dot" style="background-color: ${categoryColor};"></span>`;
                        }
                        html += `<span class="gcal-year-event-date">${dayOfMonth}</span>`;
                        html += `<span class="gcal-year-event-title">${this.escapeHtml(event.title)}</span>`;
                        html += `</div>`;
                    });

                    if (monthEvents.length > 20) {
                        html += `<button class="gcal-year-more" data-month="${monthKey}">`;
                        html += `<span class="gcal-year-more-text">+${monthEvents.length - 20} de plus</span>`;
                        html += `<span class="gcal-year-less-text" style="display: none;">Voir moins</span>`;
                        html += `</button>`;
                    }
                } else {
                    html += `<div class="gcal-no-events">${gcalData.i18n.noEvents}</div>`;
                }

                html += `</div></div>`;
            }

            html += '</div>';
            container.innerHTML = html;
        },

        /**
         * Render event HTML
         */
        renderEventHTML: function(event) {
            let categoryColor = '#2271b1';
            let titlePrefix = '';

            // Check for invalid tags (unknown tags)
            if (event.invalidTags && event.invalidTags.length > 0 && (!event.tags || event.tags.length === 0)) {
                categoryColor = '#8B0000'; // Dark red for unknown tags
                titlePrefix = '⚠️ ';
            }
            // Check for untagged events
            else if ((!event.tags || event.tags.length === 0) && (!event.invalidTags || event.invalidTags.length === 0)) {
                categoryColor = '#000000'; // Black for untagged
                titlePrefix = '⚠️ ';
            }
            // Normal events with valid tags
            else if (event.tags && event.tags.length > 0) {
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
                <span class="gcal-event-title">${titlePrefix}${this.escapeHtml(event.title)}</span>
            </div>`;
        },

        /**
         * Get category color from global data
         */
        getCategoryColor: function(tagId) {
            // Use colors from localized script data
            if (typeof gcalData !== 'undefined' && gcalData.categories && gcalData.categories[tagId]) {
                return gcalData.categories[tagId];
            }
            // Fallback to default blue
            return '#2271b1';
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
