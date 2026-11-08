/**
 * Copyright © Artimus Code. All rights reserved.
 */
define([], function () {
    'use strict';

    /**
     * Pure logic turning checkout steps becoming visible into virtual page view `_paq` commands.
     *
     * Skips a step code that repeats the one sent just before it.
     *
     * @param {String} checkoutUrl
     * @return {{commandsFor: Function}}
     */
    return function createStepPageView(checkoutUrl) {
        var lastCode = null;

        return {
            /**
             * @param {Object} step
             * @param {String} step.code
             * @param {String} step.title
             * @return {Array}
             */
            commandsFor: function (step) {
                if (step.code === lastCode) {
                    return [];
                }

                lastCode = step.code;

                return [
                    ['setCustomUrl', checkoutUrl + '#' + step.code],
                    ['setDocumentTitle', 'Checkout: ' + step.title],
                    ['trackPageView']
                ];
            }
        };
    };
});
