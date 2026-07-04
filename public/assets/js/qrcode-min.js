/**
 * QR Code Generator — Minimal implementation for TOTP setup.
 * Generates QR codes as Canvas or SVG. No external dependencies.
 * Based on nayuki/QR-Code-generator (MIT License).
 *
 * Usage:
 *   QRCode.toCanvas(document.getElementById('canvas'), 'otpauth://totp/...', 200);
 *   QRCode.toSVG('otpauth://totp/...', 200) => SVG string
 */
var QRCode = (function() {
    'use strict';

    // --- GF(256) and Reed-Solomon ---
    var EXP = new Uint8Array(256), LOG = new Uint8Array(256);
    (function() {
        var v = 1;
        for (var i = 0; i < 255; i++) {
            EXP[i] = v;
            LOG[v] = i;
            v = (v << 1) ^ (v >= 128 ? 0x11D : 0);
        }
        EXP[255] = EXP[0];
    })();

    function gfMul(a, b) {
        return (a === 0 || b === 0) ? 0 : EXP[(LOG[a] + LOG[b]) % 255];
    }

    function rsGenPoly(degree) {
        var poly = new Uint8Array(degree + 1);
        poly[0] = 1;
        for (var i = 0; i < degree; i++) {
            for (var j = degree; j >= 1; j--) {
                poly[j] = gfMul(poly[j], EXP[i]) ^ poly[j - 1];
            }
            poly[0] = gfMul(poly[0], EXP[i]);
        }
        return poly;
    }

    function rsRemainder(data, genPoly) {
        var degree = genPoly.length - 1;
        var result = new Uint8Array(degree);
        for (var i = 0; i < data.length; i++) {
            var factor = data[i] ^ result[0];
            result.copyWithin(0, 1);
            result[degree - 1] = 0;
            for (var j = 0; j < degree; j++) {
                result[j] ^= gfMul(genPoly[degree - 1 - j], factor);
            }
        }
        return result;
    }

    // --- QR constants ---
    var ECC_CODEWORDS = [
        // L, M, Q, H per version 1..40
        [7,10,13,17],[10,16,22,28],[15,26,36,44],[20,36,52,64],[26,48,72,88],
        [36,64,96,112],[40,72,108,130],[48,88,132,156],[60,110,160,192],[72,130,192,224],
        [80,150,224,264],[96,176,260,308],[104,198,288,348],[120,216,320,396],[132,240,360,444],
        [144,280,408,480],[168,308,448,532],[180,338,504,588],[196,364,546,650],[224,416,600,700],
        [224,442,644,750],[252,476,690,816],[270,504,750,900],[300,560,810,960],[312,588,870,1050],
        [336,644,952,1110],[360,700,1020,1200],[390,728,1050,1260],[420,784,1140,1350],[450,812,1200,1440],
        [480,868,1290,1530],[510,924,1350,1620],[540,980,1440,1710],[570,1036,1530,1800],[570,1064,1590,1890],
        [600,1120,1680,1980],[630,1204,1770,2100],[660,1260,1860,2220],[720,1316,1950,2310],[750,1372,2040,2430]
    ];

    var NUM_BLOCKS = [
        [1,1,1,1],[1,1,1,1],[1,1,2,2],[1,2,2,4],[1,2,4,4],
        [2,4,4,4],[2,4,6,5],[2,4,6,6],[2,5,8,8],[4,5,8,8],
        [4,5,8,11],[4,8,10,11],[4,9,12,16],[4,9,16,16],[6,10,12,18],
        [6,10,17,16],[6,11,16,19],[6,13,18,21],[7,14,21,25],[8,16,20,25],
        [8,17,23,25],[9,17,23,34],[9,18,25,30],[10,20,27,32],[12,21,29,35],
        [12,23,34,37],[12,25,34,40],[13,26,35,42],[14,28,38,45],[15,29,40,48],
        [16,31,43,51],[17,33,45,54],[18,35,48,57],[19,37,51,60],[19,38,53,63],
        [20,40,56,66],[21,43,59,70],[22,45,62,74],[24,47,65,77],[25,49,68,81]
    ];

    var ALIGN_POS = [
        [], [6,18], [6,22], [6,26], [6,30], [6,34],
        [6,22,38], [6,24,42], [6,26,46], [6,28,50], [6,30,54],
        [6,32,58], [6,34,62], [6,26,46,66], [6,26,48,70], [6,26,50,74],
        [6,30,54,78], [6,30,56,82], [6,30,58,86], [6,34,62,90],
        [6,28,50,72,94], [6,26,50,74,98], [6,30,54,78,102], [6,28,54,80,106],
        [6,32,58,84,110], [6,30,58,86,114], [6,34,62,90,118],
        [6,26,50,74,98,122], [6,30,54,78,102,126], [6,26,52,78,104,130],
        [6,30,56,82,108,134], [6,34,60,86,112,138], [6,30,58,86,114,142],
        [6,34,62,90,118,146],
        [6,30,54,78,102,126,150], [6,24,50,76,102,128,154], [6,28,54,80,106,132,158],
        [6,32,58,84,110,136,162], [6,26,54,82,110,138,166], [6,30,58,86,114,142,170]
    ];

    var FORMAT_BITS = [
        // L, M, Q, H × mask 0..7
        [0x77C4,0x72F3,0x7DAA,0x789D,0x662F,0x6318,0x6C41,0x6976],
        [0x5412,0x5125,0x5E7C,0x5B4B,0x45F9,0x40CE,0x4F97,0x4AA0],
        [0x355F,0x3068,0x3F31,0x3A06,0x24B4,0x2183,0x2EDA,0x2BED],
        [0x1689,0x13BE,0x1CE7,0x19D0,0x0762,0x0255,0x0D0C,0x083B]
    ];

    // --- Encode text to byte mode segments ---
    function textToBytes(text) {
        var bytes = [];
        for (var i = 0; i < text.length; i++) {
            var c = text.charCodeAt(i);
            if (c < 0x80) bytes.push(c);
            else if (c < 0x800) { bytes.push(0xC0 | (c >> 6)); bytes.push(0x80 | (c & 0x3F)); }
            else if (c < 0xD800 || c >= 0xE000) { bytes.push(0xE0 | (c >> 12)); bytes.push(0x80 | ((c >> 6) & 0x3F)); bytes.push(0x80 | (c & 0x3F)); }
            else { i++; c = 0x10000 + (((c & 0x3FF) << 10) | (text.charCodeAt(i) & 0x3FF)); bytes.push(0xF0 | (c >> 18)); bytes.push(0x80 | ((c >> 12) & 0x3F)); bytes.push(0x80 | ((c >> 6) & 0x3F)); bytes.push(0x80 | (c & 0x3F)); }
        }
        return bytes;
    }

    function chooseVersion(dataLen, eccIdx) {
        for (var ver = 1; ver <= 40; ver++) {
            var cap = getDataCapacity(ver, eccIdx);
            if (dataLen <= cap) return ver;
        }
        throw new Error('Data too long for QR');
    }

    function getDataCapacity(ver, eccIdx) {
        var size = ver * 4 + 17;
        var totalModules = size * size;
        var funcModules = 64 * 3 + 15 * 2 + 1 + (ver >= 2 ? (function() {
            var a = ALIGN_POS[ver - 1];
            var n = a.length;
            return 25 * n * n - 10 * (n > 0 ? n * 2 - 1 : 0) - 50 * (n > 0 ? 1 : 0);
        })() : 0) + (ver >= 7 ? 36 : 0) + 31;
        var totalCodewords = Math.floor((totalModules - funcModules) / 8);
        var eccCW = ECC_CODEWORDS[ver - 1][eccIdx];
        return totalCodewords - eccCW;
    }

    function encode(text, eccLevel) {
        var eccIdx = {L:0, M:1, Q:2, H:3}[eccLevel] || 1;
        var dataBytes = textToBytes(text);
        var ver = chooseVersion(dataBytes.length + 3, eccIdx);

        // Build data codewords
        var cap = getDataCapacity(ver, eccIdx);
        var bits = [];
        // Mode indicator (byte = 0100)
        bits.push(0,1,0,0);
        // Character count
        var ccBits = ver <= 9 ? 8 : 16;
        for (var i = ccBits - 1; i >= 0; i--) bits.push((dataBytes.length >> i) & 1);
        // Data
        for (var i = 0; i < dataBytes.length; i++) {
            for (var j = 7; j >= 0; j--) bits.push((dataBytes[i] >> j) & 1);
        }
        // Terminator + padding
        for (var i = 0; i < 4 && bits.length < cap * 8; i++) bits.push(0);
        while (bits.length % 8 !== 0) bits.push(0);
        while (bits.length < cap * 8) {
            bits.push(1,1,1,0,1,1,0,0); // 0xEC
            if (bits.length < cap * 8) bits.push(0,0,0,1,0,0,0,1); // 0x11
        }

        var dataCW = new Uint8Array(cap);
        for (var i = 0; i < cap; i++) {
            var b = 0;
            for (var j = 0; j < 8; j++) b = (b << 1) | bits[i * 8 + j];
            dataCW[i] = b;
        }

        // Error correction
        var numBlocks = NUM_BLOCKS[ver - 1][eccIdx];
        var totalEcc = ECC_CODEWORDS[ver - 1][eccIdx];
        var eccPerBlock = Math.floor(totalEcc / numBlocks);
        var shortBlocks = numBlocks - (cap % numBlocks === 0 ? 0 : cap % numBlocks);

        // Split data into blocks...simplified
        var blockDataLen = Math.floor(cap / numBlocks);
        var blocks = [];
        var offset = 0;
        for (var i = 0; i < numBlocks; i++) {
            var len = blockDataLen + (i >= shortBlocks && cap % numBlocks !== 0 ? 1 : 0);
            blocks.push(dataCW.slice(offset, offset + len));
            offset += len;
        }

        var genPoly = rsGenPoly(eccPerBlock);
        var eccBlocks = [];
        for (var i = 0; i < numBlocks; i++) {
            eccBlocks.push(rsRemainder(blocks[i], genPoly));
        }

        // Interleave
        var result = [];
        var maxDataLen = blocks[blocks.length - 1].length;
        for (var i = 0; i < maxDataLen; i++) {
            for (var j = 0; j < numBlocks; j++) {
                if (i < blocks[j].length) result.push(blocks[j][i]);
            }
        }
        for (var i = 0; i < eccPerBlock; i++) {
            for (var j = 0; j < numBlocks; j++) {
                result.push(eccBlocks[j][i]);
            }
        }

        return { version: ver, eccIdx: eccIdx, data: new Uint8Array(result) };
    }

    // --- Place modules on grid ---
    function createGrid(ver, eccIdx, codewords) {
        var size = ver * 4 + 17;
        var grid = [];
        var func = [];
        for (var i = 0; i < size * size; i++) { grid.push(0); func.push(false); }

        function set(r, c, val, isFunc) {
            if (r >= 0 && r < size && c >= 0 && c < size) {
                grid[r * size + c] = val ? 1 : 0;
                if (isFunc) func[r * size + c] = true;
            }
        }
        function get(r, c) { return grid[r * size + c]; }
        function isFunc(r, c) { return func[r * size + c]; }

        // Finder patterns
        function drawFinder(row, col) {
            for (var dr = -1; dr <= 7; dr++) {
                for (var dc = -1; dc <= 7; dc++) {
                    var r = row + dr, c = col + dc;
                    if (r < 0 || r >= size || c < 0 || c >= size) continue;
                    var inBorder = dr === 0 || dr === 6 || dc === 0 || dc === 6;
                    var inCenter = dr >= 2 && dr <= 4 && dc >= 2 && dc <= 4;
                    var inBlank = dr === -1 || dr === 7 || dc === -1 || dc === 7;
                    set(r, c, (inBorder || inCenter) && !inBlank, true);
                }
            }
        }
        drawFinder(0, 0);
        drawFinder(0, size - 7);
        drawFinder(size - 7, 0);

        // Alignment patterns
        if (ver >= 2) {
            var pos = ALIGN_POS[ver - 1];
            for (var i = 0; i < pos.length; i++) {
                for (var j = 0; j < pos.length; j++) {
                    var r = pos[i], c = pos[j];
                    if (isFunc(r, c)) continue;
                    for (var dr = -2; dr <= 2; dr++) {
                        for (var dc = -2; dc <= 2; dc++) {
                            set(r + dr, c + dc, Math.abs(dr) === 2 || Math.abs(dc) === 2 || (dr === 0 && dc === 0), true);
                        }
                    }
                }
            }
        }

        // Timing patterns
        for (var i = 8; i < size - 8; i++) {
            set(6, i, i % 2 === 0, true);
            set(i, 6, i % 2 === 0, true);
        }
        // Dark module
        set(size - 8, 8, 1, true);

        // Reserve format areas
        for (var i = 0; i < 8; i++) {
            set(8, i, 0, true);
            set(8, size - 1 - i, 0, true);
            set(i, 8, 0, true);
            set(size - 1 - i, 8, 0, true);
        }
        set(8, 8, 0, true);

        // Reserve version areas
        if (ver >= 7) {
            for (var i = 0; i < 6; i++) {
                for (var j = 0; j < 3; j++) {
                    set(i, size - 11 + j, 0, true);
                    set(size - 11 + j, i, 0, true);
                }
            }
        }

        // Place data bits
        var bitIdx = 0;
        var totalBits = codewords.length * 8;
        for (var right = size - 1; right >= 1; right -= 2) {
            if (right === 6) right = 5;
            for (var vert = 0; vert < size; vert++) {
                for (var j = 0; j < 2; j++) {
                    var col = right - j;
                    var upward = ((right + 1) & 2) === 0;
                    var row = upward ? size - 1 - vert : vert;
                    if (!isFunc(row, col) && bitIdx < totalBits) {
                        var byteIdx = bitIdx >> 3;
                        var bitPos = 7 - (bitIdx & 7);
                        set(row, col, (codewords[byteIdx] >> bitPos) & 1, false);
                        bitIdx++;
                    }
                }
            }
        }

        // Apply best mask
        var bestMask = 0, bestPenalty = Infinity;
        for (var mask = 0; mask < 8; mask++) {
            var masked = grid.slice();
            for (var r = 0; r < size; r++) {
                for (var c = 0; c < size; c++) {
                    if (!isFunc(r, c)) {
                        if (maskFn(mask, r, c)) {
                            masked[r * size + c] ^= 1;
                        }
                    }
                }
            }
            // Write format info
            var fmtBits = FORMAT_BITS[eccIdx][mask];
            for (var i = 0; i < 15; i++) {
                var bit = (fmtBits >> (14 - i)) & 1;
                // Around top-left finder
                if (i < 6) masked[8 * size + i] = bit;
                else if (i === 6) masked[8 * size + 7] = bit;
                else if (i === 7) masked[8 * size + 8] = bit;
                else if (i === 8) masked[7 * size + 8] = bit;
                else masked[(14 - i) * size + 8] = bit;
                // Other copy
                if (i < 8) masked[(size - 1 - i) * size + 8] = bit;
                else masked[8 * size + (size - 15 + i)] = bit;
            }
            var penalty = calcPenalty(masked, size);
            if (penalty < bestPenalty) { bestPenalty = penalty; bestMask = mask; }
        }

        // Apply chosen mask
        for (var r = 0; r < size; r++) {
            for (var c = 0; c < size; c++) {
                if (!isFunc(r, c) && maskFn(bestMask, r, c)) {
                    grid[r * size + c] ^= 1;
                }
            }
        }
        // Write final format bits
        var fmtBits = FORMAT_BITS[eccIdx][bestMask];
        for (var i = 0; i < 15; i++) {
            var bit = (fmtBits >> (14 - i)) & 1;
            if (i < 6) grid[8 * size + i] = bit;
            else if (i === 6) grid[8 * size + 7] = bit;
            else if (i === 7) grid[8 * size + 8] = bit;
            else if (i === 8) grid[7 * size + 8] = bit;
            else grid[(14 - i) * size + 8] = bit;
            if (i < 8) grid[(size - 1 - i) * size + 8] = bit;
            else grid[8 * size + (size - 15 + i)] = bit;
        }

        // Write version bits
        if (ver >= 7) {
            var verBits = ver;
            var rem = ver;
            for (var i = 0; i < 12; i++) rem = (rem << 1) ^ ((rem >> 11) * 0x1F25);
            verBits = (ver << 12) | rem;
            for (var i = 0; i < 18; i++) {
                var bit = (verBits >> i) & 1;
                var r = Math.floor(i / 3), c = i % 3;
                grid[(5 - r) * size + (size - 11 + c)] = bit;
                grid[(size - 11 + c) * size + (5 - r)] = bit;
            }
        }

        return { grid: grid, size: size };
    }

    function maskFn(mask, r, c) {
        switch (mask) {
            case 0: return (r + c) % 2 === 0;
            case 1: return r % 2 === 0;
            case 2: return c % 3 === 0;
            case 3: return (r + c) % 3 === 0;
            case 4: return (Math.floor(r / 2) + Math.floor(c / 3)) % 2 === 0;
            case 5: return (r * c) % 2 + (r * c) % 3 === 0;
            case 6: return ((r * c) % 2 + (r * c) % 3) % 2 === 0;
            case 7: return ((r * c) % 3 + (r + c) % 2) % 2 === 0;
        }
        return false;
    }

    function calcPenalty(grid, size) {
        var penalty = 0;
        // Rule 1 & 3: horizontal
        for (var r = 0; r < size; r++) {
            var run = 1;
            for (var c = 1; c < size; c++) {
                if (grid[r * size + c] === grid[r * size + c - 1]) run++;
                else { if (run >= 5) penalty += run - 2; run = 1; }
            }
            if (run >= 5) penalty += run - 2;
        }
        // Rule 1 & 3: vertical
        for (var c = 0; c < size; c++) {
            var run = 1;
            for (var r = 1; r < size; r++) {
                if (grid[r * size + c] === grid[(r - 1) * size + c]) run++;
                else { if (run >= 5) penalty += run - 2; run = 1; }
            }
            if (run >= 5) penalty += run - 2;
        }
        // Rule 2: 2x2 blocks
        for (var r = 0; r < size - 1; r++) {
            for (var c = 0; c < size - 1; c++) {
                var v = grid[r * size + c];
                if (v === grid[r * size + c + 1] && v === grid[(r + 1) * size + c] && v === grid[(r + 1) * size + c + 1]) penalty += 3;
            }
        }
        return penalty;
    }

    // --- Public API ---
    function generate(text, eccLevel) {
        eccLevel = eccLevel || 'M';
        var enc = encode(text, eccLevel);
        return createGrid(enc.version, enc.eccIdx, enc.data);
    }

    return {
        toCanvas: function(canvas, text, pixelSize, eccLevel) {
            pixelSize = pixelSize || 200;
            var qr = generate(text, eccLevel);
            var cellSize = Math.floor(pixelSize / qr.size);
            var actualSize = cellSize * qr.size;
            canvas.width = actualSize;
            canvas.height = actualSize;
            var ctx = canvas.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, actualSize, actualSize);
            ctx.fillStyle = '#000000';
            for (var r = 0; r < qr.size; r++) {
                for (var c = 0; c < qr.size; c++) {
                    if (qr.grid[r * qr.size + c]) {
                        ctx.fillRect(c * cellSize, r * cellSize, cellSize, cellSize);
                    }
                }
            }
        },

        toSVG: function(text, pixelSize, eccLevel) {
            pixelSize = pixelSize || 200;
            var qr = generate(text, eccLevel);
            var cellSize = pixelSize / qr.size;
            var parts = ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + pixelSize + ' ' + pixelSize + '">'];
            parts.push('<rect width="100%" height="100%" fill="#fff"/>');
            for (var r = 0; r < qr.size; r++) {
                for (var c = 0; c < qr.size; c++) {
                    if (qr.grid[r * qr.size + c]) {
                        parts.push('<rect x="' + (c * cellSize) + '" y="' + (r * cellSize) + '" width="' + cellSize + '" height="' + cellSize + '" fill="#000"/>');
                    }
                }
            }
            parts.push('</svg>');
            return parts.join('');
        }
    };
})();
