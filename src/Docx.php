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
    protected $filePath;

    /** @var ZipArchive */
    protected $zip;

    /** @var NumberingMap|null */
    protected ?NumberingMap $numberingMap = null;

    /**
     * @param string $filePath
     * @throws Exception
     */
    public function __construct(string $filePath)
    {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: $filePath");
        }
        $this->filePath = $filePath;
        $this->zip = new ZipArchive();
        if ($this->zip->open($filePath) !== true) {
            throw new Exception("Could not open ZIP: $filePath");
        }
        $numberingXml = $this->zip->getFromName('word/numbering.xml');
        if ($numberingXml) {
            $this->numberingMap = new NumberingMap($numberingXml);
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
        $documentXml = $this->zip->getFromName('word/document.xml');
        if (!$documentXml) {
            throw new Exception("Could not find word/document.xml in DOCX");
        }

        $xmlReader = new XMLReader();
        $xmlReader->XML($documentXml);

        /** @var ParagraphList|null $list */
        $list = null;
        $lists = [];
        $lastItem = null;
        while ($xmlReader->read()) {
            if ($xmlReader->nodeType === XMLReader::ELEMENT) {
                if ($xmlReader->name === 'w:p') {
                    $nodeXml = $xmlReader->readOuterXml();
                    $paragraph = new Paragraph($this->parseParagraphText($nodeXml), $nodeXml);
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
                } elseif ($xmlReader->name === 'w:tbl') {
                    if ($list) {
                        yield reset($lists);
                        $list = null;
                        $lists = [];
                    }
                    $nodeXml = $xmlReader->readOuterXml();
                    yield new Table($this->parseTableRows($nodeXml));
                }
            }
        }
        if ($list) {
            yield reset($lists);
        }
        $xmlReader->close();
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
     * @return string
     */
    protected function parseParagraphText(string $xml): string
    {
        $text = '';
        $xmlReader = new XMLReader();
        $xmlReader->XML($xml);
        while ($xmlReader->read()) {
            if ($xmlReader->nodeType === XMLReader::ELEMENT) {
                if ($xmlReader->name === 'w:t') {
                    $text .= $xmlReader->readString();
                } elseif ($xmlReader->name === 'w:br' || $xmlReader->name === 'w:cr') {
                    $text .= "\n";
                } elseif ($xmlReader->name === 'w:tab') {
                    $text .= "\t";
                }
            }
        }
        $xmlReader->close();
        return $text;
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
                $cells[] = $this->parseParagraphText($xmlReader->readOuterXml());
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

    public function __destruct()
    {
        if ($this->zip) {
            $this->zip->close();
        }
    }
}
