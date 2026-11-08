/**
 * Copyright © Artimus Code. All rights reserved.
 */
define([], function () {
    'use strict';

    var EMPTY_SIGNATURE = '';

    /**
     * Magento_Tax replaces the price with {incl_tax, excl_tax} when both are displayed.
     *
     * @param {Number|String|Object} value
     * @return {Number}
     */
    function normalizePrice(value) {
        var price = value !== null && typeof value === 'object' ? value.excl_tax : value;

        return parseFloat(price) || 0;
    }

    /**
     * @param {Array} items
     * @param {Number} subtotal
     * @return {String}
     */
    function buildSignature(items, subtotal) {
        if (!items.length) {
            return EMPTY_SIGNATURE;
        }

        return items.map(function (item) {
            return item.product_sku + ':' + item.qty;
        }).sort().join(',') + '|' + subtotal;
    }

    /**
     * Decide what to send for a cart section snapshot. Pure: storage and _paq stay with the caller.
     *
     * @param {Object} cart customer-data `cart` section
     * @param {String|null} lastSignature signature stored after the previous send
     * @param {Boolean} isSuccessPage order success page, where the cart clears because of the order
     * @param {Boolean} isCheckoutPage checkout page, where Luma reloads the emptied cart right after
     *     place-order (before the success redirect); the cart can't be emptied there any other way
     * @return {{commands: Array, signature: String}} signature is what the caller should store
     */
    return function (cart, lastSignature, isSuccessPage, isCheckoutPage) {
        var items = cart.items || [],
            subtotal = parseFloat(cart.subtotalAmount) || 0,
            signature = buildSignature(items, subtotal),
            previous = lastSignature || EMPTY_SIGNATURE,
            commands = [];

        if (isSuccessPage || isCheckoutPage && !items.length) {
            return {commands: [], signature: EMPTY_SIGNATURE};
        }

        if (signature === previous) {
            return {commands: commands, signature: signature};
        }

        if (!items.length) {
            commands.push(['clearEcommerceCart']);
        }

        items.forEach(function (item) {
            commands.push([
                'addEcommerceItem',
                item.product_sku,
                item.product_name,
                false,
                normalizePrice(item.product_price_value),
                parseInt(item.qty, 10) || 0
            ]);
        });
        commands.push(['trackEcommerceCartUpdate', subtotal]);

        return {commands: commands, signature: signature};
    };
});
