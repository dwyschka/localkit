/**
 * Petkit Activities Alpine.js Helpers & Entrypoint
 *
 * Provides reusable helpers for dropdown popovers, filters, and activity components.
 */

(function () {
    'use strict';

    /**
     * Factory for simple popover dropdown states.
     *
     * @param {boolean} [defaultOpen=false]
     * @returns {{open: boolean, close: Function, toggle: Function}}
     */
    function petkitDropdown(defaultOpen = false) {
        return {
            open: Boolean(defaultOpen),

            close() {
                this.open = false;
            },

            toggle() {
                this.open = !this.open;
            },
        };
    }

    window.petkitDropdown = petkitDropdown;

    if (window.Alpine) {
        window.Alpine.data('petkitDropdown', petkitDropdown);
    } else {
        document.addEventListener('alpine:init', () => {
            window.Alpine.data('petkitDropdown', petkitDropdown);
        });
    }
})();
