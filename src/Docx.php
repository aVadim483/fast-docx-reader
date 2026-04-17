<?php

namespace avadim\FastDocxReader;

use avadim\FastDocxReader\Blocks\BlockInterface;
use avadim\FastDocxReader\Blocks\Paragraph;
use avadim\FastDocxReader\Blocks\ParagraphList;
use avadim\FastDocxReader\Blocks\Table;
use avadim\FastDocxReader\Exception\Exception;
use avadim\FastDocxReader\Reader\NumberingMap;
use avadim\FastDocxReader\Reader\RelationshipMap;
use avadim\FastDocxReader\Reader\Parser;
use avadim\FastDocxReader\Reader\Reader;
use XMLReader;

class Docx
{
    /** @var string */
    protected string $file;

    /** @var Reader|null */
    protected ?Reader $xmlReader;

    /** @var NumberingMap|null */
    protected ?NumberingMap $numberingMap = null;

    /** @var RelationshipMap|null */
    protected ?RelationshipMap $relationshipMap = null;

    /**
     * @param string $file
     * @throws Exception
     */
    public function __construct(string $file)
    {
        if (!file_exists($file)) {
            throw new Exception("File not found: $file");
        }
        $this->file = $file;

        $this->numberingMap = new NumberingMap($file);
        $this->relationshipMap = new RelationshipMap($file);

        $this->xmlReader = new Reader($file);
        $this->xmlReader->openZip('word/document.xml');
    }


    public function __destruct()
    {
        if ($this->xmlReader) {
            $this->xmlReader->close();
            $this->xmlReader = null;
        }
    }

    /**
     * @param string $filePath
     * @return static
     * @throws Exception
     */
    public static function open(string $filePath): self
    {
        return new static($filePath);
    }

    /**
     * @return iterable|BlockInterface[]
     * @throws Exception
     */
    public function blocks(): iterable
    {
        /** @var ParagraphList|null $list */
        $list = null;
        $lists = [];
        $lastItem = null;

        while ($this->xmlReader->read()) {
            if ($this->xmlReader->nodeType === XMLReader::ELEMENT) {
                $block = $this->readBlock();
                if ($block) {
                    if ($block instanceof Paragraph && $listParams = $this->getListParams($block->getXml())) {
                        $block->setListParams($listParams['numId'], $listParams['ilvl']);
                        if ($this->numberingMap) {
                            $block->setMarker($this->numberingMap->getMarker($listParams['numId'], $listParams['ilvl']));
                            $block->setIsBullet($this->numberingMap->isBullet($listParams['numId'], $listParams['ilvl']));
                        }
                        if (!$list) {
                            $list = new ParagraphList();
                            $lists = [$listParams['ilvl'] => $list];
                        } else {
                            if ($listParams['numId'] !== $lastItem->listId()) {
                                yield reset($lists);
                                $list = new ParagraphList();
                                $lists = [$listParams['ilvl'] => $list];
                            } elseif ($listParams['ilvl'] > $lastItem->listLevel()) {
                                $parentList = $list;
                                $list = new ParagraphList();
                                $parentList->addItem($list);
                                $lists[$listParams['ilvl']] = $list;
                            } elseif ($listParams['ilvl'] < $lastItem->listLevel()) {
                                if (isset($lists[$listParams['ilvl']])) {
                                    $list = $lists[$listParams['ilvl']];
                                    // Remove deeper levels from tracking
                                    foreach ($lists as $level => $l) {
                                        if ($level > $listParams['ilvl']) {
                                            unset($lists[$level]);
                                        }
                                    }
                                } else {
                                    // if level not found, just use the first one
                                    $list = reset($lists);
                                    // and reset lists to only this level
                                    $lists = [$listParams['ilvl'] => $list];
                                }
                            }
                        }
                        $list->addItem($block);
                        $lastItem = $block;
                    } else {
                        if ($list) {
                            yield reset($lists);
                            $list = null;
                            $lists = [];
                        }
                        yield $block;
                    }
                }
            }
        }
        if ($list) {
            yield reset($lists);
        }
        $this->xmlReader->close();
        $this->xmlReader = null;
    }

    /**
     * @return BlockInterface|null
     */
    protected function readBlock(): ?BlockInterface
    {
        if ($this->xmlReader->nodeType === XMLReader::ELEMENT) {
            if ($this->xmlReader->name === 'w:p') {
                return $this->readParagraph();
            }
            if ($this->xmlReader->name === 'w:tbl') {
                return $this->readTable();
            }
        }
        while ($this->xmlReader->read()) {
            if ($this->xmlReader->nodeType === XMLReader::ELEMENT) {
                if ($this->xmlReader->name === 'w:p') {
                    return $this->readParagraph();
                }
                if ($this->xmlReader->name === 'w:tbl') {
                    return $this->readTable();
                }
            }
        }

        return null;
    }

    /**
     * @return Paragraph
     */
    protected function readParagraph(): Paragraph
    {
        $xml = $this->xmlReader->readOuterXml();
        $paragraph = new Paragraph($xml);
        $paragraph->setDocx($this);
        $style = Parser::parseAttributes($xml, 'w:pPr');
        if ($style) {
            $paragraph->setStyle($style);
        }

        return $paragraph;
    }

    /**
     * @return Table
     */
    protected function readTable(): Table
    {
        $style = [];
        $depth = $this->xmlReader->depth;
        if ($this->xmlReader->name === 'w:tbl') {
            while ($this->xmlReader->read() && $this->xmlReader->depth > $depth) {
                if ($this->xmlReader->nodeType === XMLReader::ELEMENT) {
                    if ($this->xmlReader->name === 'w:tblPr') {
                        $style = Parser::parseAttributes($this->xmlReader->readOuterXml(), 'w:tblPr');
                    } elseif ($this->xmlReader->name === 'w:tr') {
                        // We found the first row, so we stop parsing properties and read all rows
                        // But we need to be careful with XMLReader position.
                        // readTableRows() starts with $this->xmlReader->read()
                        // If we are already at w:tr, we should probably change how readTableRows works
                        // or backtrack/handle it.
                        break;
                    }
                }
            }
        }
        return new Table($this->readTableRows(), $style);
    }

    /**
     * @return array
     */
    protected function readTableRows(): array
    {
        $rows = [];
        // If we are already at w:tr, we need to process it
        if ($this->xmlReader->nodeType === XMLReader::ELEMENT && $this->xmlReader->name === 'w:tr') {
            $rows[] = $this->readRow();
        }
        $depth = $this->xmlReader->depth;
        while ($this->xmlReader->read() && $this->xmlReader->depth === $depth) {
            if ($this->xmlReader->nodeType === XMLReader::ELEMENT && $this->xmlReader->name === 'w:tr') {
                $rows[] = $this->readRow();
            }
            if ($this->xmlReader->nodeType === XMLReader::END_ELEMENT && $this->xmlReader->name === 'w:tbl') {
                break;
            }
        }

        return $rows;
    }

    /**
     * @return array
     */
    protected function readRow(): array
    {
        $cells = [];
        $style = [];
        $depth = $this->xmlReader->depth;
        while ($this->xmlReader->read() && $this->xmlReader->depth > $depth) {
            if ($this->xmlReader->nodeType === XMLReader::ELEMENT) {
                if ($this->xmlReader->name === 'w:trPr') {
                    $style = Parser::parseAttributes($this->xmlReader->readOuterXml(), 'w:trPr');
                } elseif ($this->xmlReader->name === 'w:tc') {
                    $cellDepth = $this->xmlReader->depth;
                    $cellStyle = [];
                    $cellContent = [];
                    while ($this->xmlReader->read() && $this->xmlReader->depth > $cellDepth) {
                        if ($this->xmlReader->nodeType === XMLReader::ELEMENT) {
                            if ($this->xmlReader->name === 'w:tcPr') {
                                $cellStyle = Parser::parseAttributes($this->xmlReader->readOuterXml(), 'w:tcPr');
                            } elseif ($this->xmlReader->name === 'w:p' || $this->xmlReader->name === 'w:tbl') {
                                $block = $this->readBlock();
                                if ($block) {
                                    $cellContent[] = $block;
                                }
                            }
                        }
                    }
                    $cells[] = [
                        'style' => $cellStyle,
                        'value' => $cellContent, //$this->composeCell($cellContent),
                    ];
                }
            }
        }

        return [
            'style' => $style,
            'cells' => $cells,
        ];
    }

    /**
     * @param array $cellContent
     *
     * @return BlockInterface
     */
    protected function composeCell(array $cellContent): BlockInterface
    {
        if (count($cellContent) === 1) {
            return $cellContent[0];
        }

        $xml = '';
        foreach ($cellContent as $block) {
            if ($block instanceof Paragraph) {
                $xml .= $block->getXml();
            }
        }

        $paragraph = new Paragraph($xml);
        $paragraph->setDocx($this);
        return $paragraph;
    }


    /**
     * @param string $xml
     * @return array|null
     */
    protected function getListParams(string $xml): ?array
    {
        if (strpos($xml, '<w:numPr>') !== false) {
            $xmlReader = new XMLReader();
            $xmlReader->XML('<root xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">' . $xml . '</root>');
            $ilvl = 0;
            $numId = null;
            while ($xmlReader->read()) {
                if ($xmlReader->nodeType === XMLReader::ELEMENT) {
                    if ($xmlReader->name === 'w:ilvl') {
                        $ilvl = (int)$xmlReader->getAttribute('w:val');
                    } elseif ($xmlReader->name === 'w:numId') {
                        $numId = (int)$xmlReader->getAttribute('w:val');
                    }
                }
            }
            $xmlReader->close();
            if ($numId !== null) {
                return ['ilvl' => $ilvl, 'numId' => $numId];
            }
        }

        return null;
    }

    /**
     * @param string $xml
     * @return bool
     * @deprecated Use getListParams()
     */
    protected function isListItem(string $xml): bool
    {
        return strpos($xml, '<w:numPr>') !== false;
    }


    /**
     * @param array $options
     * @return string
     * @throws Exception
     */
    public function getText(array $options = []): string
    {
        $fullText = '';
        foreach ($this->blocks() as $block) {
            $fullText .= $block->getText() . "\n";
        }
        return trim($fullText);
    }

    /**
     * @param string $rId
     * @return string|null
     */
    public function getImageContent(string $rId): ?string
    {
        if ($this->relationshipMap) {
            $target = $this->relationshipMap->getTarget($rId);
            if ($target) {
                if (strpos($target, '/') === false) {
                    $target = 'word/' . $target;
                } elseif (strpos($target, 'media/') === 0) {
                    $target = 'word/' . $target;
                }

                $zip = new \ZipArchive();
                if ($zip->open($this->file) === true) {
                    $content = $zip->getFromName($target);
                    $zip->close();
                    return $content ?: null;
                }
            }
        }
        return null;
    }

    /**
     * @param string $rId
     * @return string|null
     */
    public function getImageMimeType(string $rId): ?string
    {
        if ($this->relationshipMap) {
            $target = $this->relationshipMap->getTarget($rId);
            if ($target) {
                $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
                switch ($ext) {
                    case 'jpg':
                    case 'jpeg':
                        return 'image/jpeg';
                    case 'png':
                        return 'image/png';
                    case 'gif':
                        return 'image/gif';
                    case 'svg':
                        return 'image/svg+xml';
                    case 'tif':
                    case 'tiff':
                        return 'image/tiff';
                }
            }
        }
        return null;
    }
}
