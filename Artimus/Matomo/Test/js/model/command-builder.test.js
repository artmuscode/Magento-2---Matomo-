/**
 * Copyright © Artimus Code. All rights reserved.
 */
define([
    'Artimus_Matomo/js/model/command-builder'
], function (buildCommands) {
    'use strict';

    describe('Artimus_Matomo/js/model/command-builder', function () {
        var baseConfig;

        beforeEach(function () {
            baseConfig = {
                trackerUrl: 'https://matomo.test/',
                siteId: 3,
                requireConsent: false,
                providerCommands: [],
                replacesPageView: false
            };
        });

        it('builds requireConsent as the first command when consent is required', function () {
            var config = Object.assign({}, baseConfig, {requireConsent: true}),
                commands = buildCommands(config);

            expect(commands[0]).toEqual(['requireConsent']);
        });

        it('does not build a requireConsent command when consent is not required', function () {
            var commands = buildCommands(baseConfig);

            commands.forEach(function (command) {
                expect(command[0]).not.toBe('requireConsent');
            });
        });

        it('places provider commands before trackPageView', function () {
            var config = Object.assign({}, baseConfig, {
                    providerCommands: [['setEcommerceView', 'SKU1']]
                }),
                commands = buildCommands(config),
                providerIndex = commands.findIndex(function (command) {
                    return command[0] === 'setEcommerceView';
                }),
                pageViewIndex = commands.findIndex(function (command) {
                    return command[0] === 'trackPageView';
                });

            expect(providerIndex).toBeGreaterThan(-1);
            expect(pageViewIndex).toBeGreaterThan(-1);
            expect(providerIndex).toBeLessThan(pageViewIndex);
        });

        it('omits trackPageView when the config says a provider replaces it', function () {
            var config = Object.assign({}, baseConfig, {replacesPageView: true}),
                commands = buildCommands(config),
                pageViewIndex = commands.findIndex(function (command) {
                    return command[0] === 'trackPageView';
                });

            expect(pageViewIndex).toBe(-1);
        });

        it('sets the tracker url and site id before enabling link tracking', function () {
            var commands = buildCommands(baseConfig);

            expect(commands).toEqual([
                ['setTrackerUrl', 'https://matomo.test/matomo.php'],
                ['setSiteId', 3],
                ['enableLinkTracking'],
                ['trackPageView']
            ]);
        });

        describe('user id', function () {
            function indexOf(commands, name) {
                return commands.findIndex(function (command) {
                    return command[0] === name;
                });
            }

            it('adds setUserId after setSiteId when a user id is given', function () {
                var commands = buildCommands(Object.assign({}, baseConfig, {userId: 'a@b.test'}));

                expect(indexOf(commands, 'setUserId')).toBe(indexOf(commands, 'setSiteId') + 1);
                expect(commands[indexOf(commands, 'setUserId')]).toEqual(['setUserId', 'a@b.test']);
            });

            it('places setUserId before trackPageView', function () {
                var commands = buildCommands(Object.assign({}, baseConfig, {userId: 'a@b.test'}));

                expect(indexOf(commands, 'setUserId')).toBeGreaterThan(-1);
                expect(indexOf(commands, 'setUserId')).toBeLessThan(indexOf(commands, 'trackPageView'));
            });

            it('places setUserId before provider commands', function () {
                var commands = buildCommands(Object.assign({}, baseConfig, {
                    userId: 'a@b.test',
                    providerCommands: [['setEcommerceView', 'SKU1']]
                }));

                expect(indexOf(commands, 'setUserId')).toBeGreaterThan(-1);
                expect(indexOf(commands, 'setUserId')).toBeLessThan(indexOf(commands, 'setEcommerceView'));
            });

            it('omits setUserId when the user id is null', function () {
                var commands = buildCommands(Object.assign({}, baseConfig, {userId: null}));

                expect(indexOf(commands, 'setUserId')).toBe(-1);
            });

            it('omits setUserId when the user id is an empty string', function () {
                var commands = buildCommands(Object.assign({}, baseConfig, {userId: ''}));

                expect(indexOf(commands, 'setUserId')).toBe(-1);
            });

            it('adds setUserId even when a provider replaces the page view', function () {
                var commands = buildCommands(Object.assign({}, baseConfig, {
                    userId: 'a@b.test',
                    replacesPageView: true
                }));

                expect(indexOf(commands, 'setUserId')).toBeGreaterThan(-1);
                expect(indexOf(commands, 'trackPageView')).toBe(-1);
            });
        });

        it('wraps trackPageView in new_visit append commands when newVisit is set', function () {
            var commands = buildCommands(Object.assign({}, baseConfig, {newVisit: true}));

            expect(commands.slice(-3)).toEqual([
                ['appendToTrackingUrl', 'new_visit=1'],
                ['trackPageView'],
                ['appendToTrackingUrl', '']
            ]);
        });

        it('adds no new_visit commands when a provider replaces the page view', function () {
            var commands = buildCommands(Object.assign({}, baseConfig, {newVisit: true, replacesPageView: true}));

            expect(JSON.stringify(commands)).not.toContain('appendToTrackingUrl');
        });
    });
});
