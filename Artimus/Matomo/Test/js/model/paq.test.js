/**
 * Copyright © Artimus Code. All rights reserved.
 */
define([
    'Artimus_Matomo/js/model/paq'
], function (paqModule) {
    'use strict';

    describe('Artimus_Matomo/js/model/paq', function () {
        var paq;

        beforeEach(function () {
            window._paq = [];
            paq = paqModule.create();
        });

        afterEach(function () {
            delete window._paq;
        });

        it('buffers companion commands until init and flushes them in order after the base commands', function () {
            paq.push(['setCustomUrl', '/checkout/shipping']);
            paq.push(['trackPageView', 'Shipping']);

            expect(window._paq).toEqual([]);
            expect(paq.isReady()).toBe(false);

            window._paq.push(['setSiteId', 1], ['trackPageView']);
            paq.init();

            expect(paq.isReady()).toBe(true);
            expect(window._paq).toEqual([
                ['setSiteId', 1],
                ['trackPageView'],
                ['setCustomUrl', '/checkout/shipping'],
                ['trackPageView', 'Shipping']
            ]);
        });

        it('pushes straight to window._paq after init', function () {
            paq.init();
            paq.push(['trackEvent', 'cart', 'update']);

            expect(window._paq).toEqual([['trackEvent', 'cart', 'update']]);
        });

        it('flushes the buffer only once when init is called again', function () {
            paq.push(['setUserId', 'abc']);
            paq.init();
            paq.init();

            expect(window._paq).toEqual([['setUserId', 'abc']]);
        });

        it('creates window._paq when init runs before it exists', function () {
            delete window._paq;
            paq.push(['trackPageView']);
            paq.init();

            expect(window._paq).toEqual([['trackPageView']]);
        });

        it('paq: it runs whenReady callbacks after flushing the buffer', function () {
            var seen = [];

            paq.push(['setUserId', 'abc']);
            paq.whenReady(function () {
                seen.push(window._paq.slice());
                paq.push(['late']);
            });
            expect(seen).toEqual([]);

            paq.init();

            expect(seen).toEqual([[['setUserId', 'abc']]]);
            expect(window._paq).toEqual([['setUserId', 'abc'], ['late']]);
        });

        it('paq: it runs a whenReady callback immediately when already ready', function () {
            var fn = jasmine.createSpy('fn');

            paq.init();
            paq.whenReady(fn);

            expect(fn).toHaveBeenCalledTimes(1);
        });

        it('paq: it runs whenReady callbacks only once when init is called again', function () {
            var fn = jasmine.createSpy('fn');

            paq.whenReady(fn);
            paq.init();
            paq.init();

            expect(fn).toHaveBeenCalledTimes(1);
        });

        it('stores the initial user id, defaulting to null', function () {
            expect(paq.getInitialUserId()).toBeNull();
            paq.setInitialUserId('abc');
            expect(paq.getInitialUserId()).toBe('abc');
        });
    });
});
