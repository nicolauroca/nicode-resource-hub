<?php

/** @license GPL-2.0-or-later */

namespace Nicode\Lesson\Services;

final class ResourceLabel
{
    public function __construct(private string $prefix)
    {
    }

    public function forTitle(string $title): string
    {
        return $this->prefix . ': ' . $title;
    }
}
