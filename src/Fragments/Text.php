<?php

namespace avadim\FastDocxReader\Fragments;

use avadim\FastDocxReader\Docx;
use avadim\FastDocxReader\Interfaces\FragmentInterface;
use avadim\FastDocxReader\Options\HtmlOptions;
use avadim\FastDocxReader\Options\PlainTextOptions;

class Text implements FragmentInterface
{
    /** @var string */
    protected string $text;

    /** @var bool */
    protected bool $isBreak = false;

    /** @var bool */
    protected bool $isTab = false;

    /** @var array */
    protected array $style = [];

    /**
     * @param string $text
     * @param bool $isBreak
     * @param bool $isTab
     * @param array $style
     */
    public function __construct(string $text, bool $isBreak = false, bool $isTab = false, array $style = [])
    {
        $this->text = $text;
        $this->isBreak = $isBreak;
        $this->isTab = $isTab;
        $this->style = $style;
    }

    /**
     * @return bool
     */
    public function isBreak(): bool
    {
        return $this->isBreak;
    }

    /**
     * @return bool
     */
    public function isTab(): bool
    {
        return $this->isTab;
    }

    /**
     * @param PlainTextOptions|null $options
     *
     * @return string
     */
    public function getText(?PlainTextOptions $options = null): string
    {
        $options = $options ?? Docx::getPlainTextOptions();
        if ($this->isBreak) {
            return $options->breakChar;
        }
        if ($this->isTab) {
            return $options->tabChar;
        }
        return $this->text;
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
        $tag = 'span';
        $styles = [];
        if (!empty($this->style['b']) || !empty($this->style['bCs'])) {
            $styles[] = 'font-weight:bold';
        }
        if (!empty($this->style['i']) || !empty($this->style['iCs'])) {
            $styles[] = 'font-style:italic';
        }
        if (!empty($this->style['u'])) {
            $styles[] = 'text-decoration:underline';
        }
        if (!empty($this->style['color']) && is_string($this->style['color'])) {
            $styles[] = 'color:#' . $this->style['color'];
        }
        elseif (!empty($this->style['color']['val']) && is_string($this->style['color']['val'])) {
            $styles[] = 'color:#' . $this->style['color']['val'];
        }
        if (!empty($this->style['highlight'])) {
            $styles[] = 'background-color:' . $this->style['highlight'];
        }
        if (!empty($this->style['sz'])) {
            $styles[] = 'font-size:' . ((float)$this->style['sz'] / 2) . 'pt';
        }

        $styleStr = $styles ? ' style="' . implode(';', $styles) . '"' : '';

        if ($this->isBreak) {
            $html = '<br>';
        } else {
            $html = htmlspecialchars($this->text);
        }

        if ($tag) {
            return '<' . $tag . $styleStr . '>' . $html . '</' . $tag . '>';
        }

        if ($styleStr) {
            return '<span' . $styleStr . '>' . $html . '</span>';
        }

        return $html;
    }
}
