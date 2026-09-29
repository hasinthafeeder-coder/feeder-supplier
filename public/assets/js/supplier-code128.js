/**
 * Minimal Code 128B barcode SVG encoder for supplier print documents.
 * No external barcode dependency — generates scannable CODE128 bars.
 */
(function (global) {
    'use strict';

    var CODE128_B_START = 104;
    var CODE128_STOP = 106;

    // Patterns for Code 128 values 0–106 (bars/spaces widths, 6 digits each).
    var PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112'
    ];

    function encodeValue(charCode) {
        if (charCode >= 32 && charCode <= 127) {
            return charCode - 32;
        }

        return null;
    }

    function buildModules(text) {
        var value = String(text || '');
        var codes = [CODE128_B_START];
        var checksum = CODE128_B_START;

        for (var i = 0; i < value.length; i += 1) {
            var code = encodeValue(value.charCodeAt(i));
            if (code === null) {
                // Fallback: replace unsupported characters with space (Code 128B value 0).
                code = 0;
            }
            codes.push(code);
            checksum += code * (i + 1);
        }

        codes.push(checksum % 103);
        codes.push(CODE128_STOP);

        var modules = '';
        for (var c = 0; c < codes.length; c += 1) {
            modules += PATTERNS[codes[c]] || '';
        }

        return modules;
    }

    function toSvg(text, options) {
        options = options || {};
        var height = options.height || 48;
        var moduleWidth = options.moduleWidth || 1.5;
        var quietModules = options.quietZoneModules != null ? options.quietZoneModules : 10;
        var modules = buildModules(text);
        var quietWidth = quietModules * moduleWidth;
        var x = quietWidth;
        var bars = '';
        var isBar = true;

        for (var i = 0; i < modules.length; i += 1) {
            var width = parseInt(modules.charAt(i), 10) * moduleWidth;
            if (isBar) {
                bars += '<rect x="' + x.toFixed(2) + '" y="0" width="' + width.toFixed(2) + '" height="' + height + '" fill="#000"/>';
            }
            x += width;
            isBar = !isBar;
        }

        var totalWidth = x + quietWidth;
        var label = String(text || '');
        var labelHeight = options.showText === false ? 0 : 14;
        var svgHeight = height + labelHeight;

        var labelMarkup = '';
        if (options.showText !== false) {
            labelMarkup = '<text x="' + (totalWidth / 2).toFixed(2) + '" y="' + (height + 12) +
                '" text-anchor="middle" font-family="monospace" font-size="11">' +
                label.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') +
                '</text>';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="' + totalWidth.toFixed(2) +
            '" height="' + svgHeight + '" viewBox="0 0 ' + totalWidth.toFixed(2) + ' ' + svgHeight +
            '" role="img" aria-label="Barcode ' + label.replace(/"/g, '') + '">' +
            '<rect x="0" y="0" width="' + totalWidth.toFixed(2) + '" height="' + height + '" fill="#fff"/>' +
            bars + labelMarkup + '</svg>';
    }

    global.SupplierCode128 = {
        toSvg: toSvg,
    };
})(window);
