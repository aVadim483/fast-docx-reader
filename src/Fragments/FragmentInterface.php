<?php

namespace avadim\FastDocxReader\Fragments;

interface FragmentInterface
{
    public function getType(): string;

    public function getText(): string;
    public function getHtml(): string;
    public function getStyle(): array;
    public function setStyle(array $style): void;
}
