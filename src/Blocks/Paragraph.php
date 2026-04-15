<?php

namespace Avadim\FastDocxReader\Blocks;

use Avadim\FastDocxReader\Blocks\Elements\ElementInterface;
use Avadim\FastDocxReader\Reader;
use XMLReader;

class Paragraph implements BlockInterface
{
    /** @var string */
    protected string $text;

    /** @var string */
    protected string $xml;

    public function __construct(string $text, string $xml = '')
    {
        $this->text = $text;
        $this->xml = $xml;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getType(): string
    {
        return 'paragraph';
    }

    /**
     * @return iterable|ElementInterface[]
     */
    public function elements(): iterable
    {
        if (empty($this->xml)) {
            return;
        }

        $xmlReader = new XMLReader();
        $xmlReader->XML($this->xml);

        while ($xmlReader->read()) {
            if ($xmlReader->nodeType === XMLReader::ELEMENT && $xmlReader->name === 'w:r') {
                $nodeXml = $xmlReader->readOuterXml();
                $element = Reader::parseRun($nodeXml);
                if ($element) {
                    yield $element;
                }
            }
        }
        $xmlReader->close();
    }

}
