/**
 * Copyright © Artimus Code. All rights reserved.
 */
define([
    'Magento_Customer/js/customer-data',
    'Artimus_Matomo/js/model/cart-diff',
    'Artimus_Matomo/js/model/paq'
], function (customerData, cartDiff, paq) {
    'use strict';

    var STORAGE_KEY = 'artimus-matomo-cart-sig';

    /**
     * @return {String|null}
     */
    function readSignature() {
        try {
            return window.localStorage.getItem(STORAGE_KEY);
        } catch (e) {
            return null;
        }
    }

    /**
     * @param {String} signature
     */
    function writeSignature(signature) {
        try {
            window.localStorage.setItem(STORAGE_KEY, signature);
        } catch (e) {
            // Storage unavailable: worst case is a repeated update.
        }
    }

    /**
     * @param {Object} cart
     */
    function onCartChange(cart) {
        var result;

        // Section is still being fetched or has no data yet.
        if (!cart || !Array.isArray(cart.items)) {
            return;
        }

        result = cartDiff(
            cart,
            readSignature(),
            document.body.classList.contains('checkout-onepage-success'),
            document.body.classList.contains('checkout-index-index')
        );
        result.commands.forEach(function (command) {
            paq.push(command);
        });
        writeSignature(result.signature);
    }

    return function () {
        var cart = customerData.get('cart');

        cart.subscribe(onCartChange);
        onCartChange(cart());
    };
});
