/**
 * Copyright © Artimus Code. All rights reserved.
 */
define([
    'Magento_Customer/js/customer-data',
    'Artimus_Matomo/js/model/paq'
], function (customerData, paq) {
    'use strict';

    return function () {
        var section = customerData.get('matomo'),
            current = null;

        /**
         * @param {Object} data
         */
        function onChange(data) {
            var userId = data && data.userId ? data.userId : null;

            if (userId === current) {
                return;
            }

            if (userId) {
                paq.push(['setUserId', userId]);
            } else if (current) {
                paq.push(['resetUserId']);
            }
            current = userId;
        }

        /**
         * Start from the User ID the bootstrap already sent, then follow later changes. Deferred to
         * paq readiness so it can only ever compare against what the bootstrap actually set.
         */
        function start() {
            current = paq.getInitialUserId();
            section.subscribe(onChange);
            onChange(section());
        }

        paq.whenReady(start);
    };
});
