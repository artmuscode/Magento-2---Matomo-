/**
 * Copyright © Artimus Code. All rights reserved.
 */
define([
    'Magento_Checkout/js/model/step-navigator',
    'Artimus_Matomo/js/model/paq',
    'Artimus_Matomo/js/model/step-page-view'
], function (stepNavigator, paq, createStepPageView) {
    'use strict';

    /**
     * Sends a virtual page view whenever a checkout step becomes visible.
     */
    return function () {
        var stepPageView = createStepPageView(window.location.href.split('#')[0]),
            watched = {};

        /**
         * @param {Object} step
         */
        function send(step) {
            stepPageView.commandsFor({
                code: step.code,
                title: typeof step.title === 'function' ? step.title() : step.title
            }).forEach(paq.push);
        }

        /**
         * @param {Object} step
         */
        function watch(step) {
            if (watched[step.code]) {
                return;
            }

            watched[step.code] = true;
            step.isVisible.subscribe(function (visible) {
                if (visible) {
                    send(step);
                }
            });

            if (step.isVisible()) {
                send(step);
            }
        }

        stepNavigator.steps().forEach(watch);
        stepNavigator.steps.subscribe(function (steps) {
            steps.forEach(watch);
        });
    };
});
