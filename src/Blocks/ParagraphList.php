<?php

namespace Avadim\FastDocxReader\Blocks;

class ParagraphList extends Paragraph
{
    /** @var Paragraph[]|ParagraphList[] */
    protected array $items = [];

    public function __construct(string $text = '', string $xml = '')
    {
        parent::__construct($text, $xml);
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

    public function getText(): string
    {
        $text = '';
        foreach ($this->items as $item) {
            if ($item instanceof ParagraphList) {
                $text .= "\t" . str_replace("\n", "\n\t", trim($item->getText())) . "\n";
            } else {
                $marker = $item->getMarker();
                if ($marker !== null) {
                    $text .= $marker . ' ' . $item->getText() . "\n";
                } else {
                    $text .= $item->getText() . "\n";
                }
            }
        }
        return $text;
    }

    public function getType(): string
    {
        return 'list';
    }

    /**
     * @param string $tag
     * @return string
     */
    public function getHtml(string $tag = ''): string
    {
        $listTag = $this->isBullet() ? 'ul' : 'ol';
        $html = '<' . $listTag . '>';
        foreach ($this->items as $item) {
            if ($item instanceof ParagraphList) {
                $html .= $item->getHtml();
            } else {
                $html .= '<li>' . $item->getHtmlText() . '</li>';
            }
        }
        $html .= '</' . $listTag . '>';
        if ($tag) {
            $html = '<' . $tag . '>' . $html . '</' . $tag . '>';
        }
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
