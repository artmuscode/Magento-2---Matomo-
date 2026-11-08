/**
 * Copyright © Artimus Code. All rights reserved.
 */
define([], function () {
    'use strict';

    describe('Artimus_Matomo/js/tracker', function () {
        var HASH = 'a'.repeat(64),
            config,
            paq,
            resolveSpy,
            resolverModule,
            cookieValue,
            scripts,
            store,
            tracker;

        beforeEach(function () {
            var $ = function () {
                return {one: jasmine.createSpy('one')};
            };

            $.extend = function (target) {
                var i, key;

                for (i = 1; i < arguments.length; i++) {
                    for (key in arguments[i]) {
                        target[key] = arguments[i][key];
                    }
                }

                return target;
            };
            cookieValue = 'yes';
            $.mage = {cookies: {get: function () {
                return cookieValue;
            }}};

            scripts = [];
            window.document = {
                createElement: function () {
                    return {};
                },
                head: {appendChild: function (el) {
                    scripts.push(el);
                }}
            };
            window._paq = [];
            store = {};
            window.localStorage = {
                getItem: function (key) {
                    return Object.prototype.hasOwnProperty.call(store, key) ? store[key] : null;
                },
                setItem: function (key, value) {
                    store[key] = String(value);
                },
                removeItem: function (key) {
                    delete store[key];
                }
            };

            resolveSpy = jasmine.createSpy('resolve');
            resolverModule = {create: jasmine.createSpy('create').and.returnValue({resolve: resolveSpy})};
            paq = amd.load('Artimus_Matomo/js/model/paq');
            config = {trackerUrl: 'https://m.test/', siteId: 1, requireConsent: false};
            tracker = amd.load('Artimus_Matomo/js/tracker', {
                'jquery': $,
                'mage/cookies': {},
                'Magento_Customer/js/customer-data': {},
                'Artimus_Matomo/js/model/paq': paq,
                'Artimus_Matomo/js/model/user-id-resolver': resolverModule
            });
        });

        afterEach(function () {
            delete window.localStorage;
            delete window._paq;
            delete window.document;
        });

        function names() {
            return window._paq.map(function (command) {
                return command[0];
            });
        }

        it('pushes setUserId before trackPageView when the resolver returns a user id', function () {
            tracker(config);
            resolveSpy.calls.argsFor(0)[1](HASH);

            expect(window._paq).toContain(['setUserId', HASH]);
            expect(names().indexOf('setUserId')).toBeLessThan(names().indexOf('trackPageView'));
        });

        it('pushes no setUserId when the resolver returns null', function () {
            tracker(config);
            resolveSpy.calls.argsFor(0)[1](null);

            expect(names()).not.toContain('setUserId');
            expect(names()).toContain('trackPageView');
        });

        it('pushes no base commands and loads no script until the resolver calls back', function () {
            tracker(config);

            expect(resolveSpy).toHaveBeenCalledTimes(1);
            expect(resolveSpy.calls.argsFor(0)[0]).toBe(2000);
            expect(window._paq).toEqual([]);
            expect(scripts).toEqual([]);

            resolveSpy.calls.argsFor(0)[1](null);

            expect(scripts.length).toBe(1);
            expect(scripts[0].src).toBe('https://m.test/matomo.js');
        });

        it('initialises paq only after the base commands are queued', function () {
            var lengthAtInit;

            spyOn(paq, 'init').and.callFake(function () {
                lengthAtInit = window._paq.length;
            });
            tracker(config);
            resolveSpy.calls.argsFor(0)[1](null);

            expect(lengthAtInit).toBeGreaterThan(0);
        });

        it('ignores a second bootstrap call made while the first is still resolving', function () {
            tracker(config);
            tracker(config);

            expect(resolveSpy).toHaveBeenCalledTimes(1);
        });

        it('sets the initial user id on paq before calling paq.init', function () {
            var seen;

            spyOn(paq, 'init').and.callFake(function () {
                seen = paq.getInitialUserId();
            });
            tracker(config);
            resolveSpy.calls.argsFor(0)[1](HASH);

            expect(seen).toBe(HASH);
        });

        it('creates the resolver with the real window as timer', function () {
            tracker(config);

            expect(resolverModule.create.calls.argsFor(0)[1]).toBe(window);
        });

        it('sets the had-user-id flag when the bootstrap sends a user id', function () {
            tracker(config);
            resolveSpy.calls.argsFor(0)[1](HASH);

            expect(store['artimus-matomo-had-user-id']).toBe('1');
        });

        it('adds new_visit=1 right before trackPageView when the flag is set and no user id resolves', function () {
            store['artimus-matomo-had-user-id'] = '1';
            tracker(config);
            resolveSpy.calls.argsFor(0)[1](null);

            expect(window._paq[names().indexOf('trackPageView') - 1]).toEqual(['appendToTrackingUrl', 'new_visit=1']);
        });

        it('clears the appended new_visit parameter right after trackPageView', function () {
            store['artimus-matomo-had-user-id'] = '1';
            tracker(config);
            resolveSpy.calls.argsFor(0)[1](null);

            expect(window._paq[names().indexOf('trackPageView') + 1]).toEqual(['appendToTrackingUrl', '']);
        });

        it('clears the had-user-id flag after forcing the new visit', function () {
            store['artimus-matomo-had-user-id'] = '1';
            tracker(config);
            resolveSpy.calls.argsFor(0)[1](null);

            expect(store['artimus-matomo-had-user-id']).toBeUndefined();
        });

        it('does not add new_visit for a guest whose flag is not set', function () {
            tracker(config);
            resolveSpy.calls.argsFor(0)[1](null);

            expect(names()).not.toContain('appendToTrackingUrl');
        });

        it('keeps tracking without new_visit when localStorage throws', function () {
            var boom = function () {
                throw new Error('blocked');
            };

            window.localStorage = {getItem: boom, setItem: boom, removeItem: boom};
            tracker(config);
            resolveSpy.calls.argsFor(0)[1](HASH);

            expect(names()).toContain('trackPageView');
            expect(names()).not.toContain('appendToTrackingUrl');
        });

        it('never stores the user id or its hash in localStorage', function () {
            tracker(config);
            resolveSpy.calls.argsFor(0)[1](HASH);

            expect(JSON.stringify(store)).not.toContain(HASH);
        });
    });
});
