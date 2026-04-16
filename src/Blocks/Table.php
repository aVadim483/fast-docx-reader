<?php

namespace avadim\FastDocxReader\Blocks;

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
                if (is_array($cell) && isset($cell['value'])) {
                    $cellValue = $cell['value'];
                } else {
                    $cellValue = $cell;
                }
                if ($cellValue instanceof BlockInterface) {
                    $rowText[] = $cellValue->getText();
                } else {
                    $rowText[] = (string)$cellValue;
                }
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

    /**
     * @param int $rowNum
     * @param int $cellNum
     *
     * @return mixed|null
     */
    public function getCell(int $rowNum, int $cellNum)
    {
        if (isset($this->rows[$rowNum][$cellNum])) {
            $cell = $this->rows[$rowNum][$cellNum];
            if (is_array($cell) && isset($cell['value'])) {
                return $cell['value'];
            }
            return $cell;
        }

        return null;
    }

    /**
     * @param int $rowNum
     * @param int $cellNum
     *
     * @return array|null
     */
    public function getCellStyle(int $rowNum, int $cellNum): ?array
    {
        if (isset($this->rows[$rowNum][$cellNum])) {
            $cell = $this->rows[$rowNum][$cellNum];
            if (is_array($cell) && isset($cell['style'])) {
                return $cell['style'];
            }
        }

        return null;
    }

    /**
     * @return string
     */
    public function getHtml(): string
    {
        $html = '<table style="border-collapse: collapse; border: 1px solid black;">';
        foreach ($this->rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                if (is_array($cell) && isset($cell['value'])) {
                    $cellValues = $cell['value'];
                    $cellStyle = $cell['style'] ?? [];
                } else {
                    $cellValues = $cell;
                    $cellStyle = [];
                }

                $tdStyle = 'border: 1px solid black; padding: 4px;';
                if (!empty($cellStyle['tcW']['w']) && !empty($cellStyle['tcW']['type'])) {
                    if ($cellStyle['tcW']['type'] === 'dxa') {
                        $tdStyle .= ' width: ' . ($cellStyle['tcW']['w'] / 20) . 'pt;';
                    } elseif ($cellStyle['tcW']['type'] === 'pct') {
                        $tdStyle .= ' width: ' . ($cellStyle['tcW']['w'] / 50) . '%;';
                    }
                }
                if (!empty($cellStyle['vAlign'])) {
                    $vAlign = $cellStyle['vAlign'];
                    if ($vAlign === 'center') {
                        $vAlign = 'middle';
                    }
                    $tdStyle .= ' vertical-align: ' . $vAlign . ';';
                }
                if (!empty($cellStyle['shd']['fill'])) {
                    $tdStyle .= ' background-color: #' . $cellStyle['shd']['fill'] . ';';
                }
                if (!empty($cellStyle['tcBorders'])) {
                    $borderStyles = '';
                    foreach (['top', 'left', 'bottom', 'right'] as $side) {
                        if (isset($cellStyle['tcBorders'][$side])) {
                            $border = $cellStyle['tcBorders'][$side];
                            if (is_array($border)) {
                                $sz = isset($border['sz']) ? ($border['sz'] / 8) . 'pt' : '1px';
                                $color = isset($border['color']) && $border['color'] !== 'auto' ? '#' . $border['color'] : 'black';
                                $val = (isset($border['val']) && $border['val'] !== 'nil' && $border['val'] !== 'none') ? 'solid' : 'none';
                                $borderStyles .= " border-$side: $sz $val $color;";
                            }
                        }
                    }
                    if ($borderStyles) {
                        $tdStyle = str_replace('border: 1px solid black;', '', $tdStyle) . $borderStyles;
                    }
                }

                $html .= '<td style="' . trim($tdStyle) . '">';
                foreach ($cellValues as $cellValue) {
                    if ($cellValue instanceof Paragraph) {
                        $html .= $cellValue->getHtml();
                    } elseif ($cellValue instanceof BlockInterface) {
                        if (method_exists($cellValue, 'getHtml')) {
                            $html .= $cellValue->getHtml();
                        } else {
                            $html .= htmlspecialchars($cellValue->getText());
                        }
                    } else {
                        $html .= htmlspecialchars((string)$cellValue);
                    }
                }
                $html .= '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table>';

        return $html;
    }
}
