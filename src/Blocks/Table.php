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

        $html = '<table' . ($tableStyle ? ' style="' . trim($tableStyle) . '"' : '') . '>';

        $mergedCells = []; // [rowIdx][colIdx] = true if cell is vertically merged and should be skipped

        foreach ($this->rows as $rowIdx => $row) {
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
            $colIdx = 0;
            foreach ($cells as $cell) {
                // Skip cells that are covered by a vertical merge from above
                while (isset($mergedCells[$rowIdx][$colIdx])) {
                    $colIdx++;
                }

                if (is_array($cell) && isset($cell['value'])) {
                    $cellValues = $cell['value'];
                    $cellStyle = $cell['style'] ?? [];
                } else {
                    $cellValues = $cell;
                    $cellStyle = [];
                }

                // Horizontal merge
                $colspan = 1;
                if (!empty($cellStyle['gridSpan'])) {
                    $colspan = (int)$cellStyle['gridSpan'];
                }

                // Vertical merge
                $rowspan = 1;
                if (isset($cellStyle['vMerge'])) {
                    $vMerge = $cellStyle['vMerge'];
                    if ($vMerge === 'restart') {
                        // This is the start of a vertical merge.
                        // We need to look ahead to subsequent rows to find how many to merge.
                        $nextRowIdx = $rowIdx + 1;
                        while (isset($this->rows[$nextRowIdx])) {
                            $nextRow = $this->rows[$nextRowIdx];
                            $nextRowCells = is_array($nextRow) && isset($nextRow['cells']) ? $nextRow['cells'] : $nextRow;
                            
                            // Find the corresponding cell in the next row
                            // This is tricky because of gridSpan and previous merges.
                            // We need to match the visual column index.
                            
                            $nextColIdx = 0;
                            $foundCell = null;
                            foreach ($nextRowCells as $nextCell) {
                                if ($nextColIdx === $colIdx) {
                                    $foundCell = $nextCell;
                                    break;
                                }
                                $nextCellStyle = is_array($nextCell) && isset($nextCell['style']) ? $nextCell['style'] : [];
                                $nextColIdx += !empty($nextCellStyle['gridSpan']) ? (int)$nextCellStyle['gridSpan'] : 1;
                                if ($nextColIdx > $colIdx) {
                                    // This shouldn't happen in well-formed DOCX where vMerge aligns with gridSpan
                                    break;
                                }
                            }

                            if ($foundCell) {
                                $nextCellStyle = is_array($foundCell) && isset($foundCell['style']) ? $foundCell['style'] : [];
                                if (isset($nextCellStyle['vMerge']) && $nextCellStyle['vMerge'] !== 'restart') {
                                    $rowspan++;
                                    // Mark these cells as merged so they are skipped when processing their rows
                                    for ($c = 0; $c < $colspan; $c++) {
                                        $mergedCells[$nextRowIdx][$colIdx + $c] = true;
                                    }
                                    $nextRowIdx++;
                                } else {
                                    break;
                                }
                            } else {
                                break;
                            }
                        }
                    } else {
                        // This is a continuation of a vertical merge (vMerge without 'restart' or with empty value)
                        // It should have been marked in $mergedCells when 'restart' was encountered.
                        // If we are here, it means we might have missed the 'restart' or it's an invalid table.
                        // But we should skip it anyway if it's a merge continuation.
                        $colIdx += $colspan;
                        continue;
                    }
                } else {
                    // Not a vertical merge, but might have horizontal merge
                    if ($colspan > 1) {
                        for ($c = 1; $c < $colspan; $c++) {
                            $mergedCells[$rowIdx][$colIdx + $c] = true;
                        }
                    }
                }

                $tdAttributes = '';
                if ($colspan > 1) {
                    $tdAttributes .= ' colspan="' . $colspan . '"';
                }
                if ($rowspan > 1) {
                    $tdAttributes .= ' rowspan="' . $rowspan . '"';
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

                $html .= '<td' . $tdAttributes . ' style="' . trim($tdStyle) . '">';
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
                $colIdx += $colspan;
            }
            $html .= '</tr>';
        }
        $html .= '</table>';

        return $html;
    }
}
