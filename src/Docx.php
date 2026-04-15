<?php

namespace Avadim\FastDocxReader;

use Avadim\FastDocxReader\Blocks\BlockInterface;
use Avadim\FastDocxReader\Blocks\ParagraphList;
use Avadim\FastDocxReader\Blocks\Paragraph;
use Avadim\FastDocxReader\Blocks\Table;
use Avadim\FastDocxReader\NumberingMap;
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
                if ($this->xmlReader->name === 'w:p') {
                    $nodeXml = $this->xmlReader->readOuterXml();
                    $paragraph = new Paragraph($nodeXml);
                    if ($listParams = $this->getListParams($nodeXml)) {
                        $paragraph->setListParams($listParams['numId'], $listParams['ilvl']);
                        if ($this->numberingMap) {
                            $paragraph->setMarker($this->numberingMap->getMarker($listParams['numId'], $listParams['ilvl']));
                            $paragraph->setIsBullet($this->numberingMap->isBullet($listParams['numId'], $listParams['ilvl']));
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
                        $list->addItem($paragraph);
                        $lastItem = $paragraph;
                    } else {
                        if ($list) {
                            yield reset($lists);
                            $list = null;
                            $lists = [];
                        }
                        yield $paragraph;
                    }
                } elseif ($this->xmlReader->name === 'w:tbl') {
                    if ($list) {
                        yield reset($lists);
                        $list = null;
                        $lists = [];
                    }
                    $nodeXml = $this->xmlReader->readOuterXml();
                    yield new Table($this->parseTableRows($nodeXml));
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
     * @param string $xml
     * @return array|null
     */
    protected function getListParams(string $xml): ?array
    {
        if (strpos($xml, '<w:numPr>') !== false) {
            $xmlReader = new XMLReader();
            $xmlReader->XML($xml);
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
     * @param string $xml
     * @return array
     */
    protected function parseTableRows(string $xml): array
    {
        $rows = [];
        $xmlReader = new XMLReader();
        $xmlReader->XML($xml);
        while ($xmlReader->read()) {
            if ($xmlReader->nodeType === XMLReader::ELEMENT && $xmlReader->name === 'w:tr') {
                $rows[] = $this->parseTableRow($xmlReader->readOuterXml());
                $xmlReader->next();
            }
        }
        $xmlReader->close();
        return $rows;
    }

    /**
     * @param string $xml
     * @return array
     */
    protected function parseTableRow(string $xml): array
    {
        $cells = [];
        $xmlReader = new XMLReader();
        $xmlReader->XML($xml);
        while ($xmlReader->read()) {
            if ($xmlReader->nodeType === XMLReader::ELEMENT && $xmlReader->name === 'w:tc') {
                $cells[] = new Paragraph($xmlReader->readOuterXml());
                $xmlReader->next();
            }
        }
        $xmlReader->close();
        return $cells;
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
