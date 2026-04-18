<?php

namespace avadim\FastDocxReader\Interfaces;

use avadim\FastDocxReader\Options\HtmlOptions;
use avadim\FastDocxReader\Options\PlainTextOptions;

interface ElementInterface
{
    public function getText(?PlainTextOptions $options = null): string;

    public function toHtml(?HtmlOptions $options = null): string;

    public function getStyleProps(): array;

    public function setStyleProps(array $style): void;
}
