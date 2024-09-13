<?php

/** @noinspection PhpUnused */
declare(strict_types=1);

namespace Itools\SmartString\Methods;

/**
 * String methods for SmartString class.
 */
class Strings
{
    /**
     * @param int|float|string|null $value
     * @return string|null
     */
    public static function textOnly(int|float|string|null $value): string|null
    {
        if (is_null($value)) {
            return null;
        }
        $textOnly = html_entity_decode((string)$value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        return trim(strip_tags($textOnly));
    }

    /**
     * @param int|float|string|null $value
     * @return string|null
     */
    public static function nl2br(int|float|string|null $value): string|null
    {
        if (is_null($value)) {
            return null;
        }
        return nl2br((string)$value, false);
    }

    /**
     * @param int|float|string|null $value
     * @param ...$args
     * @return string|null
     */
    public static function trim(int|float|string|null $value, ...$args): string|null
    {
        if (is_null($value)) {
            return null;
        }
        return trim((string)$value, ...$args);
    }

    /**
     * @param int|float|string|null $value
     * @param int $max
     * @param string $ellipsis
     * @return string|null
     */
    public static function maxWords(int|float|string|null $value, int $max, string $ellipsis = "..."): string|null
    {
        if (is_null($value)) {
            return null;
        }

        $text     = trim((string)$value);
        $words    = preg_split('/\s+/u', $text);
        $newValue = implode(' ', array_slice($words, 0, $max));
        $newValue = preg_replace('/\p{P}+$/u', '', $newValue); // Remove trailing punctuation
        if (count($words) > $max) {
            $newValue .= $ellipsis;
        }
        return $newValue;
    }

    /**
     * @param int|float|string|null $value
     * @param int $max
     * @param string $ellipsis
     * @return string|null
     */
    public static function maxChars(int|float|string|null $value, int $max, string $ellipsis = "..."): string|null
    {
        if (is_null($value)) {
            return null;
        }

        $text = preg_replace('/\s+/u', ' ', trim((string)$value));

        if (mb_strlen($text) <= $max) {
            $newValue = $text;
        } elseif ($max > 0 && preg_match("/^.{1,$max}(?=\s|$)/u", $text, $matches)) {
            $newValue = $matches[0];
            $newValue = preg_replace('/\p{P}+$/u', '', $newValue); // Remove trailing punctuation
            $newValue .= $ellipsis;
        } else {
            $newValue = mb_substr($text, 0, $max) . $ellipsis;
        }

        return $newValue;
    }
    /**
     * @param int|float|string|null $value
     * @param ...$args
     * @return string|null
     * @deprecated Use textOnly() instead, this method may be removed in the future.
     */
    public static function stripTags(int|float|string|null $value, ...$args): string|null
    {
        if (is_null($value)) {
            return null;
        }
        return strip_tags((string)$value, ...$args);
    }
}
