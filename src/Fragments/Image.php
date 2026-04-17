<?php

namespace avadim\FastDocxReader\Fragments;
use avadim\FastDocxReader\Docx;

class Image implements FragmentInterface
{
    /** @var array */
    protected array $style = [];

    /** @var Docx|null */
    protected ?Docx $docx = null;

    /** @var string|null */
    protected ?string $rId = null;

    /** @var array */
    protected array $size = [];

    /**
     * @param array $style
     * @param string|null $rId
     * @param array $size
     */
    public function __construct(array $style = [], ?string $rId = null, array $size = [])
    {
        $this->style = $style;
        $this->rId = $rId;
        $this->size = $size;
    }

    /**
     * @param Docx|null $docx
     */
    public function setDocx(?Docx $docx): void
    {
        $this->docx = $docx;
    }

    /**
     * @return string|null
     */
    public function getRid(): ?string
    {
        return $this->rId;
    }

    /**
     * @return array
     */
    public function getSize(): array
    {
        return $this->size;
    }
    /**
     * @return string
     */
    public function getType(): string
    {
        return 'image';
    }

    /**
     * @return string
     */
    public function getText(): string
    {
        return '';
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

    /**
     * @return string
     */
    public function getHtml(): string
    {
        $styles = [];
        if (!empty($this->size['width'])) {
            $styles[] = 'width:' . round($this->size['width'] / 12700) . 'pt';
        }
        if (!empty($this->size['height'])) {
            $styles[] = 'height:' . round($this->size['height'] / 12700) . 'pt';
        }
        $styleStr = $styles ? ' style="' . implode(';', $styles) . '"' : '';

        if ($this->docx && $this->rId) {
            $content = $this->docx->getImageContent($this->rId);
            if ($content) {
                $mimeType = $this->docx->getImageMimeType($this->rId) ?: 'image/jpeg';
                $base64 = base64_encode($content);
                return '<img src="data:' . $mimeType . ';base64,' . $base64 . '"' . $styleStr . '>';
            }
        }

        return '';
    }
}
