<?php

namespace Avadim\FastDocxReader\Blocks;

class Table implements BlockInterface
{
    /** @var array */
    protected array $rows = [];

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function getText(): string
    {
        $text = '';
        foreach ($this->rows as $row) {
            $rowText = [];
            foreach ($row as $cell) {
                $rowText[] = $cell;
            }
            $text .= implode("\t", $rowText) . "\n";
        }
        return $text;
    }

    public function getType(): string
    {
        return 'table';
    }

    /**
     * @return array
     */
    public function getRows(): array
    {
        return $this->rows;
    }
}
