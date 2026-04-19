<?php

namespace avadim\FastDocxReader\Blocks;

use avadim\FastDocxReader\Docx;
use avadim\FastDocxReader\Options\HtmlOptions;
use avadim\FastDocxReader\Options\PlainTextOptions;

class ParagraphList extends Paragraph
{
    /** @var Paragraph[]|ParagraphList[] */
    protected array $items = [];

    public function __construct(string $xml = '')
    {
        parent::__construct($xml);
    }

    /**
     * @param Paragraph|ParagraphList $paragraph
     * @return void
     */
    public function addItem($paragraph): void
    {
        $this->items[] = $paragraph;
    }

    /**
     * @return Paragraph[]|ParagraphList[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getText(?PlainTextOptions $options = null): string
    {
        $options = $options ?? Docx::getPlainTextOptions();
        $text = '';
        foreach ($this->items as $item) {
            if ($item instanceof ParagraphList) {
                $text .= "\t" . str_replace("\n", "\n\t", trim($item->getText())) . $options->breakChar;
            } else {
                //$marker = $item->getMarker();
                $text .= $options->bulletMarker . ' ' . $item->getText($options) . $options->breakChar;
            }
        }
        return $text;
    }

    /**
     * @param HtmlOptions|null $options
     * @return string
     */
    public function toHtml(?HtmlOptions $options = null): string
    {
        $options = $options ?? Docx::getHtmlOptions();
        $listTag = $this->isBullet() ? 'ul' : 'ol';
        $html = '<' . $listTag . '>';
        foreach ($this->items as $item) {
            if ($item instanceof ParagraphList) {
                $html .= $item->toHtml();
            } else {
                $html .= '<li>' . $item->getHtmlContents() . '</li>';
            }
        }
        $html .= '</' . $listTag . '>';

        return $html;
    }

    /**
     * @return bool
     */
    public function isList(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public function isBullet(): bool
    {
        foreach ($this->items as $item) {
            return $item->isBullet();
        }
        return false;
    }
}
