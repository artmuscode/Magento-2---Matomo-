/**
 * Copyright © Artimus Code. All rights reserved.
 */
define([
    'Artimus_Matomo/js/model/cart-diff'
], function (cartDiff) {
    'use strict';

    describe('Artimus_Matomo/js/model/cart-diff', function () {
        var cart;

        beforeEach(function () {
            cart = {
                subtotalAmount: 30,
                items: [
                    {product_sku: 'B', product_name: 'Bee', product_price_value: 10, qty: 1},
                    {product_sku: 'A', product_name: 'Ay', product_price_value: 10, qty: 2}
                ]
            };
        });

        it('produces no commands when the cart signature is unchanged', function () {
            var first = cartDiff(cart, null, false),
                second = cartDiff(cart, first.signature, false);

            expect(second.commands).toEqual([]);
            expect(second.signature).toBe(first.signature);
        });

        it('produces an addEcommerceItem command per cart item followed by trackEcommerceCartUpdate with the subtotal', function () {
            var result = cartDiff(cart, null, false);

            expect(result.commands).toEqual([
                ['addEcommerceItem', 'B', 'Bee', false, 10, 1],
                ['addEcommerceItem', 'A', 'Ay', false, 10, 2],
                ['trackEcommerceCartUpdate', 30]
            ]);
        });

        it('produces clearEcommerceCart and a zero cart update when the cart becomes empty', function () {
            var previous = cartDiff(cart, null, false).signature,
                result = cartDiff({subtotalAmount: null, items: []}, previous, false);

            expect(result.commands).toEqual([['clearEcommerceCart'], ['trackEcommerceCartUpdate', 0]]);
        });

        it('produces no commands for an empty cart that was already empty', function () {
            expect(cartDiff({subtotalAmount: null, items: []}, null, false).commands).toEqual([]);
            expect(cartDiff({subtotalAmount: null, items: []}, '', false).commands).toEqual([]);
        });

        it('treats a quantity change as a cart change', function () {
            var previous = cartDiff(cart, null, false).signature,
                result;

            cart.items[0].qty = 2;
            result = cartDiff(cart, previous, false);

            expect(result.commands.length).toBe(3);
            expect(result.signature).not.toBe(previous);
        });

        it('uses the excl tax price when product_price_value is an incl/excl tax object', function () {
            var result;

            cart.items[0].product_price_value = {incl_tax: 12.3, excl_tax: 10};
            result = cartDiff(cart, null, false);

            expect(result.commands[0]).toEqual(['addEcommerceItem', 'B', 'Bee', false, 10, 1]);
        });

        it('resets the stored signature without producing commands on the order success page', function () {
            var previous = cartDiff(cart, null, false).signature,
                result = cartDiff({subtotalAmount: null, items: []}, previous, true);

            expect(result.commands).toEqual([]);
            expect(result.signature).toBe('');
        });

        // Regression (task 014): Luma reloads the emptied cart on the checkout page right after
        // place-order, before redirecting to the success page.
        it('resets the stored signature without producing commands when the cart empties on the checkout page', function () {
            var previous = cartDiff(cart, null, false).signature,
                result = cartDiff({subtotalAmount: null, items: []}, previous, false, true);

            expect(result.commands).toEqual([]);
            expect(result.signature).toBe('');
        });

        it('still reports a non-empty cart change on the checkout page', function () {
            var previous = cartDiff(cart, null, false).signature,
                result;

            cart.items[0].qty = 3;
            cart.subtotalAmount = 50;
            result = cartDiff(cart, previous, false, true);

            expect(result.commands[result.commands.length - 1]).toEqual(['trackEcommerceCartUpdate', 50]);
        });
    });
});
