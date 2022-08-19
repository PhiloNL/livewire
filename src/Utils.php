<?php

namespace Livewire;

use Livewire\Exceptions\RootTagMissingFromViewException;

class Utils
{
    static function insertAttributesIntoHtmlRoot($html, $attributes) {
        $attributesFormattedForHtmlElement = collect($attributes)
            ->mapWithKeys(function ($value, $key) {
                return [$key => static::escapeStringForHtml($value)];
            })->map(function ($value, $key) {
                return sprintf('%s="%s"', $key, $value);
            })->implode(' ');

        preg_match('/(?:\n\s*|^\s*)<([a-zA-Z0-9\-]+)/', $html, $matches, PREG_OFFSET_CAPTURE);

        throw_unless(
            count($matches),
            new RootTagMissingFromViewException
        );

        $tagName = $matches[1][0];
        $lengthOfTagName = strlen($tagName);
        $positionOfFirstCharacterInTagName = $matches[1][1];

        return substr_replace(
            $html,
            ' '.$attributesFormattedForHtmlElement,
            $positionOfFirstCharacterInTagName + $lengthOfTagName,
            0
        );
    }

    static function escapeStringForHtml($subject)
    {
        if (is_string($subject) || is_numeric($subject)) {
            return htmlspecialchars($subject, ENT_QUOTES|ENT_SUBSTITUTE);
        }

        return htmlspecialchars(json_encode($subject), ENT_QUOTES|ENT_SUBSTITUTE);
    }

    static function pretendResponseIsFile($file, $mimeType = 'application/javascript')
    {
        $expires = strtotime('+1 year');
        $lastModified = filemtime($file);
        $cacheControl = 'public, max-age=31536000';

        if (static::matchesCache($lastModified)) {
            return response()->make('', 304, [
                'Expires' => static::httpDate($expires),
                'Cache-Control' => $cacheControl,
            ]);
        }

        return response()->file($file, [
            'Content-Type' => "$mimeType; charset=utf-8",
            'Expires' => static::httpDate($expires),
            'Cache-Control' => $cacheControl,
            'Last-Modified' => static::httpDate($lastModified),
        ]);
    }

    static function matchesCache($lastModified)
    {
        $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';

        return @strtotime($ifModifiedSince) === $lastModified;
    }

    static function httpDate($timestamp)
    {
        return sprintf('%s GMT', gmdate('D, d M Y H:i:s', $timestamp));
    }

    static function containsDots($subject)
    {
        return strpos($subject, '.') !== false;
    }

    static function beforeFirstDot($subject)
    {
        return head(explode('.', $subject));
    }

    static function afterFirstDot($subject) : string
    {
        return str($subject)->after('.');
    }
}