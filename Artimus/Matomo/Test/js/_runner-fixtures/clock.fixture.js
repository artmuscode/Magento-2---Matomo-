define([], function () {
    'use strict';

    describe('clock fixture', function () {
        beforeEach(function () {
            jasmine.clock().install();
        });

        it('advances timers in order', function () {
            var order = [];

            setTimeout(function () {
                order.push('b');
            }, 20);
            setTimeout(function () {
                order.push('a');
            }, 10);

            jasmine.clock().tick(9);
            expect(order).toEqual([]);
            jasmine.clock().tick(11);
            expect(order).toEqual(['a', 'b']);
        });
    });
});
