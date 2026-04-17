<?php

namespace avadim\FastDocxReader\Fragments;

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
     * @return string
     */
    public function getType(): string
    {
        return 'text';
    }

    /**
     * @return string
     */
    public function getText(): string
    {
        return $this->text;
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
     * @param string $tag
     * @return string
     */
    public function getHtml(string $tag = 'span'): string
    {
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
