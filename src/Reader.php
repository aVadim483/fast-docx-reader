<?php

namespace Avadim\FastDocxReader;

use Avadim\FastDocxReader\Blocks\Elements\ElementInterface;
use Avadim\FastDocxReader\Blocks\Elements\Image;
use Avadim\FastDocxReader\Blocks\Elements\Text;
use XMLReader;

class Reader
{
    /**
     * @param string $xml
     * @return ElementInterface|null
     */
    public static function parseRun(string $xml): ?ElementInterface
    {
        $xmlReader = new XMLReader();
        $xmlReader->XML($xml);

        $text = '';
        $style = [];
        $isImage = false;
        $isBreak = false;
        $isTab = false;

        while ($xmlReader->read()) {
            if ($xmlReader->nodeType === XMLReader::ELEMENT) {
                if ($xmlReader->name === 'w:t') {
                    $text .= $xmlReader->readString();
                } elseif ($xmlReader->name === 'w:br' || $xmlReader->name === 'w:cr') {
                    $text .= "\n";
                    $isBreak = true;
                } elseif ($xmlReader->name === 'w:tab') {
                    $text .= "\t";
                    $isTab = true;
                } elseif ($xmlReader->name === 'w:drawing' || $xmlReader->name === 'w:pict') {
                    $isImage = true;
                } elseif ($xmlReader->name === 'w:rPr') {
                    $depth = $xmlReader->depth;
                    while ($xmlReader->read() && $xmlReader->depth > $depth) {
                        if ($xmlReader->nodeType === XMLReader::ELEMENT) {
                            $styleName = str_replace('w:', '', $xmlReader->name);
                            $styleValue = true;
                            if ($xmlReader->hasAttributes) {
                                $styleValue = [];
                                while ($xmlReader->moveToNextAttribute()) {
                                    $attrName = str_replace('w:', '', $xmlReader->name);
                                    $styleValue[$attrName] = $xmlReader->value;
                                }
                                if (count($styleValue) === 1 && isset($styleValue['val'])) {
                                    $styleValue = $styleValue['val'];
                                }
                                $xmlReader->moveToElement();
                            }
                            if (!$xmlReader->isEmptyElement) {
                                $subDepth = $xmlReader->depth;
                                while ($xmlReader->read() && $xmlReader->depth > $subDepth) {
                                    // just skip
                                }
                            }
                            $style[$styleName] = $styleValue;
                        }
                    }
                }
            }
        }
        $xmlReader->close();

        if ($isImage) {
            return new Image($style);
        }

        if ($text !== '' || $isBreak || $isTab || !empty($style)) {
            return new Text($text, $isBreak, $isTab, $style);
        }

        return null;
    }
}
