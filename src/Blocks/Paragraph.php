<?php

namespace avadim\FastDocxReader\Blocks;

use avadim\FastDocxReader\Docx;
use avadim\FastDocxReader\Fragments\Image;
use avadim\FastDocxReader\Interfaces\BlockInterface;
use avadim\FastDocxReader\Interfaces\FragmentInterface;
use avadim\FastDocxReader\Options\HtmlOptions;
use avadim\FastDocxReader\Options\PlainTextOptions;
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
    public function getStyleProps(): array
    {
        return $this->style;
    }

    /**
     * @param array $style
     */
    public function setStyleProps(array $style): void
    {
        $this->style = $style;
    }

    public function getText(?PlainTextOptions $options = null): string
    {
        $options = $options ?? Docx::getPlainTextOptions();
        if ($this->text === null) {
            $this->text = '';
            foreach ($this->elements() as $element) {
                $this->text .= $element->getText($options);
            }
        }

        return $this->text;
    }

    /**
     * @return string
     */
    public function getXml(): string
    {
        return $this->xml;
    }

    /**
     * @param HtmlOptions|null $options
     * @return string
     */
    public function toHtml(?HtmlOptions $options = null): string
    {
        $options = $options ?? Docx::getHtmlOptions();
        $tag = 'p';
        $html = $this->getHtmlContents();
        if ($tag) {
            $styles = ['margin:0'];

            $sz = $this->style['rPr']['sz'] ?? 22;
            $fontSize = ((float)$sz / 2) . 'pt';
            $styles[] = 'font-size:' . $fontSize;
            $styles[] = 'min-height:' . $fontSize;

            if (!empty($this->style['jc'])) {
                $styles[] = 'text-align:' . $this->style['jc'];
            }
            if (!empty($this->style['rPr']['b']) || !empty($this->style['rPr']['bCs'])) {
                $styles[] = 'font-weight:bold';
            }
            if (!empty($this->style['rPr']['i']) || !empty($this->style['rPr']['iCs'])) {
                $styles[] = 'font-style:italic';
            }
            if (!empty($this->style['rPr']['u'])) {
                $styles[] = 'text-decoration:underline';
            }
            if (!empty($this->style['rPr']['color'])) {
                $color = $this->style['rPr']['color'];
                if (is_array($color) && isset($color['val'])) {
                    $color = $color['val'];
                }
                if (is_string($color) && $color !== 'auto') {
                    $styles[] = 'color:#' . $color;
                }
            }

            $styleStr = $styles ? ' style="' . implode(';', $styles) . '"' : '';
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
                $html .= $element->toHtml();
            } elseif ($element instanceof Image) {
                $html .= $element->toHtml();
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
                    if ($this->docx && $element instanceof Image) {
                        $element->setDocx($this->docx);
                    }
                    yield $element;
                }
            }
        }
        $xmlReader->close();
    }

}
