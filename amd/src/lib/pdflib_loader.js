/**
 * pdf-lib loader for the unified grader.
 *
 * Loads pdf-lib via RequireJS from the thirdparty directory.
 * The pdf-lib UMD build is AMD-compatible and registers with define().
 *
 * @module     local_unifiedgrader/lib/pdflib_loader
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    /** @type {?object} Cached pdf-lib library reference. */
    let pdflibLib = null;

    /** @type {?Promise} Loading promise to prevent duplicate loads. */
    let loadPromise = null;

    return {
        /**
         * Load the pdf-lib library.
         *
         * @returns {Promise<object>} The PDFLib namespace.
         */
        load: function() {
            if (pdflibLib) {
                return Promise.resolve(pdflibLib);
            }
            if (loadPromise) {
                return loadPromise;
            }

            const url = M.cfg.wwwroot + '/local/unifiedgrader/thirdparty/pdflib/pdf-lib.min.js';

            loadPromise = new Promise(function(resolve, reject) {
                // Use RequireJS require() to load the UMD build.
                // RequireJS handles AMD detection in the UMD wrapper.
                require([url], function(PDFLib) {
                    pdflibLib = PDFLib;
                    resolve(pdflibLib);
                }, function(err) {
                    reject(err);
                });
            });

            return loadPromise;
        },
    };
});
