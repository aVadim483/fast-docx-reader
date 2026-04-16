<?php

namespace avadim\FastDocxReader;

use avadim\FastDocxReader\Blocks\BlockInterface;
use avadim\FastDocxReader\Blocks\ParagraphList;
use avadim\FastDocxReader\Blocks\Paragraph;
use avadim\FastDocxReader\Blocks\Table;
use avadim\FastDocxReader\NumberingMap;
use XMLReader;
use ZipArchive;
use Exception;

class Docx
{
    /** @var string */
    protected string $file;

    /** @var Reader|null */
    protected ?Reader $xmlReader;

    /** @var NumberingMap|null */
    protected ?NumberingMap $numberingMap = null;

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
        return new Paragraph($this->xmlReader->readOuterXml());
    }

    /**
     * @return Table
     */
    protected function readTable(): Table
    {
        return new Table($this->readTableRows());
    }

    /**
     * @return array
     */
    protected function readTableRows(): array
    {
        $rows = [];
        $depth = $this->xmlReader->depth;
        while ($this->xmlReader->read() && $this->xmlReader->depth > $depth) {
            if ($this->xmlReader->nodeType === XMLReader::ELEMENT && $this->xmlReader->name === 'w:tr') {
                $rows[] = $this->readRow();
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
        $depth = $this->xmlReader->depth;
        while ($this->xmlReader->read() && $this->xmlReader->depth > $depth) {
            if ($this->xmlReader->nodeType === XMLReader::ELEMENT && $this->xmlReader->name === 'w:tc') {
                $cellDepth = $this->xmlReader->depth;
                $style = [];
                $cellContent = [];
                while ($this->xmlReader->read() && $this->xmlReader->depth > $cellDepth) {
                    if ($this->xmlReader->nodeType === XMLReader::ELEMENT) {
                        if ($this->xmlReader->name === 'w:tcPr') {
                            $style = Parser::parseAttributes($this->xmlReader->readOuterXml(), 'w:tcPr');
                        } elseif ($this->xmlReader->name === 'w:p' || $this->xmlReader->name === 'w:tbl') {
                            $block = $this->readBlock();
                            if ($block) {
                                $cellContent[] = $block;
                            }
                        }
                    }
                }
                $cells[] = [
                    'style' => $style,
                    'value' => $cellContent, //$this->composeCell($cellContent),
                ];
            }
        }

        return $cells;
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

        return new Paragraph($xml);
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

}
