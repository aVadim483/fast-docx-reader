<?php

namespace avadim\FastDocxReader\Blocks;

use avadim\FastDocxReader\Blocks\Elements\ElementInterface;
use avadim\FastDocxReader\Parser;
use XMLReader;

class Paragraph implements BlockInterface
{
    /** @var string|null */
    protected ?string $text = null;

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

    public function __construct(string $xml = '')
    {
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
        if ($this->text === null) {
            $this->text = '';
            foreach ($this->elements() as $element) {
                $this->text .= $element->getText();
            }
        }

        return $this->text;
    }

    public function getType(): string
    {
        return 'paragraph';
    }

    /**
     * @return string
     */
    public function getXml(): string
    {
        return $this->xml;
    }

    /**
     * @param string $tag
     * @return string
     */
    public function getHtml(string $tag = 'p'): string
    {
        $html = $this->getHtmlContents();
        if ($tag) {
            $html = '<' . $tag . '>' . $html . '</' . $tag . '>';
        }

        return $html;
    }

    /**
     * @return string
     */
    public function getHtmlContents(): string
    {
        $html = '';
        foreach ($this->elements() as $element) {
            if ($element instanceof \avadim\FastDocxReader\Blocks\Elements\Text) {
                $html .= $element->getHtml('');
            } else {
                $html .= htmlspecialchars($element->getText());
            }
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
        $xmlReader->XML('<root xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">' . $this->xml . '</root>');

        while ($xmlReader->read()) {
            if ($xmlReader->nodeType === XMLReader::ELEMENT && $xmlReader->name === 'w:r') {
                $nodeXml = $xmlReader->readOuterXml();
                $element = Parser::parseRun($nodeXml);
                if ($element) {
                    yield $element;
                }
            }
        }
        $xmlReader->close();
    }

}
