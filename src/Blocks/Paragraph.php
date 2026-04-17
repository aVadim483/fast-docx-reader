<?php

namespace avadim\FastDocxReader\Blocks;

use avadim\FastDocxReader\Docx;
use avadim\FastDocxReader\Fragments\FragmentInterface;
use avadim\FastDocxReader\Reader\Parser;
use XMLReader;

class Paragraph implements BlockInterface
{
    /** @var string|null */
    protected ?string $text = null;

    /** @var Docx|null */
    protected ?Docx $docx = null;

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

    /** @var array */
    protected array $style = [];

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

    /**
     * @param Docx|null $docx
     */
    public function setDocx(?Docx $docx): void
    {
        $this->docx = $docx;
    }

    /**
     * @return array
     */
    public function getStyle(): array
    {
        return $this->style;
    }

    /**
     * @param array $style
     */
    public function setStyle(array $style): void
    {
        $this->style = $style;
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
            $styleStr = '';
            $styles = [];
            if (!empty($this->style['jc'])) {
                $styles[] = 'text-align:' . $this->style['jc'];
            }
            if ($styles) {
                $styleStr = ' style="' . implode(';', $styles) . '"';
            }
            $html = '<' . $tag . $styleStr . '>' . $html . '</' . $tag . '>';
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
            if ($element instanceof \avadim\FastDocxReader\Fragments\Text) {
                $html .= $element->getHtml('');
            } elseif ($element instanceof \avadim\FastDocxReader\Fragments\Image) {
                $html .= $element->getHtml();
            } else {
                $html .= htmlspecialchars($element->getText());
            }
        }

        return $html;
    }

    /**
     * @return iterable|FragmentInterface[]
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
                    if ($this->docx && $element instanceof \avadim\FastDocxReader\Fragments\Image) {
                        $element->setDocx($this->docx);
                    }
                    yield $element;
                }
            }
        }
        $xmlReader->close();
    }

}
