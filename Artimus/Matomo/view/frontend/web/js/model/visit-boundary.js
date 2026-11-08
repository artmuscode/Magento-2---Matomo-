/**
 * Copyright © Artimus Code. All rights reserved.
 */
define([], function () {
    'use strict';

    var FLAG_KEY = 'artimus-matomo-had-user-id';

    /**
     * Tell whether this page view must start a new Matomo visit, and keep the marker up to date.
     *
     * The marker is a boolean '1' only, never the user id or its hash. Every storage access is guarded,
     * so blocked storage degrades to "no new visit" instead of breaking tracking.
     *
     * @param {(String|null)} userId User id the bootstrap resolved
     * @param {Object} [storage] Defaults to window.localStorage
     * @return {Boolean} True when the previous page view had a user id and this one has none
     */
    return function (userId, storage) {
        var hadUserId = false;

        try {
            storage = storage || window.localStorage;
            hadUserId = storage.getItem(FLAG_KEY) === '1';

            if (userId) {
                storage.setItem(FLAG_KEY, '1');
            } else if (hadUserId) {
                storage.removeItem(FLAG_KEY);
            }
        } catch (e) {
            return false;
        }

        return !userId && hadUserId;
    };
});
