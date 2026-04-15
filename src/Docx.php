<?php

namespace Avadim\FastDocxReader;

use Avadim\FastDocxReader\Blocks\BlockInterface;
use Avadim\FastDocxReader\Blocks\ParagraphList;
use Avadim\FastDocxReader\Blocks\Paragraph;
use Avadim\FastDocxReader\Blocks\Table;
use XMLReader;
use ZipArchive;
use Exception;

class Docx
{
    /** @var string */
    protected $filePath;

    /** @var ZipArchive */
    protected $zip;

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
        while ($xmlReader->read()) {
            if ($xmlReader->nodeType === XMLReader::ELEMENT) {
                if ($xmlReader->name === 'w:p') {
                    $nodeXml = $xmlReader->readOuterXml();
                    $paragraph = new Paragraph($this->parseParagraphText($nodeXml), $nodeXml);
                    if ($listParams = $this->getListParams($nodeXml)) {
                        $paragraph->setListParams($listParams['numId'], $listParams['ilvl']);
                        if (!$list) {
                            $list = new ParagraphList();
                        }
                        $list->addItem($paragraph);
                    } else {
                        if ($list) {
                            yield $list;
                            $list = null;
                        }
                        yield $paragraph;
                    }
                } elseif ($xmlReader->name === 'w:tbl') {
                    if ($list) {
                        yield $list;
                        $list = null;
                    }
                    $nodeXml = $xmlReader->readOuterXml();
                    yield new Table($this->parseTableRows($nodeXml));
                }
            }
        }
        if ($list) {
            yield $list;
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
