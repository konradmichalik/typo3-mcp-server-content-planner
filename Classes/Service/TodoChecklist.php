<?php

declare(strict_types=1);

/*
 * This file is part of the "typo3_mcp_server_content_planner" TYPO3 CMS extension.
 *
 * (c) 2026 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KonradMichalik\Typo3McpServerContentPlanner\Service;

use DOMDocument;
use DOMElement;
use DOMNode;

use function count;
use function in_array;

/**
 * TodoChecklist.
 *
 * Reads and toggles the checkbox items inside a Content Planner comment's HTML.
 * Items are addressed by their zero-based position in document order.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class TodoChecklist
{
    /**
     * @return list<array{index: int, text: string, resolved: bool}>
     */
    public static function parse(string $html): array
    {
        if ('' === $html) {
            return [];
        }

        $dom = new DOMDocument();
        $previousLibXmlUseErrors = libxml_use_internal_errors(true);
        // The XML declaration makes libxml read the fragment as UTF-8 instead of ISO-8859-1.
        $success = $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_use_internal_errors($previousLibXmlUseErrors);
        if (!$success) {
            return [];
        }

        $todos = [];
        foreach ($dom->getElementsByTagName('input') as $input) {
            if ('checkbox' !== strtolower($input->getAttribute('type'))) {
                continue;
            }

            $todos[] = [
                'index' => count($todos),
                'text' => self::textOf($input),
                'resolved' => $input->hasAttribute('checked'),
            ];
        }

        return $todos;
    }

    /**
     * Returns $html with the checkbox at $index checked or unchecked. Edits the markup
     * string instead of re-serializing a DOM, so the rest of the comment stays byte-identical.
     */
    public static function withResolvedState(string $html, int $index, bool $resolved): string
    {
        $position = -1;

        return (string) preg_replace_callback(
            '/<input\b[^>]*?\stype\s*=\s*["\']?checkbox["\']?[^>]*>/i',
            static function (array $match) use (&$position, $index, $resolved): string {
                ++$position;
                if ($position !== $index) {
                    return $match[0];
                }

                $tag = (string) preg_replace_callback(
                    '/\s+([^\s=\/>"\']+)(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+))?/',
                    static fn (array $attribute): string => 'checked' === strtolower($attribute[1]) ? '' : $attribute[0],
                    $match[0],
                );
                if (!$resolved) {
                    return $tag;
                }

                return (string) preg_replace('/\s*(\/?)>$/', ' checked="checked"$1>', $tag);
            },
            $html,
        );
    }

    private static function textOf(DOMElement $input): string
    {
        $container = $input->parentNode;
        while ($container instanceof DOMNode && !in_array($container->nodeName, ['li', 'label'], true)) {
            $container = $container->parentNode;
        }

        return $container instanceof DOMNode ? trim($container->textContent) : '';
    }
}
