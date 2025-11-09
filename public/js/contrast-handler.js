/**
 * Contrast Handler
 *
 * Automatically adjusts text color based on background color contrast.
 */

(function(window, document) {
    'use strict';

    /**
     * GCal Contrast Handler
     */
    const GCalContrast = {
        /**
         * Initialize contrast handling
         */
        init: function() {
            this.applyContrastColors();
        },

        /**
         * Calculate relative luminance of a color
         * Using WCAG formula: https://www.w3.org/TR/WCAG20/#relativeluminancedef
         *
         * @param {number} r - Red value (0-255)
         * @param {number} g - Green value (0-255)
         * @param {number} b - Blue value (0-255)
         * @returns {number} Relative luminance (0-1)
         */
        getLuminance: function(r, g, b) {
            // Convert to 0-1 range
            const [rs, gs, bs] = [r, g, b].map(val => {
                const v = val / 255;
                return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
            });

            // Calculate luminance
            return 0.2126 * rs + 0.7152 * gs + 0.0722 * bs;
        },

        /**
         * Calculate contrast ratio between two colors
         *
         * @param {number} l1 - Luminance of first color
         * @param {number} l2 - Luminance of second color
         * @returns {number} Contrast ratio (1-21)
         */
        getContrastRatio: function(l1, l2) {
            const lighter = Math.max(l1, l2);
            const darker = Math.min(l1, l2);
            return (lighter + 0.05) / (darker + 0.05);
        },

        /**
         * Parse RGB color from string
         *
         * @param {string} color - Color string (hex, rgb, or rgba)
         * @returns {object|null} Object with r, g, b properties or null if invalid
         */
        parseColor: function(color) {
            if (!color) return null;

            // Handle hex colors
            if (color.startsWith('#')) {
                const hex = color.replace('#', '');
                if (hex.length === 3) {
                    return {
                        r: parseInt(hex[0] + hex[0], 16),
                        g: parseInt(hex[1] + hex[1], 16),
                        b: parseInt(hex[2] + hex[2], 16)
                    };
                } else if (hex.length === 6) {
                    return {
                        r: parseInt(hex.substring(0, 2), 16),
                        g: parseInt(hex.substring(2, 4), 16),
                        b: parseInt(hex.substring(4, 6), 16)
                    };
                }
            }

            // Handle rgb/rgba colors
            const rgbMatch = color.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
            if (rgbMatch) {
                return {
                    r: parseInt(rgbMatch[1], 10),
                    g: parseInt(rgbMatch[2], 10),
                    b: parseInt(rgbMatch[3], 10)
                };
            }

            return null;
        },

        /**
         * Check if white text should be used on the given background
         *
         * @param {string} bgColor - Background color
         * @returns {boolean} True if white text should be used
         */
        shouldUseWhiteText: function(bgColor) {
            const rgb = this.parseColor(bgColor);
            if (!rgb) return false;

            const bgLuminance = this.getLuminance(rgb.r, rgb.g, rgb.b);

            // Luminance of white is 1, black is 0
            const whiteContrast = this.getContrastRatio(1, bgLuminance);
            const blackContrast = this.getContrastRatio(0, bgLuminance);

            // Use white text if it has better contrast
            // Also use white if background luminance is less than 0.5 (dark background)
            return whiteContrast > blackContrast || bgLuminance < 0.5;
        },

        /**
         * Apply contrast-based colors to all event items
         */
        applyContrastColors: function() {
            // Calendar view events
            const eventItems = document.querySelectorAll('.gcal-event-item');
            console.log(`Applying contrast to ${eventItems.length} event items`);

            eventItems.forEach(item => {
                const bgColor = window.getComputedStyle(item).backgroundColor;
                const useWhite = this.shouldUseWhiteText(bgColor);
                const textColor = useWhite ? '#ffffff' : '#000000';

                console.log(`Event: bgColor=${bgColor}, useWhite=${useWhite}, textColor=${textColor}`);

                item.style.color = textColor;

                // Also set on child elements to ensure they inherit
                const time = item.querySelector('.gcal-event-time');
                const title = item.querySelector('.gcal-event-title');
                if (time) time.style.color = textColor;
                if (title) title.style.color = textColor;
            });

            // List view category badges
            const categoryBadges = document.querySelectorAll('.gcal-event-category');
            categoryBadges.forEach(badge => {
                const bgColor = window.getComputedStyle(badge).backgroundColor;
                if (this.shouldUseWhiteText(bgColor)) {
                    badge.style.color = '#ffffff';
                } else {
                    badge.style.color = '#000000';
                }
            });
        }
    };

    // Initialize on load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            GCalContrast.init();
        });
    } else {
        GCalContrast.init();
    }

    // Re-apply after filtering (when events visibility changes)
    document.addEventListener('gcal-filter-complete', function() {
        GCalContrast.applyContrastColors();
    });

    // Expose to global scope
    window.GCalContrast = GCalContrast;

})(window, document);
