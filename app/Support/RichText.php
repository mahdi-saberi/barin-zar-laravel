<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class RichText
{
    private const TAGS = [
        'p' => 'p', 'div' => 'div', 'h2' => 'h2', 'h3' => 'h3', 'h4' => 'h4',
        'b' => 'strong', 'strong' => 'strong', 'i' => 'em', 'em' => 'em', 'u' => 'u',
        'ul' => 'ul', 'ol' => 'ol', 'li' => 'li', 'blockquote' => 'blockquote',
    ];

    public static function render(array $record, string $field, string $fallback = ''): HtmlString
    {
        $value = $record[$field] ?? null;

        return new HtmlString(is_string($value) && !empty($record[$field.'_html'])
            ? self::sanitize($value)
            : nl2br(e(is_string($value) ? $value : $fallback), false));
    }

    public static function text(array $record, string $field, string $fallback = ''): string
    {
        $value = $record[$field] ?? null;
        if (!is_string($value)) {
            return $fallback;
        }
        if (empty($record[$field.'_html'])) {
            return $value;
        }

        $withSpaces = preg_replace('~</?(?:p|div|h[2-4]|li|ul|ol|blockquote)>|<br\s*/?>~i', ' ', $value);

        return Str::squish(html_entity_decode(strip_tags($withSpaces), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    public static function sanitize(string $html): string
    {
        $document = new DOMDocument;
        $document->loadHTML('<!doctype html><html><head><meta charset="utf-8"></head><body>'.$html.'</body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        // Rebuild the fragment with allowed elements and attributes only.
        $body = $document->getElementsByTagName('body')->item(0);

        return $body ? self::children($body) : '';
    }

    private static function children(DOMNode $parent): string
    {
        $html = '';
        foreach ($parent->childNodes as $node) {
            $html .= self::node($node);
        }

        return $html;
    }

    private static function node(DOMNode $node): string
    {
        if ($node instanceof DOMText) {
            return htmlspecialchars($node->data, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        if (!$node instanceof DOMElement || in_array($node->tagName, ['script', 'style', 'iframe', 'object', 'template', 'svg', 'math', 'select', 'textarea'], true)) {
            return '';
        }
        if ($node->tagName === 'br') {
            return '<br>';
        }

        $content = self::children($node);
        if ($node->tagName === 'a') {
            $href = trim($node->getAttribute('href'));

            return self::validLink($href) ? '<a href="'.e($href).'">'.$content.'</a>' : $content;
        }
        $tag = self::TAGS[$node->tagName] ?? null;

        return $tag ? '<'.$tag.'>'.$content.'</'.$tag.'>' : $content;
    }

    private static function validLink(string $href): bool
    {
        if (preg_match('~[\x00-\x20\x7f\\\\]~', $href)) {
            return false;
        }

        return str_starts_with($href, '#')
            || (str_starts_with($href, '/') && !str_starts_with($href, '//'))
            || (str_starts_with(strtolower($href), 'https://') && filter_var($href, FILTER_VALIDATE_URL) !== false);
    }
}
