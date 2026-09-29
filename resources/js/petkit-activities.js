/** Existing activity-list refresh button state, shared by both legacy views. */
(function () {
    'use strict';

    if (window.petkitRefreshButton) {
        return;
    }

    window.petkitRefreshButton = function () {
        return {
            spinning: false,

            spin() {
                this.spinning = true;
                setTimeout(() => {
                    this.spinning = false;
                }, 600);
            },
        };
    };
})();
