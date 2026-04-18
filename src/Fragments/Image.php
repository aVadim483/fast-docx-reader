<?php

namespace avadim\FastDocxReader\Fragments;
use avadim\FastDocxReader\Docx;
use avadim\FastDocxReader\Interfaces\FragmentInterface;
use avadim\FastDocxReader\Options\HtmlOptions;
use avadim\FastDocxReader\Options\PlainTextOptions;

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
     * @param PlainTextOptions|null $options
     * @return string
     */
    public function getText(?PlainTextOptions $options = null): string
    {
        $options = $options ?? Docx::getPlainTextOptions();
        if (!$options->ignoreImages) {
            return $options->imagePlaceholder;
        }
        return '';
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

    /**
     * @param HtmlOptions|null $options
     * @return string
     */
    public function toHtml(?HtmlOptions $options = null): string
    {
        $options = $options ?? Docx::getHtmlOptions();
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
