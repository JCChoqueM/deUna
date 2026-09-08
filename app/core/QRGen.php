<?php
/**
 * DeUna - QR Code Generator
 * Pure PHP QR Code implementation using GD library
 * Based on ISO/IEC 18004 specification
 * Implements byte-mode encoding with Reed-Solomon error correction
 */

class QRGen
{
    // --- Static QR specification data ---

    /** RS Block Table: [version][ecLevel] = [count, totalCount, dataCount] per group */
    private static array $RS_BLOCK_TABLE = [
        // Version 1
        1 => [
            'L' => [[1, 26, 19]],
            'M' => [[1, 26, 16]],
            'Q' => [[1, 26, 13]],
            'H' => [[1, 26, 9]],
        ],
        // Version 2
        2 => [
            'L' => [[1, 44, 34]],
            'M' => [[1, 44, 28]],
            'Q' => [[1, 44, 22]],
            'H' => [[1, 44, 16]],
        ],
        // Version 3
        3 => [
            'L' => [[1, 70, 55]],
            'M' => [[1, 70, 44]],
            'Q' => [[2, 34, 17]],
            'H' => [[2, 34, 13]],
        ],
        // Version 4
        4 => [
            'L' => [[1, 100, 80]],
            'M' => [[2, 54, 32]],
            'Q' => [[2, 40, 16]],
            'H' => [[4, 28, 12]],
        ],
        // Version 5
        5 => [
            'L' => [[1, 142, 108]],
            'M' => [[2, 76, 44]],
            'Q' => [[2, 54, 24]],
            'H' => [[2, 40, 16]],
        ],
    ];

    /** Position adjustment pattern center positions per version */
    private static array $POS_TABLE = [
        1 => [],
        2 => [6, 18],
        3 => [6, 22],
        4 => [6, 26],
        5 => [6, 30],
    ];

    // Galois Field tables (GF(256))
    private static array $gexp = [];
    private static array $glog = [];
    private static bool $gfInit = false;

    // Configuration
    private int $version;
    private string $ecLevel;
    private int $moduleCount;

    /**
     * Constructor
     * @param int $version QR version 1-40
     * @param string $ecLevel Error correction: L, M, Q, H
     */
    public function __construct(int $version = 5, string $ecLevel = 'M')
    {
        $this->version = max(1, min(5, $version));
        $this->ecLevel = $ecLevel;
        $this->moduleCount = $this->version * 4 + 17;
    }

    /**
     * Generate QR code as a base64 data URI
     */
    public function toDataURI(string $data, int $size = 320, int $margin = 4): string
    {
        return 'data:image/png;base64,' . base64_encode($this->toPNG($data, $size, $margin));
    }

    /**
     * Generate QR code as PNG binary string
     */
    public function toPNG(string $data, int $size = 320, int $margin = 4): string
    {
        $matrix = $this->encode($data);

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
        $pngData = ob_get_clean();
        imagedestroy($image);

        return $pngData;
    }

    /**
     * Save QR code to a file
     */
    public function saveToFile(string $data, string $filepath, int $size = 320, int $margin = 4): bool
    {
        $pngData = $this->toPNG($data, $size, $margin);
        return file_put_contents($filepath, $pngData) !== false;
    }

    /**
     * Encode data into QR matrix with best mask
     */
    private function encode(string $data): array
    {
        self::initGF();

        // Build the data bit stream
        $bitStream = $this->encodeData($data);

        // Split into RS blocks and apply error correction
        $codewords = $this->processRSBlocks($bitStream);

        // Convert codewords to bit array
        $bits = [];
        foreach ($codewords as $byte) {
            for ($i = 7; $i >= 0; $i--) {
                $bits[] = ($byte >> $i) & 1;
            }
        }

        // Build matrix with function patterns
        $matrix = array_fill(0, $this->moduleCount, array_fill(0, $this->moduleCount, false));
        $this->setupPositionProbePattern($matrix, 0, 0);
        $this->setupPositionProbePattern($matrix, $this->moduleCount - 7, 0);
        $this->setupPositionProbePattern($matrix, 0, $this->moduleCount - 7);
        $this->setupTimingPattern($matrix);
        $this->setupAdjustPattern($matrix);
        $this->setupDarkModule($matrix);

        // Find best mask
        $bestMask = 0;
        $bestScore = PHP_INT_MAX;
        $bestMatrix = null;

        for ($mask = 0; $mask < 8; $mask++) {
            $testMatrix = $this->placeData($matrix, $bits, $mask);
            $this->setupTypeInfo($testMatrix, $mask);
            $score = $this->evaluateMask($testMatrix);
            if ($score < $bestScore) {
                $bestScore = $score;
                $bestMask = $mask;
                $bestMatrix = $testMatrix;
            }
        }

        // Final: apply best mask and type info
        $finalMatrix = $this->placeData($matrix, $bits, $bestMask);
        $this->setupTypeInfo($finalMatrix, $bestMask);

        return $finalMatrix;
    }

    /**
     * Encode data into bit stream (byte mode)
     */
    private function encodeData(string $data): array
    {
        $dataBytes = [];
        for ($i = 0; $i < strlen($data); $i++) {
            $dataBytes[] = ord($data[$i]);
        }

        $bits = [];

        // Mode indicator: 0100 (byte mode)
        $bits = array_merge($bits, [0, 1, 0, 0]);

        // Character count indicator (8 bits for version 1-9, byte mode)
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

        // Terminator (up to 4 zeros)
        $capacity = $this->getTotalDataCodewords() * 8;
        $remaining = $capacity - count($bits);
        $termLen = min(4, max(0, $remaining));
        for ($i = 0; $i < $termLen; $i++) {
            $bits[] = 0;
        }

        // Pad to byte boundary
        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }

        // Pad with 0xEC, 0x11 alternating
        $padBytes = [0xEC, 0x11];
        $padIdx = 0;
        while (count($bits) < $capacity) {
            $byte = $padBytes[$padIdx % 2];
            for ($i = 7; $i >= 0; $i--) {
                $bits[] = ($byte >> $i) & 1;
            }
            $padIdx++;
        }

        // Truncate to capacity
        $bits = array_slice($bits, 0, $capacity);

        // Convert bits to bytes
        $bytes = [];
        for ($i = 0; $i < count($bits); $i += 8) {
            $byte = 0;
            for ($j = 0; $j < 8; $j++) {
                $byte = ($byte << 1) | $bits[$i + $j];
            }
            $bytes[] = $byte;
        }

        return $bytes;
    }

    /**
     * Process RS blocks: split data, apply RS EC, interleave
     */
    private function processRSBlocks(array $dataBytes): array
    {
        $blocks = $this->getRSBlocks();

        // Split data into blocks
        $blockData = [];
        $offset = 0;
        foreach ($blocks as $block) {
            $dataCount = $block['dataCount'];
            $blockData[] = [
                'data' => array_slice($dataBytes, $offset, $dataCount),
                'ecCount' => $block['ecCount'],
            ];
            $offset += $dataCount;
        }

        // Apply RS error correction to each block
        $result = [];
        $maxDataCount = max(array_column(array_map(function($b) { return ['dataCount' => count($b['data'])]; }, $blockData), 'dataCount'));
        $maxEcCount = max(array_column($blockData, 'ecCount'));

        // Interleave data codewords
        for ($i = 0; $i < $maxDataCount; $i++) {
            foreach ($blockData as $block) {
                if ($i < count($block['data'])) {
                    $result[] = $block['data'][$i];
                }
            }
        }

        // Generate EC for each block and interleave
        for ($i = 0; $i < $maxEcCount; $i++) {
            foreach ($blockData as $block) {
                $ecCount = $block['ecCount'];
                // Generate or retrieve EC bytes
                if (!isset($block['ec'])) {
                    $block['ec'] = self::rsErrorCorrection($block['data'], $ecCount);
                }
                if ($i < count($block['ec'])) {
                    $result[] = $block['ec'][$i];
                }
            }
        }

        return $result;
    }

    /**
     * Get RS blocks for current version and EC level
     */
    private function getRSBlocks(): array
    {
        $ecLevel = $this->ecLevel;
        $groups = self::$RS_BLOCK_TABLE[$this->version][$ecLevel];

        $blocks = [];
        foreach ($groups as $group) {
            $count = $group[0];       // number of blocks
            $totalCount = $group[1];  // total codewords per block
            $dataCount = $group[2];   // data codewords per block
            $ecCount = $totalCount - $dataCount;

            for ($i = 0; $i < $count; $i++) {
                $blocks[] = [
                    'dataCount' => $dataCount,
                    'ecCount' => $ecCount,
                ];
            }
        }

        return $blocks;
    }

    /**
     * Get total data codewords for current version and EC level
     */
    private function getTotalDataCodewords(): int
    {
        $blocks = $this->getRSBlocks();
        $total = 0;
        foreach ($blocks as $block) {
            $total += $block['dataCount'];
        }
        return $total;
    }

    /**
     * Reed-Solomon error correction
     */
    public static function rsErrorCorrection(array $data, int $ecCount): array
    {
        $generator = self::rsGeneratorPoly($ecCount);
        $buffer = array_merge($data, array_fill(0, $ecCount, 0));

        for ($i = 0; $i < count($data); $i++) {
            $coef = $buffer[$i];
            if ($coef !== 0) {
                for ($j = 0; $j <= $ecCount; $j++) {
                    $buffer[$i + $j] ^= self::gfMul($generator[$j], $coef);
                }
            }
        }

        return array_slice($buffer, count($data), $ecCount);
    }

    /**
     * Generate Reed-Solomon generator polynomial
     */
    private static function rsGeneratorPoly(int $count): array
    {
        $poly = [1];
        for ($i = 0; $i < $count; $i++) {
            $newPoly = [0 => 1];
            for ($j = 0; $j < count($poly); $j++) {
                $newPoly[$j] = ($newPoly[$j] ?? 0) ^ self::gfMul($poly[$j], self::$gexp[$i]);
                $newPoly[$j + 1] = ($newPoly[$j + 1] ?? 0) ^ $poly[$j];
            }
            $poly = $newPoly;
        }
        return $poly;
    }

    /**
     * GF(256) multiplication
     */
    private static function gfMul(int $x, int $y): int
    {
        if ($x === 0 || $y === 0) return 0;
        return self::$gexp[(self::$glog[$x] + self::$glog[$y]) % 255];
    }

    /**
     * Initialize GF(256) tables
     */
    private static function initGF(): void
    {
        if (self::$gfInit) return;

        self::$gexp = array_fill(0, 512, 0);
        self::$glog = array_fill(0, 256, 0);

        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$gexp[$i] = $x;
            self::$glog[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            self::$gexp[$i] = self::$gexp[$i - 255];
        }
        self::$gfInit = true;
    }

    /**
     * Setup position probe pattern (finder pattern)
     */
    private function setupPositionProbePattern(array &$matrix, int $row, int $col): void
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

        for ($r = -1; $r < 7; $r++) {
            $rowIdx = $row + $r;
            if ($rowIdx < 0 || $rowIdx >= $this->moduleCount) continue;
            for ($c = -1; $c < 7; $c++) {
                $colIdx = $col + $c;
                if ($colIdx < 0 || $colIdx >= $this->moduleCount) continue;
                $matrix[$rowIdx][$colIdx] = (bool)$pattern[$r + 1][$c + 1];
            }
        }
    }

    /**
     * Setup timing patterns
     */
    private function setupTimingPattern(array &$matrix): void
    {
        for ($r = 8; $r < $this->moduleCount - 8; $r++) {
            $matrix[$r][6] = ($r % 2 === 0);
        }
        for ($c = 8; $c < $this->moduleCount - 8; $c++) {
            $matrix[6][$c] = ($c % 2 === 0);
        }
    }

    /**
     * Setup alignment patterns
     */
    private function setupAdjustPattern(array &$matrix): void
    {
        $pos = self::$POS_TABLE[$this->version] ?? [];
        if (empty($pos)) return;

        for ($i = 0; $i < count($pos); $i++) {
            for ($j = 0; $j < count($pos); $j++) {
                $row = $pos[$i];
                $col = $pos[$j];

                for ($r = -2; $r <= 2; $r++) {
                    for ($c = -2; $c <= 2; $c++) {
                        $isDark = ($r === -2 || $r === 2 || $c === -2 || $c === 2 || ($r === 0 && $c === 0));
                        $rowIdx = $row + $r;
                        $colIdx = $col + $c;
                        if ($rowIdx >= 0 && $rowIdx < $this->moduleCount &&
                            $colIdx >= 0 && $colIdx < $this->moduleCount) {
                            $matrix[$rowIdx][$colIdx] = $isDark;
                        }
                    }
                }
            }
        }
    }

    /**
     * Setup dark module
     */
    private function setupDarkModule(array &$matrix): void
    {
        $matrix[$this->moduleCount - 8][8] = true;
    }

    /**
     * Place data bits in matrix (without function patterns modified)
     */
    private function placeData(array $matrix, array $bits, int $mask): array
    {
        $bitIndex = 0;
        $dir = -1;
        $row = $this->moduleCount - 1;
        $col = $this->moduleCount - 1;

        while ($col > 0) {
            if ($col === 6) {
                $col--;
                continue;
            }

            while ($row + $dir < 0 || $row + $dir >= $this->moduleCount) {
                $dir = -$dir;
                $col--;
                if ($col === 6) {
                    $col--;
                }
                // Check if we're done
                if ($col <= 0) {
                    return $matrix;
                }
            }

            for ($i = 0; $i < $this->moduleCount; $i++) {
                $row += $dir;

                if ($row < 0 || $row >= $this->moduleCount || $col < 0) {
                    break;
                }

                for ($c = 0; $c < 2; $c++) {
                    $colIdx = $col - $c;
                    if ($colIdx < 0 || $colIdx >= $this->moduleCount) {
                        break 2;
                    }

                    // Skip function patterns
                    if ($this->isFunctionModule($row, $colIdx)) {
                        continue;
                    }

                    $bit = $bitIndex < count($bits) ? $bits[$bitIndex] : 0;
                    $maskBit = $this->getMaskBit($row, $colIdx, $mask);
                    $matrix[$row][$colIdx] = (bool)($bit ^ $maskBit);
                    $bitIndex++;
                }
            }

            $col--;
            if ($col === 6) {
                $col--;
            }
            if ($col < 0) {
                // Wrap to right side
                break;
            }
        }

        return $matrix;
    }

    /**
     * Check if module is part of a function pattern
     */
    private function isFunctionModule(int $row, int $col): bool
    {
        // Finder patterns (top-left, top-right, bottom-left)
        if ($row < 9 && $col < 9) return true;
        if ($row < 9 && $col >= $this->moduleCount - 8) return true;
        if ($row >= $this->moduleCount - 8 && $col < 9) return true;

        // Timing patterns
        if ($row === 6 || $col === 6) return true;

        // Format info area
        if ($row === 8 || $col === 8) return true;

        // Dark module
        if ($row === $this->moduleCount - 8 && $col === 8) return true;

        // Alignment patterns
        $pos = self::$POS_TABLE[$this->version] ?? [];
        foreach ($pos as $p) {
            if (abs($row - $p) <= 2 && abs($col - $p) <= 2) return true;
        }

        return false;
    }

    /**
     * Setup type information
     */
    private function setupTypeInfo(array &$matrix, int $mask): void
    {
        $ecLevelBits = ['L' => 1, 'M' => 0, 'Q' => 3, 'H' => 2];
        $data = ($ecLevelBits[$this->ecLevel] << 3) | $mask;
        $bits = $this->getBCHTypeInfo($data);

        // Type info: 15 bits
        for ($i = 0; $i < 15; $i++) {
            $bit = (($bits >> (14 - $i)) & 1) === 1;

            // Horizontal (top-left finder)
            if ($i < 6) {
                $matrix[8][$i] = $bit;
            } elseif ($i < 8) {
                $matrix[8][$i + 1] = $bit;
            } else {
                $matrix[8][$this->moduleCount - 15 + $i - 7] = $bit;
            }

            // Vertical (top-left finder)
            if ($i < 8) {
                $matrix[$i][8] = $bit;
            } elseif ($i < 9) {
                $matrix[$i][8] = $bit;
            } elseif ($i < 14) {
                $matrix[$i + 1][8] = $bit;
            }

            // Top-right finder
            if ($i < 8) {
                $matrix[8][$this->moduleCount - 1 - $i] = $bit;
            } else {
                $matrix[8 - ($i - 7)][$this->moduleCount - 7] = $bit;
            }

            // Bottom-left finder
            if ($i < 8) {
                $matrix[$this->moduleCount - 1 - $i][8] = $bit;
            } else {
                $matrix[$this->moduleCount - 7][8 - ($i - 7)] = $bit;
            }
        }
    }

    /**
     * Calculate BCH format information
     * G15 = 0x535 = x^10 + x^8 + x^5 + x^4 + x^2 + x + 1
     */
    private function getBCHTypeInfo(int $data): int
    {
        $G15 = 0x535;
        $a = $data << 10;

        for ($i = 14; $i >= 10; $i--) {
            if (($a & (1 << $i)) !== 0) {
                $a ^= ($G15 << ($i - 10));
            }
        }

        return (($data << 10) | ($a & 0x3FF)) ^ 0x5412;
    }

    /**
     * Get mask bit for position and pattern
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
            case 7: return ((($row + $col) % 2 + ($row * $col) % 3)) % 2 === 0 ? 1 : 0;
            default: return 0;
        }
    }

    /**
     * Evaluate mask penalty score
     */
    private function evaluateMask(array $matrix): int
    {
        $score = 0;
        $modules = $this->moduleCount;

        // Rule 1: Consecutive same-color modules in rows (5+)
        for ($row = 0; $row < $modules; $row++) {
            $consecutive = 1;
            $prev = $matrix[$row][0];
            for ($col = 1; $col < $modules; $col++) {
                if ($matrix[$row][$col] === $prev) {
                    $consecutive++;
                    if ($consecutive >= 5) $score += 3 + ($consecutive - 5);
                } else {
                    $consecutive = 1;
                    $prev = $matrix[$row][$col];
                }
            }
        }

        // Rule 2: Consecutive same-color modules in columns (5+)
        for ($col = 0; $col < $modules; $col++) {
            $consecutive = 1;
            $prev = $matrix[0][$col];
            for ($row = 1; $row < $modules; $row++) {
                if ($matrix[$row][$col] === $prev) {
                    $consecutive++;
                    if ($consecutive >= 5) $score += 3 + ($consecutive - 5);
                } else {
                    $consecutive = 1;
                    $prev = $matrix[$row][$col];
                }
            }
        }

        // Rule 3: 2x2 blocks of same color
        for ($row = 0; $row < $modules - 1; $row++) {
            for ($col = 0; $col < $modules - 1; $col++) {
                $dark = $matrix[$row][$col] && $matrix[$row][$col + 1] &&
                        $matrix[$row + 1][$col] && $matrix[$row + 1][$col + 1];
                $light = !$matrix[$row][$col] && !$matrix[$row][$col + 1] &&
                         !$matrix[$row + 1][$col] && !$matrix[$row + 1][$col + 1];
                if ($dark) $score += 3;
                if ($light) $score += 3;
            }
        }

        // Rule 4: Dark module ratio
        $darkCount = 0;
        $total = $modules * $modules;
        for ($row = 0; $row < $modules; $row++) {
            for ($col = 0; $col < $modules; $col++) {
                if ($matrix[$row][$col]) $darkCount++;
            }
        }
        $ratio = intval(($darkCount * 100) / $total);
        $prevMultiple = intval($ratio / 5) * 5;
        $score += abs($prevMultiple - 50) / 5 * 10;

        return $score;
    }

    /**
     * Get total data codewords for version and EC level
     */
    public static function getCapacity(int $version = 5, string $ec = 'M'): int
    {
        $table = [
            1 => ['L' => 19, 'M' => 16, 'Q' => 13, 'H' => 9],
            2 => ['L' => 34, 'M' => 28, 'Q' => 22, 'H' => 16],
            3 => ['L' => 55, 'M' => 44, 'Q' => 34, 'H' => 28],
            4 => ['L' => 80, 'M' => 64, 'Q' => 48, 'H' => 36],
            5 => ['L' => 108, 'M' => 86, 'Q' => 62, 'H' => 48],
        ];
        return $table[$version][$ec] ?? 17;
    }
}
