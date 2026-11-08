/**
 * Copyright © Artimus Code. All rights reserved.
 */
define([], function () {
    'use strict';

    describe('Artimus_Matomo/js/model/user-id-resolver', function () {
        var HASH = 'a'.repeat(64),
            OTHER = 'b'.repeat(64),
            factory,
            section,
            initCallbacks,
            expired,
            customerData,
            callback,
            resolver;

        /**
         * Minimal knockout-observable stand-in.
         */
        function makeSection(value) {
            var current = value,
                subscribers = [],
                observable = function () {
                    return current;
                };

            observable.subscribe = jasmine.createSpy('subscribe').and.callFake(function (fn) {
                var sub = {
                    dispose: jasmine.createSpy('dispose').and.callFake(function () {
                        subscribers.splice(subscribers.indexOf(fn), 1);
                    })
                };

                subscribers.push(fn);
                observable.lastSubscription = sub;

                return sub;
            });
            observable.update = function (next) {
                current = next;
                subscribers.slice().forEach(function (fn) {
                    fn(next);
                });
            };
            observable.subscriberCount = function () {
                return subscribers.length;
            };

            return observable;
        }

        function initialise() {
            initCallbacks.forEach(function (fn) {
                fn();
            });
        }

        beforeEach(function () {
            jasmine.clock().install();
            factory = amd.load('Artimus_Matomo/js/model/user-id-resolver');
            section = makeSection({});
            initCallbacks = [];
            expired = [];
            customerData = {
                getInitCustomerData: function () {
                    return {
                        done: function (fn) {
                            initCallbacks.push(fn);
                        }
                    };
                },
                getExpiredSectionNames: function () {
                    return expired;
                },
                get: function (name) {
                    return name === 'matomo' ? section : undefined;
                }
            };
            callback = jasmine.createSpy('callback');
            resolver = factory.create(customerData, window);
        });

        it('resolves the cached user id immediately when the matomo section is not expired', function () {
            section = makeSection({userId: HASH});
            resolver.resolve(1000, callback);
            initialise();

            expect(callback).toHaveBeenCalledWith(HASH);
        });

        it('resolves null immediately for a guest whose matomo section is not expired', function () {
            resolver.resolve(1000, callback);
            initialise();

            expect(callback).toHaveBeenCalledWith(null);
        });

        it('waits for the reloaded matomo section when it is expired and resolves its user id', function () {
            expired = ['matomo'];
            resolver.resolve(1000, callback);
            initialise();

            expect(callback).not.toHaveBeenCalled();
            section.update({userId: HASH});

            expect(callback).toHaveBeenCalledWith(HASH);
        });

        it('resolves null when the reloaded matomo section has no user id', function () {
            expired = ['matomo'];
            resolver.resolve(1000, callback);
            initialise();
            section.update({});

            expect(callback).toHaveBeenCalledWith(null);
        });

        it('resolves with the current value when the timeout elapses before customer data initialises', function () {
            section = makeSection({userId: HASH});
            resolver.resolve(1000, callback);
            jasmine.clock().tick(999);

            expect(callback).not.toHaveBeenCalled();
            jasmine.clock().tick(1);

            expect(callback).toHaveBeenCalledWith(HASH);
        });

        it('resolves with the current value when the timeout elapses before an expired section reloads', function () {
            expired = ['matomo'];
            resolver.resolve(1000, callback);
            initialise();
            jasmine.clock().tick(1000);

            expect(callback).toHaveBeenCalledWith(null);
        });

        it('calls the callback exactly once and disposes its subscription and timer', function () {
            expired = ['matomo'];
            resolver.resolve(1000, callback);
            initialise();
            section.update({userId: HASH});
            section.update({userId: OTHER});
            jasmine.clock().tick(5000);

            expect(callback).toHaveBeenCalledTimes(1);
            expect(section.lastSubscription.dispose).toHaveBeenCalled();
            expect(section.subscriberCount()).toBe(0);
        });

        it('ignores a section update that arrives after the timeout has resolved', function () {
            expired = ['matomo'];
            resolver.resolve(1000, callback);
            initialise();
            jasmine.clock().tick(1000);
            section.update({userId: HASH});

            expect(callback).toHaveBeenCalledTimes(1);
            expect(callback).toHaveBeenCalledWith(null);
        });

        it('resolves null for a reloaded section that only carries data_id', function () {
            expired = ['matomo'];
            resolver.resolve(1000, callback);
            initialise();
            section.update({data_id: 1234});

            expect(callback).toHaveBeenCalledWith(null);
        });

        it('resolves null immediately without waiting for an empty never-loaded section that is not expired', function () {
            resolver.resolve(1000, callback);
            initialise();

            expect(callback).toHaveBeenCalledWith(null);
            expect(section.subscribe).not.toHaveBeenCalled();
        });

        it('resolves immediately when customer data is already initialised and the section reload has already landed', function () {
            section = makeSection({userId: HASH, data_id: 99});
            customerData.getInitCustomerData = function () {
                return {
                    done: function (fn) {
                        fn();
                    }
                };
            };
            resolver.resolve(1000, callback);

            expect(callback).toHaveBeenCalledWith(HASH);
            expect(callback).toHaveBeenCalledTimes(1);
        });
    });
});
