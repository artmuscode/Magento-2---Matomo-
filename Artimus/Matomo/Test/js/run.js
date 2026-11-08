/**
 * Copyright © Artimus Code. All rights reserved.
 *
 * Zero-dependency runner for the module's RequireJS (AMD) Jasmine-style specs.
 *
 * Usage: node run.js [file-path | substring-filter]
 */
'use strict';

var fs = require('fs'),
    path = require('path'),
    vm = require('vm'),
    util = require('util'),
    realSetTimeout = setTimeout,
    realClearTimeout = clearTimeout,
    ASYNC_TIMEOUT_MS = 5000,
    MODULE_ROOT = path.resolve(__dirname, '..', '..'),
    WEB_JS_ROOT = path.join(MODULE_ROOT, 'view', 'frontend', 'web', 'js'),
    MODULE_PREFIX = 'Artimus_Matomo/',
    JS_PREFIX = MODULE_PREFIX + 'js/',
    pendingDefine = null,
    currentSpec = null,
    sharedRegistry = {},
    rootSuite = {name: '', children: [], beforeEach: [], afterEach: [], parent: null},
    currentSuite = rootSuite,
    activeSpies = [],
    clock;

// ---------------------------------------------------------------- AMD loader

/**
 * @param {String} id
 * @return {String}
 */
function resolveFile(id) {
    if (id.indexOf(JS_PREFIX) === 0) {
        return path.join(WEB_JS_ROOT, id.substring(JS_PREFIX.length) + '.js');
    }

    return path.join(MODULE_ROOT, id.substring(MODULE_PREFIX.length) + '.js');
}

/**
 * Evaluate a file and return the arguments of its (anonymous) define() call.
 *
 * @param {String} file
 * @return {Object}
 */
function evaluate(file) {
    var source = fs.readFileSync(file, 'utf8'),
        wrapper = vm.runInThisContext('(function (require, __dirname) {' + source + '\n})', {filename: file});

    pendingDefine = null;
    wrapper(require, path.dirname(file));

    if (!pendingDefine) {
        throw new Error('No define() call in ' + file);
    }

    return pendingDefine;
}

/**
 * @param {String[]} deps
 * @param {Function} factory
 * @param {Object} context {stubs, cache}
 * @return {*}
 */
function instantiate(deps, factory, context) {
    return factory.apply(null, deps.map(function (dep) {
        return resolve(dep, context);
    }));
}

/**
 * @param {String} id
 * @param {Object} context
 * @return {*}
 */
function resolve(id, context) {
    var definition;

    if (Object.prototype.hasOwnProperty.call(context.stubs, id)) {
        return context.stubs[id];
    }

    if (id.indexOf(MODULE_PREFIX) !== 0) {
        throw new Error('Unstubbed module "' + id + '": stub it via amd.load(id, {"' + id + '": ...})');
    }

    if (!Object.prototype.hasOwnProperty.call(context.cache, id)) {
        definition = evaluate(resolveFile(id));
        context.cache[id] = instantiate(definition.deps, definition.factory, context);
    }

    return context.cache[id];
}

global.window = global;
global.define = function (deps, factory) {
    if (typeof deps === 'function') {
        factory = deps;
        deps = [];
    }

    pendingDefine = {deps: deps, factory: factory};
};
global.amd = {
    load: function (id, stubs) {
        return resolve(id, {stubs: stubs || {}, cache: {}});
    }
};

// ------------------------------------------------------------------ Matchers

/**
 * @param {*} value
 * @return {String}
 */
function show(value) {
    return util.inspect(value, {depth: 4, breakLength: Infinity});
}

var matchers = {
    toBe: function (a, e) {
        return {pass: Object.is(a, e), msg: 'to be ' + show(e)};
    },
    toEqual: function (a, e) {
        return {pass: util.isDeepStrictEqual(a, e), msg: 'to equal ' + show(e)};
    },
    toBeNull: function (a) {
        return {pass: a === null, msg: 'to be null'};
    },
    toBeUndefined: function (a) {
        return {pass: a === undefined, msg: 'to be undefined'};
    },
    toBeDefined: function (a) {
        return {pass: a !== undefined, msg: 'to be defined'};
    },
    toBeTruthy: function (a) {
        return {pass: !!a, msg: 'to be truthy'};
    },
    toBeFalsy: function (a) {
        return {pass: !a, msg: 'to be falsy'};
    },
    toContain: function (a, e) {
        var has = typeof a === 'string' ?
            a.indexOf(e) !== -1 :
            Array.prototype.some.call(a, function (item) {
                return util.isDeepStrictEqual(item, e);
            });

        return {pass: has, msg: 'to contain ' + show(e)};
    },
    toBeGreaterThan: function (a, e) {
        return {pass: a > e, msg: 'to be greater than ' + show(e)};
    },
    toBeLessThan: function (a, e) {
        return {pass: a < e, msg: 'to be less than ' + show(e)};
    },
    toThrow: function (a) {
        var threw = false;

        try {
            a();
        } catch (e) {
            threw = true;
        }

        return {pass: threw, msg: 'to throw'};
    },
    toHaveBeenCalled: function (a) {
        return {pass: a.calls.count() > 0, msg: 'to have been called'};
    },
    toHaveBeenCalledTimes: function (a, e) {
        return {pass: a.calls.count() === e, msg: 'to have been called ' + e + ' times (was ' + a.calls.count() + ')'};
    },
    toHaveBeenCalledWith: function (a) {
        var expected = Array.prototype.slice.call(arguments, 1);

        return {
            pass: a.calls.all().some(function (call) {
                return util.isDeepStrictEqual(call.args, expected);
            }),
            msg: 'to have been called with ' + show(expected) + ' (calls: ' + show(a.calls.all().map(function (c) {
                return c.args;
            })) + ')'
        };
    }
};

global.expect = function (actual) {
    var api = {not: {}};

    Object.keys(matchers).forEach(function (name) {
        [false, true].forEach(function (negate) {
            (negate ? api.not : api)[name] = function () {
                var args = [actual].concat(Array.prototype.slice.call(arguments)),
                    result = matchers[name].apply(null, args);

                if (result.pass === negate) {
                    throw new Error('Expected ' + show(actual) + (negate ? ' not ' : ' ') + result.msg);
                }
            };
        });
    });

    return api;
};

// -------------------------------------------------------------------- Spies

/**
 * @param {String} name
 * @param {Function} [original]
 * @return {Function}
 */
function createSpy(name, original) {
    var calls = [],
        strategy = function () {
            return undefined;
        },
        spy = function () {
            var args = Array.prototype.slice.call(arguments);

            calls.push({object: this, args: args});

            return strategy.apply(this, args);
        };

    spy.and = {
        returnValue: function (value) {
            strategy = function () {
                return value;
            };

            return spy;
        },
        callFake: function (fn) {
            strategy = fn;

            return spy;
        },
        callThrough: function () {
            strategy = original || function () {
                return undefined;
            };

            return spy;
        }
    };
    spy.calls = {
        count: function () {
            return calls.length;
        },
        argsFor: function (i) {
            return calls[i] && calls[i].args;
        },
        mostRecent: function () {
            return calls[calls.length - 1];
        },
        all: function () {
            return calls.slice();
        },
        reset: function () {
            calls.length = 0;
        }
    };
    spy.spyName = name;

    return spy;
}

global.spyOn = function (object, method) {
    var original = object[method],
        spy = createSpy(method, original);

    activeSpies.push(function () {
        object[method] = original;
    });
    object[method] = spy;

    return spy;
};

// --------------------------------------------------------------------- Clock

clock = {
    timers: [],
    now: 0,
    seq: 0,
    installed: false
};

/**
 * @param {Boolean} repeat
 * @return {Function}
 */
function fakeTimer(repeat) {
    return function (fn, delay) {
        var timer = {
            id: ++clock.seq,
            fn: fn,
            at: clock.now + (delay || 0),
            interval: repeat ? Math.max(delay || 0, 1) : null,
            args: Array.prototype.slice.call(arguments, 2)
        };

        clock.timers.push(timer);

        return timer.id;
    };
}

/**
 * @param {Number} id
 */
function fakeClear(id) {
    clock.timers = clock.timers.filter(function (timer) {
        return timer.id !== id;
    });
}

/**
 * @return {Object|undefined}
 */
function nextDueTimer(limit) {
    return clock.timers.filter(function (timer) {
        return timer.at <= limit;
    }).sort(function (a, b) {
        return a.at - b.at || a.id - b.id;
    })[0];
}

global.jasmine = {
    createSpy: createSpy,
    clock: function () {
        return {
            install: function () {
                clock.installed = true;
                clock.timers = [];
                clock.now = 0;
                global.setTimeout = fakeTimer(false);
                global.setInterval = fakeTimer(true);
                global.clearTimeout = global.clearInterval = fakeClear;
            },
            uninstall: function () {
                clock.installed = false;
                clock.timers = [];
                global.setTimeout = realSetTimeout;
                global.clearTimeout = realClearTimeout;
                global.setInterval = setInterval;
                global.clearInterval = clearInterval;
            },
            tick: function (ms) {
                var limit = clock.now + ms,
                    timer = nextDueTimer(limit);

                while (timer) {
                    clock.now = Math.max(clock.now, timer.at);

                    if (timer.interval) {
                        timer.at += timer.interval;
                    } else {
                        fakeClear(timer.id);
                    }

                    timer.fn.apply(null, timer.args);
                    timer = nextDueTimer(limit);
                }

                clock.now = limit;
            }
        };
    }
};

// --------------------------------------------------------------- Suite DSL

global.describe = function (name, body) {
    var suite = {name: name, children: [], beforeEach: [], afterEach: [], parent: currentSuite};

    currentSuite.children.push(suite);
    currentSuite = suite;

    try {
        body();
    } finally {
        currentSuite = suite.parent;
    }
};
global.it = function (name, fn) {
    currentSuite.children.push({name: name, fn: fn, parent: currentSuite});
};
global.beforeEach = function (fn) {
    currentSuite.beforeEach.push(fn);
};
global.afterEach = function (fn) {
    currentSuite.afterEach.push(fn);
};

// ---------------------------------------------------------------- Execution

/**
 * Run fn, awaiting `done` when it declares a parameter. Rejects on throw or timeout.
 *
 * @param {Function} fn
 * @return {Promise}
 */
function runFunction(fn) {
    return new Promise(function (resolvePromise, reject) {
        var timer;

        if (fn.length === 0) {
            try {
                fn();
                resolvePromise();
            } catch (e) {
                reject(e);
            }

            return;
        }

        timer = realSetTimeout(function () {
            reject(new Error('Timed out after ' + ASYNC_TIMEOUT_MS + 'ms waiting for done()'));
        }, ASYNC_TIMEOUT_MS);
        currentSpec.fail = function (e) {
            realClearTimeout(timer);
            reject(e);
        };

        try {
            fn(function () {
                realClearTimeout(timer);
                resolvePromise();
            });
        } catch (e) {
            realClearTimeout(timer);
            reject(e);
        }
    });
}

/**
 * @param {Object} spec
 * @return {String}
 */
function fullName(spec) {
    var names = [],
        node = spec;

    while (node && node.parent) {
        names.unshift(node.name);
        node = node.parent;
    }

    return names.join(' ');
}

/**
 * @param {Object} spec
 * @return {Object[]} suites from outermost to innermost
 */
function suiteChain(spec) {
    var chain = [],
        suite = spec.parent;

    while (suite) {
        chain.unshift(suite);
        suite = suite.parent;
    }

    return chain;
}

/**
 * @param {Object} spec
 * @return {Promise<Error|null>}
 */
async function runSpec(spec) {
    var chain = suiteChain(spec),
        hooks = [],
        failure = null,
        i;

    currentSpec = spec;
    chain.forEach(function (suite) {
        hooks.push.apply(hooks, suite.beforeEach);
    });
    hooks.push(spec.fn);

    try {
        for (i = 0; i < hooks.length; i++) {
            await runFunction(hooks[i]);
        }
    } catch (e) {
        failure = e;
    }

    chain.reverse().forEach(function (suite) {
        suite.afterEach.slice().reverse().forEach(function (fn) {
            try {
                fn();
            } catch (e) {
                failure = failure || e;
            }
        });
    });

    activeSpies.splice(0).reverse().forEach(function (restore) {
        restore();
    });
    global.jasmine.clock().uninstall();
    currentSpec = null;

    return failure;
}

/**
 * @param {Object} suite
 * @return {Object[]}
 */
function collectSpecs(suite) {
    return suite.children.reduce(function (specs, child) {
        return specs.concat(child.fn ? [child] : collectSpecs(child));
    }, []);
}

/**
 * @param {String} dir
 * @return {String[]}
 */
function discover(dir) {
    return fs.readdirSync(dir, {withFileTypes: true}).reduce(function (files, entry) {
        var full = path.join(dir, entry.name);

        if (entry.isDirectory()) {
            return entry.name === '_runner-fixtures' ? files : files.concat(discover(full));
        }

        return /\.test\.js$/.test(entry.name) ? files.concat(full) : files;
    }, []).sort();
}

/**
 * @param {String|undefined} arg
 * @return {String[]}
 */
function selectFiles(arg) {
    var explicit = arg && path.resolve(arg);

    if (explicit && fs.existsSync(explicit) && fs.statSync(explicit).isFile()) {
        return [explicit];
    }

    return discover(__dirname).filter(function (file) {
        return !arg || file.indexOf(arg) !== -1;
    });
}

process.on('uncaughtException', function (e) {
    if (currentSpec && currentSpec.fail) {
        currentSpec.fail(e);
    } else {
        console.error(e);
        process.exit(1);
    }
});

(async function main() {
    var files = selectFiles(process.argv[2]),
        passed = 0,
        failed = 0,
        loadErrors = 0,
        specs,
        i,
        failure;

    files.forEach(function (file) {
        try {
            var definition = evaluate(file);

            instantiate(definition.deps, definition.factory, {stubs: {}, cache: sharedRegistry});
        } catch (e) {
            loadErrors++;
            console.log('LOAD ERROR ' + path.relative(__dirname, file) + ': ' + e.message);
        }
    });

    if (!files.length) {
        console.log('No spec files matched');
    }

    specs = collectSpecs(rootSuite);

    for (i = 0; i < specs.length; i++) {
        failure = await runSpec(specs[i]);

        if (failure) {
            failed++;
            console.log('FAIL ' + fullName(specs[i]) + '\n     ' + (failure && failure.message || failure));
        } else {
            passed++;
            console.log('PASS ' + fullName(specs[i]));
        }
    }

    console.log('\n' + passed + ' passed, ' + failed + ' failed');
    process.exit(failed || loadErrors || !files.length ? 1 : 0);
})();
