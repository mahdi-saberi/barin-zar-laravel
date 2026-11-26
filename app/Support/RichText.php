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
        's' => 's', 'strike' => 's',
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

        $withSpaces = preg_replace('~</?(?:p|div|h[2-4]|li|ul|ol|blockquote|br|img|video)\b[^>]*>~i', ' ', $value);

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
        if (in_array($node->tagName, ['img', 'video'], true)) {
            $src = $node->getAttribute('src');
            $type = Media::type($src);
            if ($type === 'image' && $node->tagName === 'img') {
                return '<img src="'.e($src).'" alt="'.e(Str::limit($node->getAttribute('alt'), 180, '')).'" loading="lazy" decoding="async">';
            }

            return $type === 'video' && $node->tagName === 'video'
                ? '<video src="'.e($src).'" controls preload="metadata" playsinline></video>' : '';
        }

        $content = self::children($node);
        if ($node->tagName === 'a') {
            $href = trim($node->getAttribute('href'));

            return self::validLink($href) ? '<a href="'.e($href).'">'.$content.'</a>' : $content;
        }
        $tag = self::TAGS[$node->tagName] ?? null;

        if (!$tag) {
            return $content;
        }

        $classes = [];
        if (in_array($tag, ['p', 'div', 'h2', 'h3', 'h4', 'li', 'blockquote'], true)) {
            foreach (preg_split('/\s+/', trim($node->getAttribute('class'))) as $class) {
                if (preg_match('/\Aql-(?:align-(?:left|right|center|justify)|direction-(?:ltr|rtl))\z/', $class)) {
                    $classes[] = $class;
                }
            }
        }
        $attribute = $classes ? ' class="'.implode(' ', array_unique($classes)).'"' : '';

        return '<'.$tag.$attribute.'>'.$content.'</'.$tag.'>';
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
