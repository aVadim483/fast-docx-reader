<?php

namespace Avadim\FastDocxReader\Blocks;

class ParagraphList implements BlockInterface
{
    /** @var Paragraph[] */
    protected array $items = [];

    /**
     * @param Paragraph $paragraph
     * @return void
     */
    public function addItem(Paragraph $paragraph): void
    {
        $this->items[] = $paragraph;
    }

    /**
     * @return Paragraph[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getText(): string
    {
        $text = '';
        foreach ($this->items as $item) {
            $text .= $item->getText() . "\n";
        }
        return $text;
    }

    public function getType(): string
    {
        return 'list';
    }
}
