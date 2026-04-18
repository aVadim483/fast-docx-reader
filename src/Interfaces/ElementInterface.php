<?php

namespace avadim\FastDocxReader\Interfaces;

interface ElementInterface
{
    public function getType(): string;

    public function getText(): string;
    public function toHtml(): string;
    public function getStyleOptions(): array;
    public function setStyleOptions(array $style): void;
}
