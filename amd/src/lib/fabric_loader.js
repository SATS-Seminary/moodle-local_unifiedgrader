/**
 * Fabric.js loader for the unified grader.
 *
 * Loads Fabric.js via RequireJS from the thirdparty directory.
 * The Fabric.js UMD build is AMD-compatible and registers with define().
 *
 * @module     local_unifiedgrader/lib/fabric_loader
 * @copyright  2025 SATS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    /** @type {?object} Cached Fabric.js library reference. */
    let fabricLib = null;

    /** @type {?Promise} Loading promise to prevent duplicate loads. */
    let loadPromise = null;

    return {
        /**
         * Load the Fabric.js library.
         *
         * @returns {Promise<object>} The fabric namespace.
         */
        load: function() {
            if (fabricLib) {
                return Promise.resolve(fabricLib);
            }
            if (loadPromise) {
                return loadPromise;
            }

            const url = M.cfg.wwwroot + '/local/unifiedgrader/thirdparty/fabric/fabric.js';

            loadPromise = new Promise(function(resolve, reject) {
                // Use RequireJS require() to load the UMD build.
                // RequireJS handles AMD detection in the UMD wrapper.
                require([url], function(fabric) {
                    fabricLib = fabric;
                    resolve(fabricLib);
                }, function(err) {
                    reject(err);
                });
            });

            return loadPromise;
        },
    };
});
