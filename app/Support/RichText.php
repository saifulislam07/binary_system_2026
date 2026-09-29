<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Admin-written rich text (product descriptions). Only a small allow-list
 * of formatting tags survives; scripts, styles, event handlers and unsafe
 * links are stripped. Sanitized when saved and again when rendered, so
 * older plain-text values and anything written straight to the database
 * are safe to print with v-html.
 */
final class RichText
{
    private const MAX_INPUT = 50_000;

    private static ?HtmlSanitizer $sanitizer = null;

    /**
     * Sanitized HTML (legacy plain text becomes paragraphs), or null when
     * nothing readable is left.
     */
    public static function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $clean = trim(self::sanitizer()->sanitize(self::fromPlainText($html)));

        // Empty editor output ("<p><br></p>") counts as no description.
        return self::toPlain($clean) === '' ? null : $clean;
    }

    /**
     * Plain text for excerpts, meta descriptions and search.
     */
    public static function toPlain(?string $html): string
    {
        if ($html === null) {
            return '';
        }

        $text = preg_replace('/<\s*(br|\/p|\/li|\/h[1-6]|\/blockquote)\s*\/?>/i', ' ', $html) ?? $html;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * Values without any tag are plain text (one paragraph per blank-line
     * separated block, line breaks kept).
     */
    private static function fromPlainText(string $value): string
    {
        if (preg_match('/<[a-z][^>]*>/i', $value) === 1) {
            return $value;
        }

        $paragraphs = preg_split('/\R{2,}/', trim($value)) ?: [];

        return implode('', array_map(
            fn (string $p) => '<p>'.nl2br(e(trim($p)), false).'</p>',
            $paragraphs,
        ));
    }

    private static function sanitizer(): HtmlSanitizer
    {
        return self::$sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowElement('p')
                ->allowElement('br')
                ->allowElement('strong')
                ->allowElement('b')
                ->allowElement('em')
                ->allowElement('i')
                ->allowElement('u')
                ->allowElement('s')
                ->allowElement('h2')
                ->allowElement('h3')
                ->allowElement('h4')
                ->allowElement('ul')
                ->allowElement('ol')
                ->allowElement('li')
                ->allowElement('blockquote')
                ->allowElement('a', ['href'])
                ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
                ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
                ->forceAttribute('a', 'target', '_blank')
                ->dropElement('script')
                ->dropElement('style')
                ->dropElement('iframe')
                ->dropElement('object')
                ->withMaxInputLength(self::MAX_INPUT),
        );
    }
}
