<?php

namespace avadim\FastDocxReader;

use XMLReader;

class NumberingMap
{
    /** @var array */
    protected array $numMap = [];

    /** @var array */
    protected array $abstractNumMap = [];

    /** @var array */
    protected array $counters = [];

    /**
     * @param string $file
     */
    public function __construct(string $file)
    {
        $xmlReader = new Reader($file);
        $xmlReader->openZip('word/numbering.xml');
        $this->parse($xmlReader);
    }

    /**
     * @param $xmlReader
     * @return void
     */
    public function parse($xmlReader): void
    {
        while ($xmlReader->read()) {
            if ($xmlReader->nodeType === XMLReader::ELEMENT) {
                if ($xmlReader->name === 'w:abstractNum') {
                    $abstractNumId = $xmlReader->getAttribute('w:abstractNumId');
                    $levels = [];
                    $depth = $xmlReader->depth;
                    while ($xmlReader->read() && $xmlReader->depth > $depth) {
                        if ($xmlReader->nodeType === XMLReader::ELEMENT && $xmlReader->name === 'w:lvl') {
                            $ilvl = $xmlReader->getAttribute('w:ilvl');
                            $lvlData = [
                                'numFmt' => '',
                                'lvlText' => '',
                                'start' => 1,
                            ];
                            $lvlDepth = $xmlReader->depth;
                            while ($xmlReader->read() && $xmlReader->depth > $lvlDepth) {
                                if ($xmlReader->nodeType === XMLReader::ELEMENT) {
                                    if ($xmlReader->name === 'w:numFmt') {
                                        $lvlData['numFmt'] = $xmlReader->getAttribute('w:val');
                                    } elseif ($xmlReader->name === 'w:lvlText') {
                                        $lvlData['lvlText'] = $xmlReader->getAttribute('w:val');
                                    } elseif ($xmlReader->name === 'w:start') {
                                        $lvlData['start'] = (int)$xmlReader->getAttribute('w:val');
                                    }
                                }
                            }
                            $levels[$ilvl] = $lvlData;
                        }
                    }
                    $this->abstractNumMap[$abstractNumId] = $levels;
                } elseif ($xmlReader->name === 'w:num') {
                    $numId = $xmlReader->getAttribute('w:numId');
                    $abstractNumId = null;
                    $depth = $xmlReader->depth;
                    while ($xmlReader->read() && $xmlReader->depth > $depth) {
                        if ($xmlReader->nodeType === XMLReader::ELEMENT && $xmlReader->name === 'w:abstractNumId') {
                            $abstractNumId = $xmlReader->getAttribute('w:val');
                        }
                    }
                    if ($abstractNumId !== null) {
                        $this->numMap[$numId] = $abstractNumId;
                    }
                }
            }
        }
        $xmlReader->close();
    }

    /**
     * @param int|string $numId
     * @param int|string $ilvl
     * @param int|null $index 1-based index
     * @return string
     */
    public function getMarker($numId, $ilvl, ?int $index = null): string
    {
        if (!isset($this->numMap[$numId])) {
            return '';
        }
        $abstractNumId = $this->numMap[$numId];
        if (!isset($this->abstractNumMap[$abstractNumId][$ilvl])) {
            return '';
        }

        $lvlData = $this->abstractNumMap[$abstractNumId][$ilvl];
        $lvlText = $lvlData['lvlText'];
        $numFmt = $lvlData['numFmt'];

        if ($numFmt === 'bullet') {
            return $lvlText;
        }

        if ($index === null) {
            $counterKey = "$numId:$ilvl";
            if (!isset($this->counters[$counterKey])) {
                $this->counters[$counterKey] = $lvlData['start'];
            }
            $index = $this->counters[$counterKey]++;
        }

        $marker = $this->formatNumber($index, $numFmt);
        
        // lvlText contains placeholders like %1, %2
        return str_replace('%' . ($ilvl + 1), $marker, $lvlText);
    }

    /**
     * @param int|string $numId
     * @param int|string $ilvl
     * @return bool
     */
    public function isBullet($numId, $ilvl): bool
    {
        if (!isset($this->numMap[$numId])) {
            return false;
        }
        $abstractNumId = $this->numMap[$numId];
        if (!isset($this->abstractNumMap[$abstractNumId][$ilvl])) {
            return false;
        }

        $lvlData = $this->abstractNumMap[$abstractNumId][$ilvl];
        return $lvlData['numFmt'] === 'bullet';
    }

    /**
     * @param int $number
     * @param string $fmt
     * @return string
     */
    protected function formatNumber(int $number, string $fmt): string
    {
        switch ($fmt) {
            case 'lowerLetter':
                return $this->toLetters($number, false);
            case 'upperLetter':
                return $this->toLetters($number, true);
            case 'lowerRoman':
                return $this->toRoman($number, false);
            case 'upperRoman':
                return $this->toRoman($number, true);
            case 'decimal':
            default:
                return (string)$number;
        }
    }

    /**
     * @param int $number
     * @param bool $upper
     * @return string
     */
    protected function toLetters(int $number, bool $upper): string
    {
        $letters = '';
        while ($number > 0) {
            $code = ($number - 1) % 26;
            $letters = chr(($upper ? 65 : 97) + $code) . $letters;
            $number = floor(($number - $code) / 26);
        }
        return $letters;
    }

    /**
     * @param int $number
     * @param bool $upper
     * @return string
     */
    protected function toRoman(int $number, bool $upper): string
    {
        $map = [
            'M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400,
            'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40,
            'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1
        ];
        $roman = '';
        foreach ($map as $rom => $dec) {
            $matches = intval($number / $dec);
            $roman .= str_repeat($rom, $matches);
            $number %= $dec;
        }
        return $upper ? $roman : strtolower($roman);
    }
}
