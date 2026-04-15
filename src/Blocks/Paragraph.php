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

    /** @var int|null */
    protected ?int $listId = null;

    /** @var int|null */
    protected ?int $listLevel = null;

    public function __construct(string $text, string $xml = '')
    {
        $this->text = $text;
        $this->xml = $xml;
    }

    /**
     * @return bool
     */
    public function isList(): bool
    {
        return $this->listId !== null;
    }

    /**
     * @return int|null
     */
    public function listId(): ?int
    {
        return $this->listId;
    }

    /**
     * @return int|null
     */
    public function listLevel(): ?int
    {
        return $this->listLevel;
    }

    /**
     * @param int|null $listId
     * @param int|null $listLevel
     * @return void
     */
    public function setListParams(?int $listId, ?int $listLevel): void
    {
        $this->listId = $listId;
        $this->listLevel = $listLevel;
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
