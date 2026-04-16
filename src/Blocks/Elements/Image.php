<?php

namespace avadim\FastDocxReader\Blocks\Elements;

class Image implements ElementInterface
{
    /** @var array */
    protected array $style = [];

    /**
     * @param array $style
     */
    public function __construct(array $style = [])
    {
        $this->style = $style;
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
}
