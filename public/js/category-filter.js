/**
 * Category Filter Handler
 *
 * Handles category filtering for events.
 */

(function(window, document) {
    'use strict';

    /**
     * GCal Category Filter
     */
    const GCalCategoryFilter = {
        /**
         * Initialize category filters
         */
        init: function() {
            this.attachEventListeners();
        },

        /**
         * Attach event listeners for category buttons and display toggle
         */
        attachEventListeners: function() {
            const self = this;

            // Delegate click events for category buttons
            document.addEventListener('click', function(e) {
                const categoryBtn = e.target.closest('.gcal-category-btn');

                if (categoryBtn) {
                    e.preventDefault();
                    const category = categoryBtn.dataset.category;
                    const sidebar = categoryBtn.closest('.gcal-category-sidebar');
                    const instanceId = sidebar ? sidebar.dataset.instance : null;

                    if (instanceId) {
                        self.filterByCategory(category, instanceId);
                    }
                }
            });

            // Delegate click events for display style toggle
            document.addEventListener('click', function(e) {
                const displayBtn = e.target.closest('.gcal-display-btn');

                if (displayBtn) {
                    e.preventDefault();
                    const displayType = displayBtn.dataset.display;

                    if (displayType) {
                        self.switchDisplayStyle(displayType);
                    }
                }
            });
        },

        /**
         * Switch display style (calendar/list)
         *
         * @param {string} displayType - Display type ('calendar' or 'list')
         */
        switchDisplayStyle: function(displayType) {
            // Update URL parameter and reload
            const url = new URL(window.location);
            url.searchParams.set('gcal_display', displayType);

            // Reload page with new display style
            window.location.href = url.toString();
        },

        /**
         * Filter events by category
         *
         * @param {string} category - Category to filter by (empty string for all)
         * @param {string} instanceId - Calendar/list instance ID
         */
        filterByCategory: function(category, instanceId) {
            // Update URL parameter
            const url = new URL(window.location);
            if (category) {
                url.searchParams.set('gcal_category', category);
            } else {
                url.searchParams.delete('gcal_category');
            }

            // Update URL without reload to preserve state
            window.history.pushState({}, '', url);

            // Update active button state
            this.updateActiveButton(category, instanceId);

            // Filter events in the calendar/list
            this.filterEvents(category, instanceId);
        },

        /**
         * Update active button state
         *
         * @param {string} category - Selected category
         * @param {string} instanceId - Instance ID
         */
        updateActiveButton: function(category, instanceId) {
            const sidebar = document.querySelector(`.gcal-category-sidebar[data-instance="${instanceId}"]`);

            if (!sidebar) return;

            // Remove active class from all buttons
            const buttons = sidebar.querySelectorAll('.gcal-category-btn');
            buttons.forEach(btn => btn.classList.remove('active'));

            // Add active class to selected button
            const activeBtn = sidebar.querySelector(`.gcal-category-btn[data-category="${category}"]`);
            if (activeBtn) {
                activeBtn.classList.add('active');
            }
        },

        /**
         * Filter events in calendar/list view
         *
         * @param {string} category - Category to filter by (empty for all)
         * @param {string} instanceId - Instance ID
         */
        filterEvents: function(category, instanceId) {
            const wrapper = document.getElementById(instanceId);

            if (!wrapper) {
                console.warn('Instance not found:', instanceId);
                return;
            }

            // Get all events
            const eventsJson = wrapper.dataset.events;
            if (!eventsJson) {
                console.warn('No events data found for', instanceId);
                return;
            }

            let events;
            try {
                events = JSON.parse(eventsJson);
            } catch (e) {
                console.error('Failed to parse events JSON:', e);
                return;
            }

            // Show loading state
            wrapper.classList.add('filtering');

            // Filter events by category
            const filteredEventIds = this.getFilteredEventIds(events, category);

            // Update DOM visibility
            this.updateEventVisibility(wrapper, filteredEventIds);

            // Remove loading state
            setTimeout(() => {
                wrapper.classList.remove('filtering');
            }, 200);
        },

        /**
         * Get filtered event IDs based on category
         *
         * @param {Array} events - All events
         * @param {string} category - Category to filter by
         * @returns {Array} Array of event IDs that match the filter
         */
        getFilteredEventIds: function(events, category) {
            if (!category) {
                // Show all events
                return events.map(e => e.id);
            }

            // Special handling for "UNTAGGED" category (admin-only)
            if (category.toUpperCase() === 'UNTAGGED') {
                return events
                    .filter(event => !event.tags || event.tags.length === 0)
                    .map(e => e.id);
            }

            // Special handling for "UNKNOWN" category (admin-only) - events with unknown tags
            if (category.toUpperCase() === 'UNKNOWN') {
                return events
                    .filter(event => {
                        // Event has invalid tags but no valid tags
                        return (!event.tags || event.tags.length === 0) &&
                               event.invalidTags && event.invalidTags.length > 0;
                    })
                    .map(e => e.id);
            }

            // Filter by category
            return events
                .filter(event => {
                    if (!event.tags || event.tags.length === 0) {
                        return false;
                    }
                    // Check if event has the selected category
                    return event.tags.some(tag => tag.toUpperCase() === category.toUpperCase());
                })
                .map(e => e.id);
        },

        /**
         * Update visibility of events in DOM
         *
         * @param {HTMLElement} wrapper - Calendar/list wrapper element
         * @param {Array} visibleEventIds - Array of event IDs that should be visible
         */
        updateEventVisibility: function(wrapper, visibleEventIds) {
            // For calendar view - hide event items and days without events
            const eventItems = wrapper.querySelectorAll('.gcal-event-item');
            eventItems.forEach(item => {
                const eventId = item.dataset.eventId;
                if (visibleEventIds.includes(eventId)) {
                    item.classList.remove('filtered-out');
                } else {
                    item.classList.add('filtered-out');
                }
            });

            // Note: We do NOT hide day cells in calendar view to maintain grid structure
            // Days remain visible even when they have no matching events

            // For list view - hide event cards
            const eventCards = wrapper.querySelectorAll('.gcal-list-event-card');
            eventCards.forEach(card => {
                const eventId = card.dataset.eventId;
                if (visibleEventIds.includes(eventId)) {
                    card.classList.remove('filtered-out');
                } else {
                    card.classList.add('filtered-out');
                }
            });

            // Show empty state if no events visible
            this.updateEmptyState(wrapper, visibleEventIds.length === 0);
        },

        /**
         * Update empty state message
         *
         * @param {HTMLElement} wrapper - Wrapper element
         * @param {boolean} isEmpty - Whether to show empty state
         */
        updateEmptyState: function(wrapper, isEmpty) {
            let emptyState = wrapper.querySelector('.gcal-filter-empty');

            if (isEmpty) {
                if (!emptyState) {
                    emptyState = document.createElement('div');
                    emptyState.className = 'gcal-filter-empty';
                    emptyState.innerHTML = '<p>Aucun événement trouvé pour cette catégorie.</p>';

                    const grid = wrapper.querySelector('.gcal-calendar-grid, .gcal-list');
                    if (grid) {
                        grid.appendChild(emptyState);
                    }
                }
                emptyState.style.display = 'block';
            } else {
                if (emptyState) {
                    emptyState.style.display = 'none';
                }
            }
        }
    };

    // Initialize on load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            GCalCategoryFilter.init();
        });
    } else {
        GCalCategoryFilter.init();
    }

    // Expose to global scope
    window.GCalCategoryFilter = GCalCategoryFilter;

})(window, document);
