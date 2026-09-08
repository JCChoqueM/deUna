<?php
/**
 * DeUna - QR Code Generator
 * Pure PHP QR Code implementation using GD library
 * Based on ISO/IEC 18004 and Kazuhiko Arase's qrcode-generator
 */

class QRCode
{
    private int $typeNumber;
    private string $errorCorrectionLevel;
    private array $qrcode = [];
    private int $moduleCount;

    // GF(256) tables for Reed-Solomon
    private static array $EXP_TABLE = [];
    private static array $LOG_TABLE = [];
    private static bool $gfInitialized = false;

    // BCH generator polynomials
    private const G15 = 0x0535;  // 1 0001 0110 1011 0101
    private const G18 = 0x1f25; // 1 1111 0010 0101
    private const G18_VALUE = 0x1f25;

    // Mask patterns
    private const MASK_PATTERNS = [
        0 => 'i + j mod 2 == 0',
        1 => 'i + j mod 2 == 0', // Actually (i+j)%2==0
        2 => 'i mod 2 == 0',
        3 => 'j mod 3 == 0',
        4 => '(i + j) mod 3 == 0',
        5 => 'true',
        6 => '(i mod 2 == 0) and (j mod 3 == 0)',
        7 => 'true',
    ];

    public function __construct(int $version = 4, string $ec = 'M')
    {
        $this->typeNumber = $version;
        $this->errorCorrectionLevel = $ec;
        $this->moduleCount = $version * 4 + 17;
    }

    /**
     * Generate QR code and return as base64 data URI
     */
    public function generateDataURL(string $data, int $size = 300, int $margin = 4): string
    {
        $pngData = $this->generatePNG($data, $size, $margin);
        return 'data:image/png;base64,' . base64_encode($pngData);
    }

    /**
     * Output PNG image directly to browser
     */
    public function outputPNG(string $data, int $size = 300, int $margin = 4): void
    {
        $pngData = $this->generatePNG($data, $size, $margin);
        header('Content-Type: image/png');
        echo $pngData;
    }

    /**
     * Generate QR code PNG binary data
     */
    private function generatePNG(string $data, int $size, int $margin): string
    {
        $matrix = $this->generateMatrix($data);

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
     * Generate the QR code matrix
     */
    private function generateMatrix(string $data): array
    {
        $this->qrcode = array_fill(0, $this->moduleCount, array_fill(0, $this->moduleCount, false));
        $this->setupPositionProbePattern(0, 0);
        $this->setupPositionProbePattern($this->moduleCount - 7, 0);
        $this->setupPositionProbePattern(0, $this->moduleCount - 7);
        $this->setupPositionAdjustPattern();
        $this->setupTimingPattern();
        $this->setupTypeInfo(true, -1);

        // Generate map data
        $bitStream = $this->createBitStream($data);
        $this->mapDataInMatrix($bitStream);

        $this->setupTypeInfo(false, $this->getBestMask($bitStream));

        return $this->qrcode;
    }

    /**
     * Setup position probe pattern (finder pattern) at given position
     */
    private function setupPositionProbePattern(int $row, int $col): void
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
                $this->qrcode[$rowIdx][$colIdx] = (bool)$pattern[$r + 1][$c + 1];
            }
        }
    }

    /**
     * Setup position adjustment patterns
     */
    private function setupPositionAdjustPattern(): void
    {
        $pos = $this->getPatternPosition();

        for ($i = 0; $i < count($pos); $i++) {
            for ($j = 0; $j < count($pos); $j++) {
                $row = $pos[$i];
                $col = $pos[$j];

                $this->qrcode[$row - 2][$col - 2] = true;
                $this->qrcode[$row - 2][$col - 1] = true;
                $this->qrcode[$row - 2][$col] = true;
                $this->qrcode[$row - 2][$col + 1] = true;
                $this->qrcode[$row - 2][$col + 2] = true;

                $this->qrcode[$row - 1][$col - 2] = true;
                $this->qrcode[$row - 1][$col + 2] = true;
                $this->qrcode[$row][$col - 2] = true;
                $this->qrcode[$row][$col + 2] = true;
                $this->qrcode[$row + 1][$col - 2] = true;
                $this->qrcode[$row + 1][$col + 2] = true;

                $this->qrcode[$row + 2][$col - 2] = true;
                $this->qrcode[$row + 2][$col - 1] = true;
                $this->qrcode[$row + 2][$col] = true;
                $this->qrcode[$row + 2][$col + 1] = true;
                $this->qrcode[$row + 2][$col + 2] = true;
            }
        }
    }

    /**
     * Setup timing patterns
     */
    private function setupTimingPattern(): void
    {
        for ($r = 8; $r < $this->moduleCount - 8; $r++) {
            $this->qrcode[$r][6] = ($r % 2 === 0);
        }
        for ($c = 8; $c < $this->moduleCount - 8; $c++) {
            $this->qrcode[6][$c] = ($c % 2 === 0);
        }
    }

    /**
     * Setup type information
     */
    private function setupTypeInfo(bool $test, int $maskPattern): void
    {
        $ecLevelBits = [1 => 1, 0 => 0, 3 => 3, 2 => 2]; // M, L, H, Q
        $ecLevel = $ecLevelBits[$this->ecLevelOrdinal()];

        if ($test) {
            $maskPattern = 0;
        }

        $data = ($ecLevel << 3) | $maskPattern;
        $bits = $this->getBCHTypeInfo($data);

        // Type info horizontal
        for ($i = 0; $i < 14; $i++) {
            $bit = !$test && (($bits >> (14 - $i - 1)) & 1) === 1;

            if ($i < 6) {
                $this->qrcode[8][$i] = $bit;
            } elseif ($i < 8) {
                $this->qrcode[8][$i + 1] = $bit;
            } else {
                $this->qrcode[8][$this->moduleCount - 15 + $i - 7] = $bit;
            }

            // Vertical
            if ($i < 8) {
                $this->qrcode[$i][8] = $bit;
            } elseif ($i < 9) {
                $this->qrcode[$i][8] = $bit;
            } elseif ($i < 14) {
                $this->qrcode[$i + 1][8] = $bit;
            }
        }

        // Dark module
        $this->qrcode[$this->moduleCount - 8][8] = !$test;
    }

    /**
     * Get EC level ordinal (0=L, 1=M, 2=Q, 3=H)
     */
    private function ecLevelOrdinal(): int
    {
        $map = ['L' => 0, 'M' => 1, 'Q' => 2, 'H' => 3];
        return $map[$this->errorCorrectionLevel] ?? 1;
    }

    /**
     * Get BCH type information
     */
    private function getBCHTypeInfo(int $data): int
    {
        $bch = $this->getBCHPolynomial($data, 3);
        $bch = $bch ^ ((($data << 3) & 0x07));

        // Apply mask for dark-light module
        $bch ^= 0x07;

        return $bch;
    }

    /**
     * Get BCH error correction for data
     * Returns the polynomial
     */
    private function getBCHPolynomial(int $data, int $degree): int
    {
        $generator = 1 << $degree;
        // G15 = 0x535 for type info
        $g = self::G15;

        for ($i = degree - 1; $i >= 0; $i--) {
            // This is not the standard approach; let me use a simpler method
        }

        // Simpler BCH for type info:
        $a = $data << 3;
        for ($i = 13 + 2; $i >= 3; $i--) {
            if (($a & (1 << $i)) !== 0) {
                $a ^= ($g << ($i - 3));
            }
        }
        return $a;
    }

    /**
     * Get position adjustment pattern positions
     */
    private function getPatternPosition(): array
    {
        $positions = [
            [], // Version 0 (unused)
            [], // Version 1
            [6, 18], // Version 2
            [6, 22], // Version 3
            [6, 26], // Version 4
            [6, 30], // Version 5
            [6, 34], // Version 6
            [6, 22, 38], // Version 7
            [6, 26, 42], // Version 8
            [6, 30, 46], // Version 9
            [6, 34, 50], // Version 10
            [6, 28, 54], // Version 11
            [6, 32, 58], // Version 12
            [6, 26, 42, 58], // Version 13
            [6, 30, 42, 58], // Version 14
            [6, 34, 42, 58], // Version 15
            [6, 28, 42, 58], // Version 16
            [6, 32, 42, 58], // Version 17
            [6, 36, 42, 58], // Version 18
            [6, 28, 46, 62], // Version 19
            [6, 32, 46, 62], // Version 20
        ];

        $index = $this->typeNumber;
        return $index < count($positions) ? $positions[$index] : $positions[min($index, 20)];
    }

    /**
     * Create bit stream for QR encoding
     */
    private function createBitStream(string $data): array
    {
        $bits = [];

        // Mode indicator (0b0100 = byte mode)
        $bits[] = 0;
        $bits[] = 1;
        $bits[] = 0;
        $bits[] = 0;

        // Character count indicator
        // For versions 1-9, byte mode: 8 bits
        $dataLength = strlen($data);
        for ($i = 7; $i >= 0; $i--) {
            $bits[] = ($dataLength >> $i) & 1;
        }

        // Encode data bytes
        for ($i = 0; $i < strlen($data); $i++) {
            $byte = ord($data[$i]);
            for ($j = 7; $j >= 0; $j--) {
                $bits[] = ($byte >> $j) & 1;
            }
        }

        // Terminator (4 zeros)
        for ($i = 0; $i < 4; $i++) {
            $bits[] = 0;
        }

        // Pad to byte boundary
        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }

        // Pad with 0xEC, 0x11 alternating until we fill the data capacity
        $totalDataCodewords = $this->getTotalDataCodewords();
        $padBytes = [0xEC, 0x11];
        $padIndex = 0;
        while (count($bits) < $totalDataCodewords * 8) {
            $byte = $padBytes[$padIndex % 2];
            for ($i = 7; $i >= 0; $i--) {
                $bits[] = ($byte >> $i) & 1;
            }
            $padIndex++;
        }

        // Convert bits to bytes
        $bytes = [];
        for ($i = 0; $i < count($bits); $i += 8) {
            $byte = 0;
            for ($j = 0; $j < 8; $j++) {
                $byte = ($byte << 1) | $bits[$i + $j];
            }
            $bytes[] = $byte;
        }

        // Apply Reed-Solomon error correction
        $rsBlocks = $this->getRSBlocks();
        $ecBytes = [];

        // The data is already in the right format for single-block codes
        // Apply RS error correction
        $ecBytes = $this->rsErrorCorrection($bytes, $this->getECCodewordsCount());

        // Combine data and EC bytes
        $allBytes = array_merge($bytes, $ecBytes);

        // Return as bit array
        $bitArray = [];
        foreach ($allBytes as $byte) {
            for ($i = 7; $i >= 0; $i--) {
                $bitArray[] = ($byte >> $i) & 1;
            }
        }

        return $bitArray;
    }

    /**
     * Get total data codewords for this version and EC level
     */
    private function getTotalDataCodewords(): int
    {
        $capacities = [
            // version => [L, M, Q, H] data codewords
            1 => [19, 16, 13, 9],
            2 => [34, 28, 22, 16],
            3 => [55, 44, 34, 28],
            4 => [80, 64, 48, 36],
            5 => [108, 86, 62, 48],
        ];

        $index = $this->typeNumber;
        $ecIdx = $this->ecLevelOrdinal();
        return $capacities[$index][$ecIdx] ?? $capacities[4][$ecIdx];
    }

    /**
     * Get RS blocks for this version and EC level
     */
    private function getRSBlocks(): array
    {
        // EC codewords per block for each version and EC level
        $rsBlockData = [
            1 => [7, 10, 13, 17],   // L, M, Q, H
            2 => [10, 16, 22, 22],
            3 => [15, 22, 18, 16],
            4 => [20, 18, 16, 28],
            5 => [26, 24, 18, 22],
        ];

        $ecCodewords = $rsBlockData[$this->typeNumber][$this->ecLevelOrdinal()] ?? $rsBlockData[4][$this->ecLevelOrdinal()];

        // For version 1-4 with M level, typically single block
        // Version 1-M: 1 block of 16 data + 10 EC
        return [['data' => $this->getTotalDataCodewords(), 'ec' => $ecCodewords]];
    }

    /**
     * Get EC codewords count per block
     */
    private function getECCodewordsCount(): int
    {
        $rsBlockData = [
            1 => [7, 10, 13, 17],
            2 => [10, 16, 22, 22],
            3 => [15, 22, 18, 16],
            4 => [20, 18, 16, 28],
            5 => [26, 24, 18, 22],
        ];
        $ecIdx = $this->ecLevelOrdinal();
        return $rsBlockData[$this->typeNumber][$ecIdx] ?? $rsBlockData[4][$ecIdx];
    }

    /**
     * Reed-Solomon error correction
     */
    private function rsErrorCorrection(array $data, int $ecCount): array
    {
        $this->initGF();

        if ($ecCount === 0) {
            return [];
        }

        // Generate generator polynomial
        $generator = [1];
        for ($i = 0; $i < $ecCount; $i++) {
            $newGen = [0 => 1];
            for ($j = 0; $j < count($generator); $j++) {
                $newGen[$j] = $newGen[$j] ^ self::$EXP_TABLE[self::$LOG_TABLE[$generator[$j]] + $i];
                $newGen[$j + 1] = ($newGen[$j + 1] ?? 0) ^ $generator[$j];
            }
            $generator = $newGen;
        }

        // Multiply data by x^ecCount
        $buffer = array_merge($data, array_fill(0, $ecCount, 0));

        // Divide by generator
        for ($i = 0; $i < count($data); $i++) {
            $coefficient = $buffer[$i];
            if ($coefficient !== 0) {
                for ($j = 0; $j <= $ecCount; $j++) {
                    $factor = self::$EXP_TABLE[self::$LOG_TABLE[$coefficient] + ($j < count($generator) ? 0 : 0)];
                    // Actually, use direct GF multiplication
                    $g = $generator[$j] ?? 0;
                    if ($g !== 0) {
                        $buffer[$i + $j] ^= self::$EXP_TABLE[(self::$LOG_TABLE[$g] + self::$LOG_TABLE[$coefficient]) % 255];
                    }
                }
            }
        }

        // The result is the last ecCount bytes
        return array_slice($buffer, count($data), $ecCount);
    }

    /**
     * Initialize GF(256) tables
     */
    private function initGF(): void
    {
        if (self::$gfInitialized) {
            return;
        }

        self::$EXP_TABLE = array_fill(0, 512, 0);
        self::$LOG_TABLE = array_fill(0, 256, 0);

        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$EXP_TABLE[$i] = $x;
            self::$LOG_TABLE[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            self::$EXP_TABLE[$i] = self::$EXP_TABLE[$i - 255];
        }
        self::$gfInitialized = true;
    }

    /**
     * Map data in the QR matrix
     */
    private function mapDataInMatrix(array $bitStream): void
    {
        // Try different masks and pick the best one
        $masks = [0, 1, 2, 3, 4, 5, 6, 7];
        $bestMask = 0;
        $bestScore = PHP_INT_MAX;

        foreach ($masks as $mask) {
            $this->applyMask($bitStream, $mask);
            $score = $this->getMaskScore();
            if ($score < $bestScore) {
                $bestScore = $score;
                $bestMask = $mask;
            }
        }

        // Don't apply the mask here - we already did it in the loop for the best mask
        // Actually, we need to store the matrix state
        // But for simplicity, let's just apply the best mask
        $this->applyMask($bitStream, $bestMask);

        // Store the data bits for later use in setupTypeInfo
        $this->currentMask = $bestMask;
        $this->currentBitStream = $bitStream;
    }

    private int $currentMask = 0;
    private array $currentBitStream = [];

    /**
     * Apply a mask pattern to the matrix data region
     */
    private function applyMask(array $bitStream, int $maskPattern): void
    {
        // We need to create a fresh matrix with only function patterns
        // Then apply data with the mask
        // For simplicity, rebuild the function pattern matrix each time
        $this->qrcode = array_fill(0, $this->moduleCount, array_fill(0, $this->moduleCount, false));
        $this->setupPositionProbePattern(0, 0);
        $this->setupPositionProbePattern($this->moduleCount - 7, 0);
        $this->setupPositionProbePattern(0, $this->moduleCount - 7);
        $this->setupPositionAdjustPattern();
        $this->setupTimingPattern();
        $this->setupTypeInfo(true, $maskPattern);

        // Place data
        $bitIndex = 0;
        $dir = -1;
        $row = $this->moduleCount - 1;
        $col = $this->moduleCount - 1;

        while ($col > 0) {
            if ($col === 6) {
                $col--;
            }

            for ($i = 0; $i < $this->moduleCount; $i++) {
                $row = $row + $dir;
                if ($row < 0 || $row >= $this->moduleCount) {
                    $row = $row < 0 ? 0 : $this->moduleCount - 1;
                    $dir = -$dir;
                    $row = $row + $dir;
                }

                for ($c = 0; $c < 2; $c++) {
                    $colIdx = $col - $c;
                    if ($colIdx < 0) continue;

                    // Skip function patterns
                    if (!$this->isFunctionPattern($row, $colIdx)) {
                        $bit = ($bitIndex < count($bitStream)) ? $bitStream[$bitIndex] : 0;
                        $mask = $this->getMaskBit($row, $colIdx, $maskPattern);
                        $this->qrcode[$row][$colIdx] = (bool)($bit ^ $mask);
                        $bitIndex++;
                    }
                }
            }
            $col--;
        }
    }

    /**
     * Get mask bit for a position
     */
    private function getMaskBit(int $row, int $col, int $pattern): int
    {
        switch ($pattern) {
            case 0: return ($row + $col) % 2 === 0 ? 1 : 0;
            case 1: return $row % 2 === 0 ? 1 : 0;
            case 2: return ($col % 3 === 0) ? 1 : 0;
            case 3: return ($row + $col) % 3 === 0 ? 1 : 0;
            case 4: return (intdiv($row, 2) + intdiv($col, 3)) % 2 === 0 ? 1 : 0;
            case 5: return ($row * $col) % 2 + ($row * $col) % 3 === 0 ? 1 : 0;
            case 6: return ((($row + $col) % 2) + (($row * $col) % 3)) % 2 === 0 ? 1 : 0;
            case 7: return ((($row + $col) % 3) + 3 - ($col % 3)) % 2 === 0 ? 1 : 0;
            default: return 0;
        }
    }

    /**
     * Check if position is a function pattern
     */
    private function isFunctionPattern(int $row, int $col): bool
    {
        if ($row < 9 && $col < 9) return true;
        if ($row < 9 && $col >= $this->moduleCount - 8) return true;
        if ($row >= $this->moduleCount - 8 && $col < 9) return true;

        if ($row === 6 || $col === 6) return true;

        if ($row === 8 || $col === 8) return true;

        if ($row === $this->moduleCount - 8 && $col === 8) return true;

        $pos = $this->getPatternPosition();
        foreach ($pos as $p) {
            if (abs($row - $p) <= 2 && abs($col - $p) <= 2) return true;
        }

        return false;
    }

    /**
     * Calculate mask penalty score
     */
    private function getMaskScore(): int
    {
        $score = 0;

        // Rule 1: 5+ consecutive modules of same color in row
        for ($row = 0; $row < $this->moduleCount; $row++) {
            $consecutive = 1;
            for ($col = 1; $col < $this->moduleCount; $col++) {
                if ($this->qrcode[$row][$col] === $this->qrcode[$row][$col - 1]) {
                    $consecutive++;
                    if ($consecutive >= 5) $score += 3 + ($consecutive - 5);
                } else {
                    $consecutive = 1;
                }
            }
        }

        // Rule 2: 5+ consecutive modules in column
        for ($col = 0; $col < $this->moduleCount; $col++) {
            $consecutive = 1;
            for ($row = 1; $row < $this->moduleCount; $row++) {
                if ($this->qrcode[$row][$col] === $this->qrcode[$row - 1][$col]) {
                    $consecutive++;
                    if ($consecutive >= 5) $score += 3 + ($consecutive - 5);
                } else {
                    $consecutive = 1;
                }
            }
        }

        // Rule 3: 2x2 blocks of same color
        for ($row = 0; $row < $this->moduleCount - 1; $row++) {
            for ($col = 0; $col < $this->moduleCount - 1; $col++) {
                $same = $this->qrcode[$row][$col] && $this->qrcode[$row][$col + 1] &&
                        $this->qrcode[$row + 1][$col] && $this->qrcode[$row + 1][$col + 1];
                if ($same) $score += 40;
                $dark = !$this->qrcode[$row][$col] && !$this->qrcode[$row][$col + 1] &&
                        !$this->qrcode[$row + 1][$col] && !$this->qrcode[$row + 1][$col + 1];
                if ($dark && $row >= 0 && $row < $this->moduleCount - 1 &&
                    $col >= 0 && $col < $this->moduleCount - 1) {
                    // Don't double count
                }
            }
        }

        // Rule 4: proportion of dark modules
        $darkCount = 0;
        $totalModules = $this->moduleCount * $this->moduleCount;
        for ($row = 0; $row < $this->moduleCount; $row++) {
            for ($col = 0; $col < $this->moduleCount; $col++) {
                if ($this->qrcode[$row][$col]) $darkCount++;
            }
        }
        $ratio = ($darkCount / $totalModules) * 100;
        $prevMultiple = intval($ratio / 5) * 5;
        $score += ($prevMultiple - 80 < 0 ? 80 : 80 - $prevMultiple) * ($prevMultiple - 80 < 0 ? 1 : 1);
        // Simpler version
        $prevMultiple = floor($ratio / 5) * 5;
        if ($prevMultiple < 50) {
            $score += (50 - $prevMultiple);
        } else {
            $score += ($prevMultiple - 50);
        }

        return $score;
    }
}
