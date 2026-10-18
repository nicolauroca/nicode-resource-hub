<?php

namespace Nicode\Plugin\Content\Reading\Helper;

defined('_JEXEC') or die;

final class ReadingTime
{
    public static function minutes(string $html): int
    {
        // Exclude non-reading content before replacing tags with separators.
        $text = preg_replace('~<(script|style)\b[^>]*>.*?</\1\s*>~is', ' ', $html);
        $text = preg_replace('~<[^>]*>~', ' ', $text ?? '');
        $text = html_entity_decode($text ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $words = preg_match_all('/[\p{L}\p{N}]+(?:[\x{2019}\x{0027}-][\p{L}\p{N}]+)*/u', $text);

        // A declared heuristic, not a measurement of any reader's speed.
        return $words === false ? 0 : (int) ceil($words / 200);
    }
}
