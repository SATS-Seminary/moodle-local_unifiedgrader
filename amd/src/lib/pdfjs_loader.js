/**
 * PDF.js loader for the unified grader.
 *
 * Loads PDF.js via native dynamic import from the thirdparty directory.
 * Uses new Function() to preserve the import() call from Rollup transformation,
 * which would otherwise convert it to a RequireJS require() that cannot load ES modules.
 *
 * @module     local_unifiedgrader/lib/pdfjs_loader
 * @copyright  2025 SATS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    /** @type {?object} Cached PDF.js library reference. */
    let pdfjsLib = null;

    /** @type {?Promise} Loading promise to prevent duplicate loads. */
    let loadPromise = null;

    /**
     * Native dynamic import that survives Rollup bundling.
     *
     * Rollup transforms import() into require() for AMD output, which breaks
     * loading of ES module files. Wrapping in new Function prevents Rollup
     * from seeing and transforming the import expression.
     *
     * @param {string} url The module URL to import.
     * @returns {Promise<object>} The imported module namespace.
     */
    // eslint-disable-next-line no-new-func
    const nativeImport = new Function('url', 'return import(url)');

    return {
        /**
         * Load the PDF.js library.
         *
         * @returns {Promise<object>} The pdfjsLib namespace.
         */
        load: function() {
            if (pdfjsLib) {
                return Promise.resolve(pdfjsLib);
            }
            if (loadPromise) {
                return loadPromise;
            }

            const base = M.cfg.wwwroot + '/local/unifiedgrader/thirdparty/pdfjs';

            loadPromise = nativeImport(base + '/pdf.min.js').then(function(lib) {
                pdfjsLib = lib;
                pdfjsLib.GlobalWorkerOptions.workerSrc = base + '/pdf.worker.min.js';
                return pdfjsLib;
            });

            return loadPromise;
        },
    };
});
