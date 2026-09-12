<?php

declare(strict_types=1);

/**
 * Return the small, safe HTML subset supported by the news editor.
 */
function sanitize_news_rich_text(string $html): string
{
    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8"><div id="news-rich-text-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $root = $document->getElementById('news-rich-text-root');
    if (!$root) {
        return '';
    }

    $allowedTags = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'span'];
    $dangerousTags = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math'];
    $walk = function (DOMNode $parent) use (&$walk, $allowedTags, $dangerousTags): void {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node instanceof DOMElement) {
                $tag = strtolower($node->tagName);
                if (in_array($tag, $dangerousTags, true)) {
                    $node->parentNode?->removeChild($node);
                    continue;
                }
                if (!in_array($tag, $allowedTags, true)) {
                    $walk($node);
                    while ($node->firstChild) {
                        $node->parentNode?->insertBefore($node->firstChild, $node);
                    }
                    $node->parentNode?->removeChild($node);
                    continue;
                }

                $safeStyles = [];
                if ($node->hasAttribute('style')) {
                    foreach (explode(';', $node->getAttribute('style')) as $declaration) {
                        [$property, $value] = array_pad(explode(':', $declaration, 2), 2, '');
                        $property = strtolower(trim($property));
                        $value = strtolower(trim($value));
                        if ($property === 'font-size' && preg_match('/^(12|14|16|18|20|22|24|28|32|36|40|48)px$/', $value)) {
                            $safeStyles[] = 'font-size:' . $value;
                        } elseif ($property === 'text-align' && in_array($value, ['left', 'center', 'right', 'justify'], true)) {
                            $safeStyles[] = 'text-align:' . $value;
                        }
                    }
                }
                foreach (iterator_to_array($node->attributes) as $attribute) {
                    $node->removeAttribute($attribute->name);
                }
                if ($safeStyles) {
                    $node->setAttribute('style', implode(';', $safeStyles));
                }
                $walk($node);
            }
        }
    };
    $walk($root);

    $result = '';
    foreach ($root->childNodes as $child) {
        $result .= $document->saveHTML($child);
    }
    return trim($result);
}

function news_rich_text_has_content(string $html): bool
{
    return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) !== '';
}

/** Convert existing plain-text records to editor-safe HTML without changing them. */
function news_rich_text_for_editor(string $content): string
{
    if ($content === strip_tags($content)) {
        return nl2br(htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false);
    }
    return sanitize_news_rich_text($content);
}
