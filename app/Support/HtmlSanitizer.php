<?php

namespace App\Support;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Cleans HTML written by store staff (product descriptions, pages). It is shown on the store's own
 * domain, where its staff are signed in, so a script in it could act as anyone who opens the page. This keeps what the
 * block editor makes — block comments, text formatting, links, pictures, tables, video, audio and
 * players from the video sites below — and drops everything else, whatever it is wrapped in.
 *
 * The cleaned HTML is always written out again from the parsed tree, never passed through as typed,
 * so the browser reads exactly what was checked.
 */
class HtmlSanitizer
{
    /**
     * Elements kept, each with the attributes it may carry besides those in COMMON_ATTRIBUTES.
     */
    private const ELEMENTS = [
        'a' => ['href', 'target', 'rel', 'hreflang'],
        'abbr' => [], 'address' => [], 'article' => [], 'aside' => [], 'b' => [], 'bdi' => [], 'bdo' => [],
        'blockquote' => ['cite'], 'br' => [], 'caption' => [], 'center' => [], 'cite' => [], 'code' => [],
        'col' => ['span', 'width'], 'colgroup' => ['span', 'width'], 'dd' => [], 'del' => ['cite', 'datetime'],
        'details' => ['open'], 'dfn' => [], 'div' => [], 'dl' => [], 'dt' => [], 'em' => [], 'figcaption' => [],
        'figure' => [], 'footer' => [], 'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
        'header' => [], 'hr' => [], 'i' => [], 'ins' => ['cite', 'datetime'], 'kbd' => [], 'li' => ['value'],
        'main' => [], 'mark' => [], 'nav' => [], 'ol' => ['start', 'reversed', 'type'], 'p' => [], 'picture' => [],
        'pre' => [], 'q' => ['cite'], 's' => [], 'samp' => [], 'section' => [], 'small' => [], 'span' => [],
        'strike' => [], 'strong' => [], 'sub' => [], 'summary' => [], 'sup' => [],
        'table' => ['width'], 'tbody' => [], 'td' => ['colspan', 'rowspan', 'headers', 'width'], 'tfoot' => [],
        'th' => ['colspan', 'rowspan', 'headers', 'scope', 'abbr', 'width'], 'thead' => [], 'time' => ['datetime'],
        'tr' => [], 'u' => [], 'ul' => [], 'var' => [], 'wbr' => [],
        'img' => ['src', 'srcset', 'sizes', 'alt', 'width', 'height', 'loading', 'decoding'],
        'video' => ['src', 'poster', 'controls', 'autoplay', 'loop', 'muted', 'playsinline', 'preload', 'width', 'height'],
        'audio' => ['src', 'controls', 'autoplay', 'loop', 'muted', 'preload'],
        'source' => ['src', 'srcset', 'sizes', 'type', 'media'],
        'track' => ['src', 'kind', 'srclang', 'label', 'default'],
        'iframe' => ['src', 'width', 'height', 'allow', 'allowfullscreen', 'frameborder', 'loading', 'referrerpolicy', 'scrolling'],
    ];

    private const COMMON_ATTRIBUTES = ['class', 'id', 'style', 'title', 'lang', 'dir', 'role'];

    /**
     * Data attributes that Facebook, X and Instagram embeds read. None of them is read by this site's own scripts.
     */
    private const DATA_ATTRIBUTES = [
        'data-href', 'data-width', 'data-height', 'data-show-text', 'data-lazy', 'data-allowfullscreen',
        'data-lang', 'data-theme', 'data-dnt', 'data-instgrm-permalink', 'data-instgrm-version', 'data-instgrm-captioned',
        'data-id', 'data-align', 'data-type',
    ];

    private const URL_ATTRIBUTES = ['href', 'src', 'poster', 'cite', 'data-href', 'data-instgrm-permalink'];

    /**
     * Removed together with everything inside them. Any other element that is not kept is
     * unwrapped: it goes, and what it held is checked like everything else.
     */
    private const DROPPED_WITH_CONTENT = [
        'script', 'style', 'template', 'svg', 'math', 'object', 'embed', 'applet', 'frame', 'frameset', 'noframes',
        'noscript', 'noembed', 'xmp', 'plaintext', 'textarea', 'title', 'select', 'option', 'optgroup', 'datalist',
        'button', 'input', 'form', 'meta', 'link', 'base', 'head', 'canvas', 'dialog', 'portal', 'slot', 'iframe',
    ];

    /**
     * Players that may be embedded, by host and the path their embed addresses start with.
     */
    private const FRAME_SOURCES = [
        'www.youtube.com' => '/embed/',
        'youtube.com' => '/embed/',
        'www.youtube-nocookie.com' => '/embed/',
        'www.facebook.com' => '/plugins/',
        'player.vimeo.com' => '/video/',
        'www.dailymotion.com' => '/embed/',
        'open.spotify.com' => '/embed',
        'w.soundcloud.com' => '/player/',
        'www.google.com' => '/maps/embed',
    ];

    public static function clean(?string $html): string
    {
        $html = (string) $html;

        if (trim($html) === '') {
            return $html;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="sanitizer-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET | LIBXML_COMPACT
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = null;

        foreach ($document->childNodes as $node) {
            if ($node instanceof DOMElement && $node->getAttribute('id') === 'sanitizer-root') {
                $root = $node;
            }
        }

        if (! $root) {
            return '';
        }

        static::cleanChildren($root);

        $output = '';

        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return $output;
    }

    private static function cleanChildren(DOMNode $parent): void
    {
        $child = $parent->firstChild;

        while ($child) {
            $next = $child->nextSibling;

            if ($child instanceof DOMElement) {
                $name = strtolower($child->localName ?? $child->nodeName);

                if (isset(self::ELEMENTS[$name]) && ($name !== 'iframe' || static::isAllowedFrame($child))) {
                    static::cleanAttributes($child, $name);

                    // A player shows its own page; anything written inside it is never meant to be seen.
                    if ($name === 'iframe') {
                        while ($child->firstChild) {
                            $child->removeChild($child->firstChild);
                        }
                    } else {
                        static::cleanChildren($child);
                    }
                } elseif (in_array($name, self::DROPPED_WITH_CONTENT, true)) {
                    $parent->removeChild($child);
                } else {
                    $first = $child->firstChild;

                    while ($child->firstChild) {
                        $parent->insertBefore($child->firstChild, $child);
                    }

                    $parent->removeChild($child);
                    $next = $first ?? $next;
                }
            } elseif ($child instanceof DOMComment) {
                if (! static::isBlockComment($child->data)) {
                    $parent->removeChild($child);
                }
            } elseif (! $child instanceof DOMText) {
                $parent->removeChild($child);
            }

            $child = $next;
        }
    }

    private static function cleanAttributes(DOMElement $element, string $name): void
    {
        $allowed = array_merge(self::COMMON_ATTRIBUTES, self::ELEMENTS[$name]);

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $key = strtolower($attribute->nodeName);
            $value = $attribute->value;
            $keep = in_array($key, $allowed, true)
                || in_array($key, self::DATA_ATTRIBUTES, true)
                || str_starts_with($key, 'aria-');

            if ($keep && in_array($key, self::URL_ATTRIBUTES, true)) {
                $keep = static::isSafeUrl($value, $key === 'src' && $name === 'img');
            } elseif ($keep && $key === 'srcset') {
                $keep = collect(explode(',', $value))->every(fn ($candidate) => static::isSafeUrl(strtok(trim($candidate), " \t\n") ?: '', true));
            } elseif ($keep && $key === 'style') {
                $value = static::cleanStyle($value);
                $keep = $value !== '';
            } elseif ($keep && $key === 'target') {
                $keep = in_array(strtolower($value), ['_blank', '_self'], true);
            }

            if (! $keep) {
                $element->removeAttribute($attribute->nodeName);
            } elseif ($value !== $attribute->value) {
                $element->setAttribute($attribute->nodeName, $value);
            }
        }

        if ($name === 'a' && strtolower($element->getAttribute('target')) === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    /**
     * http and https anywhere, mail and phone links on links, and pictures written into an image.
     * Browsers ignore tabs, line breaks and control characters in an address, so they are taken
     * out before the scheme is read.
     */
    private static function isSafeUrl(string $url, bool $isImage = false): bool
    {
        $plain = preg_replace('/[\x00-\x20\x7F]+/', '', $url);

        if (! preg_match('~^([a-zA-Z][a-zA-Z0-9+.-]*):~', $plain, $match)) {
            // No scheme: a path on this site, a #section or a ?query.
            return ! str_contains($plain, ':') || preg_match('~^[^:]*[/?#]~', $plain) === 1;
        }

        return match (strtolower($match[1])) {
            'http', 'https', 'mailto', 'tel' => true,
            'data' => $isImage && preg_match('~^data:image/(?:png|gif|jpe?g|webp|avif);base64,~i', $plain) === 1,
            default => false,
        };
    }

    private static function isAllowedFrame(DOMElement $frame): bool
    {
        $src = preg_replace('/[\x00-\x20\x7F]+/', '', $frame->getAttribute('src'));
        $parts = parse_url($src);

        if (! is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['port'])) {
            return false;
        }

        $path = self::FRAME_SOURCES[strtolower($parts['host'] ?? '')] ?? null;

        return $path !== null && str_starts_with($parts['path'] ?? '', $path);
    }

    /**
     * Keeps style declarations, apart from any that load something other than an ordinary picture
     * address or that hide what they say behind CSS escapes.
     */
    private static function cleanStyle(string $style): string
    {
        $declarations = [];

        foreach (explode(';', $style) as $declaration) {
            $declaration = trim($declaration);

            if ($declaration === '' || str_contains($declaration, '\\')
                || preg_match('/expression|javascript|vbscript|@import|behaviou?r|-moz-binding|<|>/i', $declaration)) {
                continue;
            }

            preg_match_all('/url\(\s*([\'"]?)(.*?)\1\s*\)/i', $declaration, $urls);

            if (substr_count(strtolower($declaration), 'url(') !== count($urls[2])
                || collect($urls[2])->contains(fn ($url) => ! static::isSafeUrl($url))) {
                continue;
            }

            $declarations[] = $declaration;
        }

        return implode('; ', $declarations);
    }

    /**
     * Block editor markers such as <!-- wp:paragraph --> and <!-- /wp:image -->. The editor writes "--",
     * "<" and ">" inside them as \u escapes, so a marker holding any of them was not made by it.
     */
    private static function isBlockComment(string $data): bool
    {
        return ! str_contains($data, '--')
            && ! preg_match('/[<>!]/', $data)
            && preg_match('~^\s*/?wp:[a-z][a-z0-9_-]*(?:/[a-z][a-z0-9_-]*)?(?:\s+\{.*\})?\s*/?\s*$~s', $data) === 1;
    }
}
