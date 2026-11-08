define([], function () {
    'use strict';

    describe('async fixture', function () {
        it('completes via done', function (done) {
            setTimeout(function () {
                expect(true).toBe(true);
                done();
            }, 5);
        });
    });
});
