/**
 * Copyright © Artimus Code. All rights reserved.
 */
define([
    'jquery',
    'Artimus_Matomo/js/model/command-builder',
    'Artimus_Matomo/js/model/paq',
    'Artimus_Matomo/js/model/user-id-resolver',
    'Artimus_Matomo/js/model/visit-boundary',
    'Magento_Customer/js/customer-data',
    'mage/cookies'
], function ($, buildCommands, paq, userIdResolver, visitBoundary, customerData) {
    'use strict';

    var COOKIE_NAME = 'user_allowed_save_cookie',
        USER_ID_TIMEOUT_MS = 2000,
        started = false;

    /**
     * Grant consent now if the visitor already accepted the cookie notice, otherwise when they do.
     * Presence of the cookie is the same check Magento_Cookie/js/notices uses.
     */
    function handleConsent() {
        if ($.mage.cookies.get(COOKIE_NAME)) {
            window._paq.push(['rememberConsentGiven']);

            return;
        }

        $(document).one('user:allowed:save:cookie', function () {
            window._paq.push(['rememberConsentGiven']);
        });
    }

    /**
     * Load matomo.js asynchronously from the configured tracker host.
     *
     * @param {String} trackerUrl
     */
    function loadScript(trackerUrl) {
        var script = document.createElement('script');

        script.async = true;
        script.src = trackerUrl + 'matomo.js';
        document.head.appendChild(script);
    }

    /**
     * @param {Object} config
     */
    return function (config) {
        var resolver;

        if (started || paq.isReady()) {
            return;
        }
        started = true;

        resolver = userIdResolver.create(customerData, window);
        resolver.resolve(USER_ID_TIMEOUT_MS, function (userId) {
            window._paq = window._paq || [];
            buildCommands($.extend({}, config, {
                userId: userId,
                newVisit: visitBoundary(userId)
            })).forEach(function (command) {
                window._paq.push(command);
            });

            if (config.requireConsent) {
                handleConsent();
            }

            paq.setInitialUserId(userId);
            paq.init();
            loadScript(config.trackerUrl);
        });
    };
});
