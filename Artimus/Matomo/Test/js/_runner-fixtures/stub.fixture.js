define(['Artimus_Matomo/js/model/paq'], function (paqModule) {
    'use strict';

    describe('stub fixture', function () {
        it('replaces a module id with a stub', function () {
            var loaded = amd.load('Artimus_Matomo/Test/js/_runner-fixtures/needs-jquery', {jquery: {stubbed: true}});

            expect(loaded.jquery).toEqual({stubbed: true});
            expect(typeof paqModule.create).toBe('function');
        });
    });
});
