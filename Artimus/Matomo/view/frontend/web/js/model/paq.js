/**
 * Copyright © Artimus Code. All rights reserved.
 */
define([], function () {
    'use strict';

    /**
     * The only way companion components (cart, user ID, checkout steps) may send `_paq` commands.
     *
     * Commands pushed before `init()` are buffered. `Artimus_Matomo/js/tracker` pushes its base
     * commands (consent, tracker URL, site ID, page view) straight to `window._paq` and then calls
     * `init()`, which flushes the buffer in order, so companion commands never land before the
     * default page view. When tracking is inactive the tracker isn't rendered, `init()` is never
     * called, and companions send nothing.
     *
     * @return {Object}
     */
    function create() {
        var buffer = [],
            ready = false,
            readyCallbacks = [],
            initialUserId = null;

        return {
            /**
             * @param {Array} command
             */
            push: function (command) {
                if (ready) {
                    window._paq.push(command);
                } else {
                    buffer.push(command);
                }
            },

            /**
             * Flush buffered commands to `window._paq`; later pushes go straight through.
             */
            init: function () {
                var callbacks;

                if (ready) {
                    return;
                }

                window._paq = window._paq || [];
                ready = true;
                buffer.forEach(function (command) {
                    window._paq.push(command);
                });
                buffer = [];
                callbacks = readyCallbacks;
                readyCallbacks = [];
                callbacks.forEach(function (callback) {
                    callback();
                });
            },

            /**
             * Run the callback once paq is ready: now if it already is, otherwise right after
             * `init()` has flushed the buffer.
             *
             * @param {Function} callback
             */
            whenReady: function (callback) {
                if (ready) {
                    callback();
                } else {
                    readyCallbacks.push(callback);
                }
            },

            /**
             * Record the User ID the tracker bootstrap sent with its base commands.
             *
             * @param {String|null} id
             */
            setInitialUserId: function (id) {
                initialUserId = id;
            },

            /**
             * @return {String|null}
             */
            getInitialUserId: function () {
                return initialUserId;
            },

            /**
             * @return {Boolean}
             */
            isReady: function () {
                return ready;
            }
        };
    }

    var instance = create();

    // Exposed so specs can build an isolated instance; components use the shared one.
    instance.create = create;

    return instance;
});
