/**
 * Copyright © Artimus Code. All rights reserved.
 */
define([], function () {
    'use strict';

    var childProcess = require('child_process'),
        path = require('path'),
        runner = path.join(__dirname, 'run.js'),
        fixtures = path.join(__dirname, '_runner-fixtures');

    /**
     * @param {String[]} args
     * @return {Object}
     */
    function run(args) {
        var result = childProcess.spawnSync(process.execPath, [runner].concat(args), {encoding: 'utf8'});

        return {status: result.status, out: result.stdout + result.stderr};
    }

    describe('Test/js/run.js', function () {
        it('discovers and runs every *.test.js file under Test/js', function () {
            var result = run(['paq.test.js']);

            expect(result.status).toBe(0);
            expect(result.out).toContain('Artimus_Matomo/js/model/paq');
        });

        it('resolves Artimus_Matomo/js/* module ids to view/frontend/web/js files', function () {
            var paq = amd.load('Artimus_Matomo/js/model/paq');

            expect(typeof paq.create).toBe('function');
        });

        it('lets a spec replace a module id with a stub', function () {
            var result = run([path.join(fixtures, 'stub.fixture.js')]);

            expect(result.out).toContain('1 passed, 0 failed');
            expect(result.status).toBe(0);
        });

        it('exits with a non-zero code when any spec fails', function () {
            var result = run([path.join(fixtures, 'failing.fixture.js')]);

            expect(result.status).not.toBe(0);
            expect(result.out).toContain('0 passed, 1 failed');
        });

        it('supports async specs that take a done callback', function () {
            var result = run([path.join(fixtures, 'async.fixture.js')]);

            expect(result.status).toBe(0);
            expect(result.out).toContain('1 passed, 0 failed');
        });

        it('advances fake timers with jasmine.clock().tick', function () {
            var result = run([path.join(fixtures, 'clock.fixture.js')]);

            expect(result.status).toBe(0);
            expect(result.out).toContain('1 passed, 0 failed');
        });

        it('loads a fresh module instance on every amd.load call', function () {
            expect(amd.load('Artimus_Matomo/js/model/paq')).not.toBe(amd.load('Artimus_Matomo/js/model/paq'));
        });

        it('throws a clear error naming an unstubbed non-Artimus module id', function () {
            var message = '';

            try {
                amd.load('Artimus_Matomo/Test/js/_runner-fixtures/needs-jquery');
            } catch (e) {
                message = e.message;
            }

            expect(message).toContain('"jquery"');
        });

        it('fails an async spec that never calls done instead of hanging', function () {
            var result = run([path.join(fixtures, 'never-done.fixture.js')]);

            expect(result.status).not.toBe(0);
            expect(result.out).toContain('0 passed, 1 failed');
        });

        it('does not discover fixture specs under _runner-fixtures', function () {
            var result = run(['sentinel']);

            expect(result.out).not.toContain('SENTINEL_SPEC_RAN');
            expect(result.status).not.toBe(0);
        });
    });
});
