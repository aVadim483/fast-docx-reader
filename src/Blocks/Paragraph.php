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

    /** @var string|null */
    protected ?string $marker = null;

    /** @var bool */
    protected bool $isBullet = false;

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
     * @return bool
     */
    public function isBullet(): bool
    {
        return $this->isBullet;
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
     * @return string|null
     */
    public function getMarker(): ?string
    {
        return $this->marker;
    }

    /**
     * @param string|null $marker
     */
    public function setMarker(?string $marker): void
    {
        $this->marker = $marker;
    }

    /**
     * @param bool $isBullet
     */
    public function setIsBullet(bool $isBullet): void
    {
        $this->isBullet = $isBullet;
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
     * @param string $tag
     * @return string
     */
    public function getHtml(string $tag = 'p'): string
    {
        $html = '';
        foreach ($this->elements() as $element) {
            if ($element instanceof \Avadim\FastDocxReader\Blocks\Elements\Text) {
                $html .= $element->getHtml('');
            } else {
                $html .= htmlspecialchars($element->getText());
            }
        }
        if ($tag) {
            $html = '<' . $tag . '>' . $html . '</' . $tag . '>';
        }
        return $html;
    }

    /**
     * @param string $tag
     * @return string
     */
    public function getHtmlText(string $tag = ''): string
    {
        $html = '';
        foreach ($this->elements() as $element) {
            if ($element instanceof \Avadim\FastDocxReader\Blocks\Elements\Text) {
                $html .= $element->getHtml('');
            } else {
                $html .= htmlspecialchars($element->getText());
            }
        }
        if ($tag) {
            $html = '<' . $tag . '>' . $html . '</' . $tag . '>';
        }
        return $html;
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
