<?php
/**
 * DeUna - QR Code Generator
 * Server-side QR code generation using Python qrcode library
 * (with a pure-PHP GD fallback if Python is unavailable)
 *
 * Client-side QR rendering is available via public/js/qrcode.min.js
 */

class QRGen
{
    private int $version;
    private string $ecLevel;

    public function __construct(int $version = 5, string $ecLevel = 'M')
    {
        $this->version = $version;
        $this->ecLevel = $ecLevel;
    }

    /**
     * Generate QR code as a base64 data URI
     */
    public function toDataURI(string $data, int $size = 320, int $margin = 4): string
    {
        $pngData = $this->generatePNG($data, $size, $margin);
        return 'data:image/png;base64,' . base64_encode($pngData);
    }

    /**
     * Generate QR code as PNG binary data
     */
    public function toPNG(string $data, int $size = 320, int $margin = 4): string
    {
        return $this->generatePNG($data, $size, $margin);
    }

    /**
     * Output QR code PNG directly to browser
     */
    public function outputPNG(string $data, int $size = 320, int $margin = 4): void
    {
        header('Content-Type: image/png');
        echo $this->generatePNG($data, $size, $margin);
    }

    /**
     * Save QR code to file
     */
    public function saveToFile(string $data, string $filepath, int $size = 320, int $margin = 4): bool
    {
        $pngData = $this->generatePNG($data, $size, $margin);
        $dir = dirname($filepath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return file_put_contents($filepath, $pngData) !== false;
    }

    /**
     * Generate PNG binary data using Python's qrcode library
     */
    private function generatePNG(string $data, int $size = 320, int $margin = 4): string
    {
        // Try Python first (more reliable QR generation)
        $pythonResult = $this->generateWithPython($data, $size, $margin);
        if ($pythonResult !== null) {
            return $pythonResult;
        }

        // Fallback to pure PHP GD implementation
        return $this->generateWithGD($data, $size, $margin);
    }

    /**
     * Generate QR code using Python's qrcode library
     */
    private function generateWithPython(string $data, int $size, int $margin): ?string
    {
        $script = dirname(__DIR__, 2) . '/scripts/generate_qr.py';
        if (!file_exists($script)) {
            return null;
        }

        $escapedData = escapeshellarg($data);
        $cmd = sprintf(
            'python3 %s %s %d %d 2>/dev/null',
            escapeshellarg($script),
            $escapedData,
            $size,
            $margin
        );

        $output = shell_exec($cmd);
        if ($output === null || strlen($output) < 100) {
            return null;
        }

        // Output is base64-encoded PNG from the Python script
        $decoded = base64_decode($output, true);
        if ($decoded === false || strlen($decoded) < 50) {
            return null;
        }

        return $decoded;
    }

    /**
     * Generate QR code using pure PHP + GD (fallback)
     */
    private function generateWithGD(string $data, int $size, int $margin): string
    {
        $matrix = $this->encodeToMatrix($data);

        $modules = count($matrix);
        $moduleSize = max(2, intval($size / ($modules + $margin * 2)));
        $imageSize = $modules * $moduleSize + $margin * 2 * $moduleSize;
        $offset = $margin * $moduleSize;

        $image = imagecreate($imageSize, $imageSize);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefill($image, 0, 0, $white);

        for ($row = 0; $row < $modules; $row++) {
            for ($col = 0; $col < $modules; $col++) {
                if ($matrix[$row][$col]) {
                    $x1 = $offset + $col * $moduleSize;
                    $y1 = $offset + $row * $moduleSize;
                    imagefilledrectangle($image, $x1, $y1, $x1 + $moduleSize - 1, $y1 + $moduleSize - 1, $black);
                }
            }
        }

        ob_start();
        imagepng($image);
        return ob_get_clean();
    }

    /**
     * Encode data into QR matrix (pure PHP implementation)
     * Uses the QR code specification algorithm
     */
    private function encodeToMatrix(string $data): array
    {
        // GF(256) tables
        $gexp = array_fill(0, 512, 0);
        $glog = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            $gexp[$i] = $x;
            $glog[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) $x ^= 0x11D;
        }
        for ($i = 255; $i < 512; $i++) $gexp[$i] = $gexp[$i - 255];

        // Version 5-M: 2 blocks, 44 data + 32 EC per block = 76 total per block
        $version = 5;
        $mc = $version * 4 + 17; // 37 modules
        $ecLevel = 1; // M
        $ecFormat = [1, 0, 3, 2]; // L, M, Q, H → format bits

        // Data capacity for v5-M: 2 blocks × 44 data = 88 bytes
        $dataBytes = [];
        for ($i = 0; $i < strlen($data); $i++) {
            $dataBytes[] = ord($data[$i]);
        }

        // Build bit stream: mode + length + data + terminator + padding
        $bits = [];
        // Mode indicator (byte mode = 0100)
        $bits = array_merge($bits, [0, 1, 0, 0]);
        // Character count (8 bits)
        $count = count($dataBytes);
        for ($i = 7; $i >= 0; $i--) {
            $bits[] = ($count >> $i) & 1;
        }
        // Data bytes
        foreach ($dataBytes as $byte) {
            for ($i = 7; $i >= 0; $i--) {
                $bits[] = ($byte >> $i) & 1;
            }
        }

        // Terminator (4 zeros)
        for ($i = 0; $i < 4; $i++) $bits[] = 0;
        // Pad to byte boundary
        while (count($bits) % 8 !== 0) $bits[] = 0;
        // Pad to capacity
        $capacity = 88 * 8; // v5-M
        $padBytes = [0xEC, 0x11];
        $padIdx = 0;
        while (count($bits) < $capacity) {
            $byte = $padBytes[$padIdx % 2];
            for ($i = 7; $i >= 0; $i--) $bits[] = ($byte >> $i) & 1;
            $padIdx++;
        }
        $bits = array_slice($bits, 0, $capacity);

        // Convert to data codewords
        $dataCW = [];
        for ($i = 0; $i + 8 <= count($bits); $i += 8) {
            $byte = 0;
            for ($j = 0; $j < 8; $j++) $byte = ($byte << 1) | $bits[$i + $j];
            $dataCW[] = $byte;
        }

        // Split into 2 blocks of 44 bytes each
        $block1 = array_slice($dataCW, 0, 44);
        $block2 = array_slice($dataCW, 44, 44);

        // Reed-Solomon: generate 32 EC bytes per block
        $ecCount = 32;

        // Generator polynomial
        $gen = [1];
        for ($i = 0; $i < $ecCount; $i++) {
            $newGen = [1];
            for ($j = 0; $j < count($gen); $j++) {
                $term = ($gen[$j] === 0 || $gexp[$i] === 0) ? 0 : $gexp[($glog[$gen[$j]] + $glog[$gexp[$i] ?? 1]) % 255];
                // Actually, gfMul: if either is 0, return 0, else gexp[glog[x] + glog[y]]
                if ($gen[$j] !== 0 && $gexp[$i] !== 0) {
                    $term = $gexp[($glog[$gen[$j]] + $glog[$gexp[$i] ?? 1]) % 255];
                } else {
                    $term = 0;
                }
                $newGen[$j] = ($newGen[$j] ?? 0) ^ $term;
                $newGen[$j + 1] = ($newGen[$j + 1] ?? 0) ^ $gen[$j];
            }
            $gen = $newGen;
        }

        // RS encode each block
        $rsEncode = function(array $data, array $gen, int $ecCount) use (&$gexp, &$glog) {
            $buffer = array_merge($data, array_fill(0, $ecCount, 0));
            for ($i = 0; $i < count($data); $i++) {
                $coef = $buffer[$i];
                if ($coef !== 0) {
                    for ($j = 0; $j <= $ecCount; $j++) {
                        if (isset($gen[$j]) && $gen[$j] !== 0) {
                            $buffer[$i + $j] ^= $gexp[($glog[$gen[$j]] + $glog[$coef]) % 255];
                        }
                    }
                }
            }
            return array_slice($buffer, count($data), $ecCount);
        };

        $ec1 = $rsEncode($block1, $gen, $ecCount);
        $ec2 = $rsEncode($block2, $gen, $ecCount);

        // Interleave: data1[0], data2[0], data1[1], data2[1], ... ec1[0], ec2[0], ...
        $allCW = [];
        for ($i = 0; $i < 44; $i++) {
            $allCW[] = $block1[$i];
            $allCW[] = $block2[$i];
        }
        for ($i = 0; $i < $ecCount; $i++) {
            $allCW[] = $ec1[$i];
            $allCW[] = $ec2[$i];
        }

        // Convert to bits
        $dataBits = [];
        foreach ($allCW as $byte) {
            for ($i = 7; $i >= 0; $i--) {
                $dataBits[] = ($byte >> $i) & 1;
            }
        }

        // Build matrix
        $matrix = array_fill(0, $mc, array_fill(0, $mc, false));

        // Finder patterns
        $this->setupFinderPattern($matrix, 0, 0);
        $this->setupFinderPattern($matrix, $mc - 7, 0);
        $this->setupFinderPattern($matrix, 0, $mc - 7);

        // Timing patterns
        for ($r = 8; $r < $mc - 8; $r++) $matrix[$r][6] = ($r % 2 === 0);
        for ($c = 8; $c < $mc - 8; $c++) $matrix[6][$c] = ($c % 2 === 0);

        // Alignment pattern (version 5: positions 6, 30)
        $pos = [6, 30];
        foreach ($pos as $pr) {
            foreach ($pos as $pc) {
                for ($dr = -2; $dr <= 2; $dr++) {
                    for ($dc = -2; $dc <= 2; $dc++) {
                        $isDark = (abs($dr) == 2 || abs($dc) == 2 || ($dr == 0 && $dc == 0));
                        $r = $pr + $dr;
                        $c = $pc + $dc;
                        if ($r >= 0 && $r < $mc && $c >= 0 && $c < $mc) {
                            $matrix[$r][$c] = $isDark;
                        }
                    }
                }
            }
        }

        // Dark module
        $matrix[$mc - 8][8] = true;

        // Format info
        $fmtInfo = $this->getBCHTypeInfo(($ecFormat[1] << 3) | 0); // EC=M(0), mask=0 (will be replaced)

        // Find best mask
        $bestMask = 0;
        $bestScore = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $testMatrix = $this->placeDataBits($matrix, $dataBits, $mask);
            $this->placeFormatInfoBit($testMatrix, $ecFormat[1], $mask);
            $score = $this->getPenaltyScore($testMatrix);
            if ($score < $bestScore) {
                $bestScore = $score;
                $bestMask = $mask;
            }
        }

        $matrix = $this->placeDataBits($matrix, $dataBits, $bestMask);
        $this->placeFormatInfoBit($matrix, $ecFormat[1], $bestMask);

        return $matrix;
    }

    /**
     * Setup finder pattern (7x7 + separator)
     */
    private function setupFinderPattern(array &$matrix, int $row, int $col): void
    {
        $pattern = [
            [1, 1, 1, 1, 1, 1, 1],
            [1, 0, 0, 0, 0, 0, 1],
            [1, 0, 1, 1, 1, 0, 1],
            [1, 0, 1, 1, 1, 0, 1],
            [1, 0, 1, 1, 1, 0, 1],
            [1, 0, 0, 0, 0, 0, 1],
            [1, 1, 1, 1, 1, 1, 1],
        ];
        $mc = $this->moduleCount;
        for ($r = -1; $r <= 7; $r++) {
            $rr = $row + $r;
            if ($rr < 0 || $rr >= $mc) continue;
            for ($c = -1; $c <= 7; $c++) {
                $cc = $col + $c;
                if ($cc < 0 || $cc >= $mc) continue;
                if ($r >= 0 && $r <= 6 && $c >= 0 && $c <= 6) {
                    $matrix[$rr][$cc] = ($pattern[$r][$c] === 1);
                } else {
                    $matrix[$rr][$cc] = false;
                }
            }
        }
    }

    /**
     * Place data bits in the matrix with a mask pattern
     */
    private function placeDataBits(array $matrix, array $bits, int $mask): array
    {
        $mc = $this->moduleCount;
        $bitIdx = 0;
        $dir = -1; // up
        $row = $mc - 1;
        $col = $mc - 1;

        while ($col > 0) {
            if ($col === 6) {
                $col--;
                continue;
            }

            $nextRow = $row + $dir;
            if ($nextRow < 0 || $nextRow >= $mc) {
                $dir = -$dir;
                $col--;
                if ($col === 6) $col--;
                if ($col <= 0) break;
                $row = ($dir === -1) ? $mc - 1 : 0;
                continue;
            }

            $row = $nextRow;

            for ($c = 0; $c < 2; $c++) {
                $colIdx = $col - $c;
                if ($colIdx < 0) continue;
                if ($this->isFunction($matrix, $row, $colIdx)) continue;

                $bit = ($bitIdx < count($bits)) ? $bits[$bitIdx] : 0;
                $maskBit = $this->getMaskBit($row, $colIdx, $mask);
                $matrix[$row][$colIdx] = (bool)($bit ^ $maskBit);
                $bitIdx++;
            }
        }
        return $matrix;
    }

    /**
     * Check if module is part of function patterns
     */
    private function isFunction(array $matrix, int $row, int $col): bool
    {
        $mc = $this->moduleCount;

        // Finder patterns (8x8 including separator)
        if ($row < 9 && $col < 9) return true;
        if ($row < 9 && $col >= $mc - 8) return true;
        if ($row >= $mc - 8 && $col < 9) return true;

        // Timing patterns
        if ($row === 6) return true;
        if ($col === 6) return true;

        // Format info area
        if ($row === 8) return true;
        if ($col === 8) return true;

        // Alignment patterns (version 5: positions 6, 30)
        $pos = [6, 30];
        foreach ($pos as $p) {
            if (abs($row - $p) <= 2 && abs($col - $p) <= 2) return true;
        }

        return false;
    }

    /**
     * Get mask bit for a position
     */
    private function getMaskBit(int $row, int $col, int $pattern): int
    {
        switch ($pattern) {
            case 0: return (($row + $col) % 2 === 0) ? 1 : 0;
            case 1: return ($row % 2 === 0) ? 1 : 0;
            case 2: return ($col % 3 === 0) ? 1 : 0;
            case 3: return (($row + $col) % 3 === 0) ? 1 : 0;
            case 4: return ((intdiv($row, 2) + intdiv($col, 3)) % 2 === 0) ? 1 : 0;
            case 5: return (($row * $col) % 2 + ($row * $col) % 3) % 2 === 0 ? 1 : 0;
            case 6: return ((($row + $col) % 2) + (($row * $col) % 3)) % 2 === 0 ? 1 : 0;
            case 7: return ((($row + $col) % 3) + ($row * $col) % 2) % 2 === 0 ? 1 : 0;
            default: return 0;
        }
    }

    /**
     * Place format information (15 bits) around finders
     */
    private function placeFormatInfoBit(array &$matrix, int $ecLevelOrd, int $mask): void
    {
        $mc = $this->moduleCount;
        $data = ($ecLevelOrd << 3) | $mask;
        $bch = $this->getBCHTypeInfo($data);

        for ($i = 0; $i < 15; $i++) {
            $bit = (($bch >> (14 - $i)) & 1) === 1;

            // Copy 1: top-left horizontal (row 8)
            if ($i < 6) {
                $matrix[8][$i] = $bit;
            } elseif ($i < 8) {
                $matrix[8][$i + 1] = $bit;
            } else {
                $matrix[8][$mc - 15 + $i] = $bit;
            }

            // Copy 1: top-left vertical (col 8)
            if ($i < 8) {
                $matrix[$i][8] = $bit;
            }
        }

        // Copy 2: top-right (col mc-8) and bottom-left (row mc-8)
        for ($i = 0; $i < 8; $i++) {
            $bit = (($bch >> (14 - $i)) & 1) === 1;

            // Top-right: vertical at col mc-8, rows 0-5, 7
            if ($i < 6) {
                $matrix[$i][mc - 8] = $bit;
            } elseif ($i < 7) {
                $matrix[7][mc - 8] = $bit;
            } else {
                // i = 7: dark module
                $matrix[8][mc - 8] = true; // Always dark
            }
        }

        // Bottom-left: horizontal at row mc-7, cols 0-5, 7
        // Actually, row mc-8 (not mc-7):
        for ($i = 0; $i < 7; $i++) {
            $bit = (($bch >> (14 - (i + 8))) & 1) === 1;
            if ($i < 6) {
                $matrix[mc - 8][$i] = $bit;
            } elseif ($i == 6) {
                $matrix[mc - 8][$i + 1] = $bit; // skip col 6 (timing)
            }
        }
    }

    /**
     * Get BCH format information (15-bit)
     */
    private function getBCHTypeInfo(int $data): int
    {
        $a = $data << 10;
        for ($i = 14; $i >= 10; $i--) {
            if (($a & (1 << $i)) !== 0) {
                $a ^= (self::G15 << ($i - 10));
            }
        }
        return (($data << 10) | ($a & 0x3FF)) ^ self::G15_MASK;
    }

    /**
     * Evaluate mask penalty score
     */
    private function getPenaltyScore(array $matrix): int
    {
        $mc = $this->moduleCount;
        $score = 0;

        // N1: 5+ consecutive same color in rows (+ 3 + (n-5) for each extra)
        for ($row = 0; $row < $mc; $row++) {
            $consecutive = 1;
            for ($col = 1; $col < $mc; $col++) {
                if ($matrix[$row][$col] === $matrix[$row][$col - 1]) {
                    $consecutive++;
                    if ($consecutive >= 5) $score += 3 + ($consecutive - 5);
                } else {
                    $consecutive = 1;
                }
            }
        }

        // N2: 5+ consecutive same color in columns
        for ($col = 0; $col < $mc; $col++) {
            $consecutive = 1;
            for ($row = 1; $row < $mc; $row++) {
                if ($matrix[$row][$col] === $matrix[$row - 1][$col]) {
                    $consecutive++;
                    if ($consecutive >= 5) $score += 3 + ($consecutive - 5);
                } else {
                    $consecutive = 1;
                }
            }
        }

        // N3: 2x2 blocks of same color (+40 each)
        for ($row = 0; $row < $mc - 1; $row++) {
            for ($col = 0; $col < $mc - 1; $col++) {
                if ($matrix[$row][$col] === $matrix[$row][$col + 1] &&
                    $matrix[$row][$col] === $matrix[$row + 1][$col] &&
                    $matrix[$row][$col] === $matrix[$row + 1][$col + 1]) {
                    $score += 3;
                }
            }
        }

        // N4: dark module ratio (ideal 50%)
        $dark = 0;
        for ($row = 0; $row < $mc; $row++) {
            for ($col = 0; $col < $mc; $col++) {
                if ($matrix[$row][$col]) $dark++;
            }
        }
        $ratio = floor(($dark * 100) / ($mc * $mc));
        $prevMultiple = floor($ratio / 5) * 5;
        if ($prevMultiple <= 50) {
            $score += (50 - $prevMultiple) / 5 * 10;
        } else {
            $score += ($prevMultiple - 50) / 5 * 10;
        }

        return $score;
    }
}
