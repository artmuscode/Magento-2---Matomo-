/**
 * Copyright © Artimus Code. All rights reserved.
 */
define([], function () {
    'use strict';

    /**
     * Pure logic turning the cacheable tracker config into an ordered list of `_paq` commands.
     *
     * Order: requireConsent (if required), setTrackerUrl, setSiteId, setUserId (only when a user id is
     * known), enableLinkTracking, every provider command, then trackPageView unless a provider already
     * replaces it.
     *
     * @param {Object} config
     * @param {String} config.trackerUrl
     * @param {Number} config.siteId
     * @param {Boolean} config.requireConsent
     * @param {Array} config.providerCommands
     * @param {Boolean} config.replacesPageView
     * @param {(String|null)} [config.userId] Resolved at runtime, never part of the cacheable config
     * @param {Boolean} [config.newVisit] Force a new visit on the page view request only
     * @return {Array}
     */
    return function buildCommands(config) {
        var commands = [];

        if (config.requireConsent) {
            commands.push(['requireConsent']);
        }

        commands.push(['setTrackerUrl', config.trackerUrl + 'matomo.php']);
        commands.push(['setSiteId', config.siteId]);
        if (config.userId) {
            commands.push(['setUserId', config.userId]);
        }

        commands.push(['enableLinkTracking']);

        (config.providerCommands || []).forEach(function (command) {
            commands.push(command);
        });

        if (!config.replacesPageView) {
            if (config.newVisit) {
                commands.push(['appendToTrackingUrl', 'new_visit=1']);
            }
            commands.push(['trackPageView']);
            if (config.newVisit) {
                commands.push(['appendToTrackingUrl', '']);
            }
        }
        // newVisit is ignored when a provider replaces the page view (checkout): logout never lands there.

        return commands;
    };
});
