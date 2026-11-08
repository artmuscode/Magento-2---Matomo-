/**
 * Copyright © Artimus Code. All rights reserved.
 */
define(['Artimus_Matomo/js/model/step-page-view'], function (createStepPageView) {
    'use strict';

    describe('Artimus_Matomo/js/model/step-page-view', function () {
        var tracker;

        beforeEach(function () {
            tracker = createStepPageView('https://shop.test/checkout/');
        });

        it('it builds setCustomUrl setDocumentTitle and trackPageView commands for a newly visible step', function () {
            expect(tracker.commandsFor({code: 'shipping', title: 'Shipping'})).toEqual([
                ['setCustomUrl', 'https://shop.test/checkout/#shipping'],
                ['setDocumentTitle', 'Checkout: Shipping'],
                ['trackPageView']
            ]);
        });

        it('it uses the step code as the url fragment', function () {
            expect(tracker.commandsFor({code: 'payment', title: 'Review & Payments'})[0]).toEqual(
                ['setCustomUrl', 'https://shop.test/checkout/#payment']
            );
        });

        it('it produces no commands when the same step becomes visible twice in a row', function () {
            tracker.commandsFor({code: 'payment', title: 'Payment'});

            expect(tracker.commandsFor({code: 'payment', title: 'Payment'})).toEqual([]);
        });

        it('it produces commands again when returning to an earlier step', function () {
            tracker.commandsFor({code: 'payment', title: 'Payment'});
            tracker.commandsFor({code: 'shipping', title: 'Shipping'});

            expect(tracker.commandsFor({code: 'payment', title: 'Payment'}).length).toBe(3);
        });
    });
});
