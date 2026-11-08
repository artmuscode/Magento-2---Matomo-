/**
 * Copyright © Artimus Code. All rights reserved.
 */
define([], function () {
    'use strict';

    var SECTION = 'matomo';

    /**
     * Read the hashed User ID from a matomo section value.
     *
     * @param {Object|null|undefined} data
     * @return {String|null}
     */
    function readUserId(data) {
        return data && typeof data.userId === 'string' && data.userId ? data.userId : null;
    }

    return {
        /**
         * @param {Object} customerData Magento_Customer/js/customer-data
         * @param {Object} [timer] {setTimeout, clearTimeout}, defaults to window
         * @return {{resolve: function(Number, function((String|null)))}}
         */
        create: function (customerData, timer) {
            timer = timer || window;

            return {
                /**
                 * Call back exactly once with the User ID or null, waiting for a
                 * reloading matomo section but never longer than timeoutMs.
                 *
                 * @param {Number} timeoutMs
                 * @param {Function} callback
                 */
                resolve: function (timeoutMs, callback) {
                    var finished = false,
                        subscription = null,
                        timerId,
                        section = customerData.get(SECTION);

                    function finish() {
                        if (finished) {
                            return;
                        }
                        finished = true;
                        timer.clearTimeout(timerId);
                        if (subscription) {
                            subscription.dispose();
                        }
                        callback(readUserId(section()));
                    }

                    timerId = timer.setTimeout(finish, timeoutMs);

                    customerData.getInitCustomerData().done(function () {
                        if (finished) {
                            return;
                        }
                        if (customerData.getExpiredSectionNames().indexOf(SECTION) === -1) {
                            finish();

                            return;
                        }
                        subscription = section.subscribe(finish);
                    });
                }
            };
        }
    };
});
