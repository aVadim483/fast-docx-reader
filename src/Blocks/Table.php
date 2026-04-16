<?php

namespace avadim\FastDocxReader\Blocks;

class Table implements BlockInterface
{
    /** @var array */
    protected array $rows = [];

    /** @var array */
    protected array $style = [];

    public function __construct(array $rows, array $style = [])
    {
        $this->rows = $rows;
        $this->style = $style;
    }

    public function getText(): string
    {
        $text = '';
        foreach ($this->rows as $row) {
            $rowText = [];
            $cells = is_array($row) && isset($row['cells']) ? $row['cells'] : $row;
            foreach ($cells as $cell) {
                if (is_array($cell) && isset($cell['value'])) {
                    $cellValue = $cell['value'];
                } else {
                    $cellValue = $cell;
                }
                if (is_array($cellValue)) {
                    $cellText = '';
                    foreach ($cellValue as $value) {
                        if ($value instanceof BlockInterface) {
                            $cellText .= $value->getText();
                        } else {
                            $cellText .= (string)$value;
                        }
                    }
                    $rowText[] = $cellText;
                } elseif ($cellValue instanceof BlockInterface) {
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
        if (isset($this->rows[$rowNum])) {
            $row = $this->rows[$rowNum];
            $cells = is_array($row) && isset($row['cells']) ? $row['cells'] : $row;
            if (isset($cells[$cellNum])) {
                $cell = $cells[$cellNum];
                if (is_array($cell) && isset($cell['value'])) {
                    return $cell['value'];
                }
                return $cell;
            }
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
        if (isset($this->rows[$rowNum])) {
            $row = $this->rows[$rowNum];
            $cells = is_array($row) && isset($row['cells']) ? $row['cells'] : $row;
            if (isset($cells[$cellNum])) {
                $cell = $cells[$cellNum];
                if (is_array($cell) && isset($cell['style'])) {
                    return $cell['style'];
                }
            }
        }

        return null;
    }

    /**
     * @return array
     */
    public function getStyle(): array
    {
        return $this->style;
    }

    /**
     * @return string
     */
    public function getHtml(): string
    {
        $tableStyle = 'border-collapse: collapse;';
        if (!empty($this->style['tblW']['w']) && !empty($this->style['tblW']['type'])) {
            if ($this->style['tblW']['type'] === 'dxa') {
                $tableStyle .= ' width: ' . ($this->style['tblW']['w'] / 20) . 'pt;';
            } elseif ($this->style['tblW']['type'] === 'pct') {
                $tableStyle .= ' width: ' . ($this->style['tblW']['w'] / 50) . '%;';
            } elseif ($this->style['tblW']['type'] === 'auto') {
                $tableStyle .= ' width: auto;';
            }
        }
        if (!empty($this->style['tblBorders'])) {
            foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $side) {
                if (isset($this->style['tblBorders'][$side])) {
                    // We can handle some basic borders here if needed, 
                    // but usually they are applied to cells
                }
            }
        }

        $html = '<table' . ($tableStyle ? ' style="' . trim($tableStyle) . '"' : '') . '>';
        foreach ($this->rows as $row) {
            $rowStyle = [];
            if (is_array($row) && isset($row['cells'])) {
                $cells = $row['cells'];
                $rowStyle = $row['style'] ?? [];
            } else {
                $cells = $row;
            }

            $trStyle = '';
            $height = $rowStyle['trHeight']['val'] ?? ($rowStyle['trHeight'] ?? null);
            if ($height) {
                $trStyle .= ' height: ' . ($height / 20) . 'pt;';
            }

            $html .= '<tr' . ($trStyle ? ' style="' . trim($trStyle) . '"' : '') . '>';
            foreach ($cells as $cell) {
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
                if (is_array($cellValues)) {
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
                } elseif ($cellValues instanceof Paragraph) {
                    $html .= $cellValues->getHtml();
                } elseif ($cellValues instanceof BlockInterface) {
                    if (method_exists($cellValues, 'getHtml')) {
                        $html .= $cellValues->getHtml();
                    } else {
                        $html .= htmlspecialchars($cellValues->getText());
                    }
                } else {
                    $html .= htmlspecialchars((string)$cellValues);
                }
                $html .= '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table>';

        return $html;
    }
}
