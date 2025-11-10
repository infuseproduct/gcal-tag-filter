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
            this.checkURLForEvent();
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
                // Don't open modal if clicking on a link
                if (e.target.tagName === 'A' || e.target.closest('a')) {
                    return; // Let the link work normally
                }

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

            // Share button - copy link to clipboard
            document.addEventListener('click', function(e) {
                const shareButton = e.target.closest('.gcal-share-button');

                if (shareButton) {
                    e.preventDefault();
                    const eventId = shareButton.dataset.eventId;

                    if (eventId) {
                        self.shareEvent(eventId, shareButton);
                    }
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

            // Update URL with event ID for sharing
            this.updateURLWithEvent(eventId);
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
            html += '<div class="gcal-modal-meta-label">' + (gcalData.i18n.dateAndTime || 'Date and time') + '</div>';
            html += '<div class="gcal-modal-datetime">';

            if (event.isAllDay) {
                html += '<div class="gcal-modal-date">' + (event.formattedDate || event.startDate) + '</div>';
                html += '<div class="gcal-all-day-badge">' + gcalData.i18n.allDay + '</div>';
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
                html += '<div class="gcal-modal-meta-label">' + (gcalData.i18n.location || 'Location') + '</div>';
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

            // Footer with link to Google Calendar and share button
            if (event.htmlLink) {
                html += '<div class="gcal-modal-footer">';
                html += '<a href="' + this.escapeHtml(event.htmlLink) + '" target="_blank" rel="noopener" class="gcal-modal-footer-link">';
                html += (gcalData.i18n.viewInGoogleCalendar || 'View in Google Calendar') + ' →';
                html += '</a>';
                html += '<button type="button" class="gcal-share-button" data-event-id="' + this.escapeHtml(event.id) + '" title="' + (gcalData.i18n.copyLink || 'Copy link') + '">';
                html += '<span class="gcal-share-icon">🔗</span>';
                html += '<span class="gcal-share-text">' + (gcalData.i18n.copyLink || 'Copy link') + '</span>';
                html += '</button>';
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

                // Remove event ID from URL
                this.removeEventFromURL();
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
        },

        /**
         * Check URL for event ID parameter and auto-open modal
         */
        checkURLForEvent: function() {
            const url = new URL(window.location);
            const eventId = url.searchParams.get('gcal_event');

            if (eventId) {
                console.log('GCal: Checking for event ID from URL:', eventId);
                console.log('GCal: Available events:', this.eventsData);

                // Small delay to ensure events are loaded
                setTimeout(() => {
                    const event = this.findEvent(eventId);
                    console.log('GCal: Found event:', event);

                    if (event) {
                        this.openModal(eventId);
                    } else {
                        console.warn('Event not found in URL:', eventId);
                        // Show user-friendly message
                        this.showEventNotFoundMessage(eventId);
                        // Remove invalid event ID from URL
                        this.removeEventFromURL();
                    }
                }, 100);
            }
        },

        /**
         * Show message when shared event is not in current view
         *
         * @param {string} eventId - Event ID that wasn't found
         */
        showEventNotFoundMessage: function(eventId) {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = 'gcal-event-not-found-notification';
            notification.innerHTML = `
                <div class="gcal-notification-content">
                    <span class="gcal-notification-icon">ℹ️</span>
                    <div class="gcal-notification-message">
                        <strong>Événement non trouvé</strong>
                        <p>L'événement partagé n'est pas visible dans la période actuelle. Essayez de changer la vue ou la période.</p>
                    </div>
                    <button class="gcal-notification-close" aria-label="Fermer">&times;</button>
                </div>
            `;

            // Add styles inline for immediate display
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                left: 50%;
                transform: translateX(-50%);
                background: #fff3cd;
                border: 1px solid #ffc107;
                border-radius: 8px;
                padding: 16px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                z-index: 999998;
                max-width: 500px;
                width: 90%;
                animation: slideDown 0.3s ease-out;
            `;

            // Append to body
            document.body.appendChild(notification);

            // Close button handler
            const closeBtn = notification.querySelector('.gcal-notification-close');
            closeBtn.addEventListener('click', function() {
                notification.style.animation = 'slideUp 0.3s ease-out';
                setTimeout(() => notification.remove(), 300);
            });

            // Auto-remove after 8 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.style.animation = 'slideUp 0.3s ease-out';
                    setTimeout(() => notification.remove(), 300);
                }
            }, 8000);
        },

        /**
         * Update URL with event ID for sharing
         *
         * @param {string} eventId - Event ID to add to URL
         */
        updateURLWithEvent: function(eventId) {
            const url = new URL(window.location);
            url.searchParams.set('gcal_event', eventId);
            window.history.pushState({}, '', url);
        },

        /**
         * Remove event ID from URL
         */
        removeEventFromURL: function() {
            const url = new URL(window.location);
            url.searchParams.delete('gcal_event');
            window.history.pushState({}, '', url);
        },

        /**
         * Share event - copy link to clipboard
         *
         * @param {string} eventId - Event ID
         * @param {HTMLElement} button - Share button element
         */
        shareEvent: function(eventId, button) {
            const event = this.findEvent(eventId);

            if (!event) {
                console.warn('Event not found for sharing:', eventId);
                return;
            }

            // Build shareable URL
            const url = new URL(window.location.href);
            url.searchParams.set('gcal_event', eventId);
            const shareUrl = url.toString();

            // Copy to clipboard
            this.copyToClipboard(shareUrl, button);
        },

        /**
         * Copy text to clipboard and show feedback
         *
         * @param {string} text - Text to copy
         * @param {HTMLElement} button - Button element for feedback
         */
        copyToClipboard: function(text, button) {
            // Try modern clipboard API first
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function() {
                    this.showCopyFeedback(button, true);
                }.bind(this)).catch(function(err) {
                    console.error('Failed to copy to clipboard:', err);
                    // Fallback to execCommand
                    this.copyToClipboardFallback(text, button);
                }.bind(this));
            } else {
                // Fallback for older browsers
                this.copyToClipboardFallback(text, button);
            }
        },

        /**
         * Fallback clipboard copy using execCommand
         *
         * @param {string} text - Text to copy
         * @param {HTMLElement} button - Button element for feedback
         */
        copyToClipboardFallback: function(text, button) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();

            try {
                const successful = document.execCommand('copy');
                this.showCopyFeedback(button, successful);
            } catch (err) {
                console.error('Failed to copy to clipboard:', err);
                this.showCopyFeedback(button, false);
            }

            document.body.removeChild(textarea);
        },

        /**
         * Show visual feedback when URL is copied
         *
         * @param {HTMLElement} button - Button element
         * @param {boolean} success - Whether copy was successful
         */
        showCopyFeedback: function(button, success) {
            const originalText = button.querySelector('.gcal-share-text').textContent;

            if (success) {
                button.classList.add('gcal-share-success');
                button.querySelector('.gcal-share-text').textContent = 'Copié!';
                button.querySelector('.gcal-share-icon').textContent = '✓';
            } else {
                button.classList.add('gcal-share-error');
                button.querySelector('.gcal-share-text').textContent = 'Erreur';
                button.querySelector('.gcal-share-icon').textContent = '✗';
            }

            // Reset after 2 seconds
            setTimeout(function() {
                button.classList.remove('gcal-share-success', 'gcal-share-error');
                button.querySelector('.gcal-share-text').textContent = originalText;
                button.querySelector('.gcal-share-icon').textContent = '🔗';
            }, 2000);
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
