<?php

namespace Avadim\FastDocxReader;

use Avadim\FastDocxReader\Blocks\BlockInterface;
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

        while ($xmlReader->read()) {
            if ($xmlReader->nodeType === XMLReader::ELEMENT) {
                if ($xmlReader->name === 'w:p') {
                    $nodeXml = $xmlReader->readOuterXml();
                    yield new Paragraph($this->parseParagraphText($nodeXml), $nodeXml);
                } elseif ($xmlReader->name === 'w:tbl') {
                    $nodeXml = $xmlReader->readOuterXml();
                    yield new Table($this->parseTableRows($nodeXml));
                }
            }
        }
        $xmlReader->close();
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
