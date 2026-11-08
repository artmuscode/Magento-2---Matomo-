/**
 * Copyright © Artimus Code. All rights reserved.
 */
define([], function () {
    'use strict';

    describe('Artimus_Matomo/js/user-id', function () {
        var HASH = 'a'.repeat(64),
            OTHER = 'b'.repeat(64),
            paq,
            section,
            component;

        /**
         * Minimal knockout-observable stand-in that notifies in subscription order.
         */
        function makeSection(value) {
            var current = value,
                subscribers = [],
                observable = function () {
                    return current;
                };

            observable.subscribe = jasmine.createSpy('subscribe').and.callFake(function (fn) {
                subscribers.push(fn);
            });
            observable.update = function (next) {
                current = next;
                subscribers.slice().forEach(function (fn) {
                    fn(next);
                });
            };

            return observable;
        }

        /**
         * Mimics the tracker callback: base commands, initial id, init.
         */
        function bootstrap(userId) {
            window._paq = window._paq || [];
            if (userId) {
                window._paq.push(['setUserId', userId]);
            }
            paq.setInitialUserId(userId);
            paq.init();
        }

        function userIdCommands() {
            return window._paq.filter(function (command) {
                return command[0] === 'setUserId' || command[0] === 'resetUserId';
            });
        }

        beforeEach(function () {
            window._paq = [];
            section = makeSection({});
            paq = amd.load('Artimus_Matomo/js/model/paq');
            component = amd.load('Artimus_Matomo/js/user-id', {
                'Magento_Customer/js/customer-data': {get: function () {
                    return section;
                }},
                'Artimus_Matomo/js/model/paq': paq
            });
        });

        afterEach(function () {
            delete window._paq;
        });

        it('does not push a duplicate setUserId for the user id the bootstrap already set', function () {
            section.update({userId: HASH});
            component();
            bootstrap(HASH);

            expect(userIdCommands()).toEqual([['setUserId', HASH]]);
        });

        it('pushes resetUserId when the matomo section loses its user id after bootstrap', function () {
            section.update({userId: HASH});
            component();
            bootstrap(HASH);
            section.update({});

            expect(userIdCommands()).toEqual([['setUserId', HASH], ['resetUserId']]);
        });

        it('pushes setUserId when the section changes to a different user id after bootstrap', function () {
            section.update({userId: HASH});
            component();
            bootstrap(HASH);
            section.update({userId: OTHER});

            expect(userIdCommands()).toEqual([['setUserId', HASH], ['setUserId', OTHER]]);
        });

        it('does not push a duplicate setUserId when the matomo section updates before the resolver callback runs', function () {
            var resolverSubscriber = function (data) {
                bootstrap(data.userId);
            };

            component();
            // The resolver subscribes after user-id.js initialised, so it is notified second.
            section.subscribe(resolverSubscriber);
            section.update({userId: HASH});

            expect(userIdCommands()).toEqual([['setUserId', HASH]]);
        });

        it('pushes setUserId directly when the section delivers a user id after the resolver timed out with null', function () {
            component();
            bootstrap(null);
            section.update({userId: HASH});

            expect(userIdCommands()).toEqual([['setUserId', HASH]]);
        });

        it('does not subscribe to the matomo section until paq is ready', function () {
            component();

            expect(section.subscribe).not.toHaveBeenCalled();

            bootstrap(null);

            expect(section.subscribe).toHaveBeenCalledTimes(1);
        });
    });
});
