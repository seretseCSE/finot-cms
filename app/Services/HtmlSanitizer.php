<?php

namespace App\Services;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonySanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class HtmlSanitizer
{
    private static ?SymfonySanitizer $instance = null;

    private static function sanitizer(): SymfonySanitizer
    {
        if (! self::$instance) {
            $config = (new HtmlSanitizerConfig())
                ->allowSafeElements()
                ->allowElement('iframe', ['src', 'width', 'height', 'frameborder', 'allowfullscreen', 'loading', 'title', 'referrerpolicy', 'style'])
                ->allowElement('video', ['src', 'controls', 'width', 'height', 'poster', 'preload'])
                ->allowElement('audio', ['src', 'controls'])
                ->allowElement('source', ['src', 'type'])
                ->allowElement('figure', ['class'])
                ->allowElement('figcaption', ['class'])
                ->allowAttribute('class', allowedElements: '*')
                ->allowAttribute('style', allowedElements: ['iframe', 'span', 'div', 'p', 'table', 'td', 'th'])
                ->allowAttribute('id', allowedElements: '*')
                ->allowAttribute('target', allowedElements: ['a'])
                ->allowAttribute('rel', allowedElements: ['a'])
                ->forceAttribute('a', 'rel', 'noopener noreferrer')
                ->allowMediaSchemes(['https', 'http', 'data'])
                ->blockElement('script')
                ->blockElement('object')
                ->blockElement('embed')
                ->blockElement('form')
                ->blockElement('input');

            self::$instance = new SymfonySanitizer($config);
        }

        return self::$instance;
    }

    /**
     * Sanitize user-authored HTML (from RichEditor fields).
     */
    public static function clean(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        return self::sanitizer()->sanitize($html);
    }
}
