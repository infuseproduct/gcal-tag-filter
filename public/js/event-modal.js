/**
 * Event Modal Handler
 *
 * Handles opening and displaying event details in a modal.
 */

(function(window, document) {
    'use strict';

    /**
     * GCal Event Modal
     */
    const GCalEventModal = {
        /**
         * Current modal element
         */
        currentModal: null,

        /**
         * Current events data
         */
        eventsData: {},

        /**
         * Initialize modal handlers
         */
        init: function() {
            this.loadEventsFromDOM();
            this.attachEventListeners();
        },

        /**
         * Load events from data-events attributes in the DOM
         */
        loadEventsFromDOM: function() {
            const self = this;

            // Find all calendar and list wrappers with event data
            const wrappers = document.querySelectorAll('.gcal-calendar-wrapper[data-events], .gcal-list-wrapper[data-events]');

            wrappers.forEach(function(wrapper) {
                const instanceId = wrapper.id;
                const eventsJson = wrapper.dataset.events;

                if (!instanceId || !eventsJson) {
                    console.warn('GCal: Wrapper missing ID or events data', wrapper);
                    return;
                }

                try {
                    let events = JSON.parse(eventsJson);

                    // Convert object with numeric keys to array (backward compatibility)
                    if (!Array.isArray(events) && typeof events === 'object' && events !== null) {
                        events = Object.values(events);
                    }

                    if (Array.isArray(events) && events.length > 0) {
                        self.registerEvents(instanceId, events);
                        console.log('GCal: Registered', events.length, 'events for', instanceId);
                    } else {
                        console.warn('GCal: No events found in data attribute for', instanceId);
                    }
                } catch (e) {
                    console.error('GCal: Failed to parse events JSON for', instanceId, e);
                }
            });
        },

        /**
         * Attach event listeners for opening modals
         */
        attachEventListeners: function() {
            const self = this;

            // Delegate click events for event items
            document.addEventListener('click', function(e) {
                const eventItem = e.target.closest('.gcal-event-item, .gcal-list-event-card, .gcal-event-read-more');

                if (eventItem) {
                    e.preventDefault();
                    const eventId = eventItem.dataset.eventId || eventItem.closest('[data-event-id]')?.dataset.eventId;

                    if (eventId) {
                        self.openModal(eventId);
                    }
                }
            });

            // Close modal on overlay click
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('gcal-modal-overlay') ||
                    e.target.classList.contains('gcal-modal-close')) {
                    self.closeModal();
                }
            });

            // Close modal on Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && self.currentModal) {
                    self.closeModal();
                }
            });
        },

        /**
         * Register events data for a calendar instance
         *
         * @param {string} instanceId - Calendar instance ID
         * @param {Array} events - Array of event objects
         */
        registerEvents: function(instanceId, events) {
            this.eventsData[instanceId] = events;
        },

        /**
         * Find event by ID across all instances
         *
         * @param {string} eventId - Event ID
         * @returns {Object|null} Event object or null
         */
        findEvent: function(eventId) {
            for (const instanceId in this.eventsData) {
                const events = this.eventsData[instanceId];
                const event = events.find(e => e.id === eventId);

                if (event) {
                    return event;
                }
            }
            return null;
        },

        /**
         * Open modal with event details
         *
         * @param {string} eventId - Event ID
         */
        openModal: function(eventId) {
            const event = this.findEvent(eventId);

            if (!event) {
                console.warn('Event not found:', eventId);
                return;
            }

            // Format event with timezone
            const formattedEvent = window.GCalTimezone ?
                window.GCalTimezone.formatEvent(event) :
                event;

            // Find modal element - look for any .gcal-modal on the page
            // (All instances share the same modal structure)
            let modal = document.querySelector('.gcal-modal');

            if (!modal) {
                console.warn('Modal element not found');
                return;
            }

            // Populate modal content
            this.populateModal(modal, formattedEvent);

            // Show modal
            modal.style.display = 'flex';
            this.currentModal = modal;

            // Prevent body scroll
            document.body.style.overflow = 'hidden';

            // Focus trap
            this.trapFocus(modal);
        },

        /**
         * Populate modal with event data
         *
         * @param {HTMLElement} modal - Modal element
         * @param {Object} event - Event object
         */
        populateModal: function(modal, event) {
            const modalBody = modal.querySelector('.gcal-modal-body');

            if (!modalBody) {
                console.warn('Modal body not found');
                return;
            }

            // Build modal content
            let html = '';

            // Title
            html += '<h2 class="gcal-modal-title">' + this.escapeHtml(event.title) + '</h2>';

            // Meta information
            html += '<div class="gcal-modal-meta">';

            // Date and Time
            html += '<div class="gcal-modal-meta-item">';
            html += '<span class="gcal-modal-meta-icon">📅</span>';
            html += '<div class="gcal-modal-meta-content">';
            html += '<div class="gcal-modal-meta-label">Date et heure</div>';
            html += '<div class="gcal-modal-datetime">';

            if (event.isAllDay) {
                html += '<div class="gcal-modal-date">' + (event.formattedDate || event.startDate) + '</div>';
                html += '<div class="gcal-all-day-badge">Toute la journée</div>';
            } else {
                html += '<div class="gcal-modal-date">' + (event.formattedRange || event.formattedDateTime) + '</div>';
                if (event.timezoneAbbr) {
                    html += '<div class="gcal-modal-timezone">' + this.escapeHtml(event.timezoneAbbr) + '</div>';
                }
            }

            html += '</div></div></div>';

            // Location
            if (event.location) {
                html += '<div class="gcal-modal-meta-item">';
                html += '<span class="gcal-modal-meta-icon">📍</span>';
                html += '<div class="gcal-modal-meta-content">';
                html += '<div class="gcal-modal-meta-label">Lieu</div>';
                html += '<div class="gcal-modal-meta-value">';

                if (event.mapLink) {
                    html += '<a href="' + this.escapeHtml(event.mapLink) + '" target="_blank" rel="noopener" class="gcal-modal-location-link">';
                    html += this.escapeHtml(event.location);
                    html += '</a>';
                } else {
                    html += this.escapeHtml(event.location);
                }

                html += '</div></div></div>';
            }

            html += '</div>'; // Close meta

            // Description
            if (event.description) {
                html += '<div class="gcal-modal-description">';
                html += this.sanitizeDescription(event.description);
                html += '</div>';
            }

            // Footer with link to Google Calendar
            if (event.htmlLink) {
                html += '<div class="gcal-modal-footer">';
                html += '<a href="' + this.escapeHtml(event.htmlLink) + '" target="_blank" rel="noopener" class="gcal-modal-footer-link">';
                html += 'Voir dans Google Calendar →';
                html += '</a>';
                html += '</div>';
            }

            modalBody.innerHTML = html;
        },

        /**
         * Close current modal
         */
        closeModal: function() {
            if (this.currentModal) {
                this.currentModal.style.display = 'none';
                this.currentModal = null;

                // Restore body scroll
                document.body.style.overflow = '';
            }
        },

        /**
         * Trap focus within modal for accessibility
         *
         * @param {HTMLElement} modal - Modal element
         */
        trapFocus: function(modal) {
            const focusableElements = modal.querySelectorAll(
                'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'
            );

            if (focusableElements.length === 0) return;

            const firstElement = focusableElements[0];
            const lastElement = focusableElements[focusableElements.length - 1];

            // Focus first element
            firstElement.focus();

            // Handle Tab key
            modal.addEventListener('keydown', function(e) {
                if (e.key !== 'Tab') return;

                if (e.shiftKey) {
                    if (document.activeElement === firstElement) {
                        e.preventDefault();
                        lastElement.focus();
                    }
                } else {
                    if (document.activeElement === lastElement) {
                        e.preventDefault();
                        firstElement.focus();
                    }
                }
            });
        },

        /**
         * Sanitize description HTML - allow safe tags, remove dangerous ones
         *
         * @param {string} description - Description HTML
         * @returns {string} Sanitized HTML
         */
        sanitizeDescription: function(description) {
            if (!description) return '';

            // Create a temporary div to parse potential HTML
            const temp = document.createElement('div');
            temp.innerHTML = description;

            // Check if description contains HTML tags
            const hasHTML = temp.children.length > 0 || description.includes('<a ') || description.includes('<br');

            if (hasHTML) {
                // Description has HTML - sanitize it but preserve safe tags
                return this.sanitizeHTML(description);
            } else {
                // Plain text description - escape and convert URLs
                const escaped = this.escapeHtml(description);
                const withLinks = this.makeLinksClickable(escaped);
                return withLinks.replace(/\n/g, '<br>');
            }
        },

        /**
         * Sanitize HTML description - preserve links and br tags, remove dangerous content
         *
         * @param {string} html - HTML content
         * @returns {string} Sanitized HTML
         */
        sanitizeHTML: function(html) {
            const temp = document.createElement('div');
            temp.innerHTML = html;

            // Remove script tags
            const scripts = temp.querySelectorAll('script');
            scripts.forEach(function(script) {
                script.remove();
            });

            // Clean all elements
            const allElements = temp.querySelectorAll('*');
            allElements.forEach(function(elem) {
                // Remove event handler attributes
                for (let i = elem.attributes.length - 1; i >= 0; i--) {
                    const attr = elem.attributes[i];
                    if (attr.name.startsWith('on')) {
                        elem.removeAttribute(attr.name);
                    }
                }

                // Ensure links have safe attributes
                if (elem.tagName === 'A') {
                    elem.setAttribute('target', '_blank');
                    elem.setAttribute('rel', 'noopener noreferrer');
                }
            });

            return temp.innerHTML;
        },

        /**
         * Convert URLs in text to clickable links
         *
         * @param {string} text - Text with escaped HTML
         * @returns {string} Text with URLs as links
         */
        makeLinksClickable: function(text) {
            // Pattern to match URLs (http, https, www)
            const pattern = /\b((https?:\/\/|www\.)[^\s<]+)/gi;

            return text.replace(pattern, function(match, url) {
                // Add http:// if URL starts with www.
                const href = url.startsWith('www.') ? 'http://' + url : url;

                return '<a href="' + href + '" target="_blank" rel="noopener noreferrer">' + url + '</a>';
            });
        },

        /**
         * Escape HTML to prevent XSS
         *
         * @param {string} text - Text to escape
         * @returns {string} Escaped text
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Initialize on load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            GCalEventModal.init();
        });
    } else {
        GCalEventModal.init();
    }

    // Expose to global scope
    window.GCalEventModal = GCalEventModal;

})(window, document);
