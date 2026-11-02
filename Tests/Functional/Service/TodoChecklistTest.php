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

namespace KonradMichalik\Typo3McpServerContentPlanner\Tests\Functional\Service;

use KonradMichalik\Typo3McpServerContentPlanner\Service\TodoChecklist;
use PHPUnit\Framework\TestCase;

/**
 * TodoChecklistTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class TodoChecklistTest extends TestCase
{
    private const HTML = 'Intro<ul><li><label><input type="checkbox" disabled="disabled"><span>First</span></label></li>'
        .'<li><label><input type="checkbox" disabled="disabled" checked="checked"><span>Second</span></label></li></ul>';

    public function testParseReturnsIndexTextAndResolvedState(): void
    {
        self::assertSame(
            [
                ['index' => 0, 'text' => 'First', 'resolved' => false],
                ['index' => 1, 'text' => 'Second', 'resolved' => true],
            ],
            TodoChecklist::parse(self::HTML),
        );
    }

    public function testParseReturnsEmptyListWithoutCheckboxes(): void
    {
        self::assertSame([], TodoChecklist::parse('<p>No todos</p>'));
        self::assertSame([], TodoChecklist::parse(''));
    }

    public function testWithResolvedStateChecksOnlyTheGivenItemAndKeepsTheRestUntouched(): void
    {
        $result = TodoChecklist::withResolvedState(self::HTML, 0, true);

        self::assertSame(
            str_replace('<input type="checkbox" disabled="disabled"><span>First', '<input type="checkbox" disabled="disabled" checked="checked"><span>First', self::HTML),
            $result,
        );
    }

    public function testWithResolvedStateUnchecksTheGivenItem(): void
    {
        $todos = TodoChecklist::parse(TodoChecklist::withResolvedState(self::HTML, 1, false));

        self::assertFalse($todos[1]['resolved']);
        self::assertFalse($todos[0]['resolved']);
    }

    public function testWithResolvedStateIsIdempotent(): void
    {
        self::assertSame(self::HTML, TodoChecklist::withResolvedState(self::HTML, 1, true));
    }

    public function testWithResolvedStateHandlesBareCheckedAttributeAndSelfClosingTag(): void
    {
        $html = '<ul><li><input type="checkbox" checked disabled/>A</li></ul>';

        $unchecked = TodoChecklist::withResolvedState($html, 0, false);
        self::assertFalse(TodoChecklist::parse($unchecked)[0]['resolved']);
        self::assertStringNotContainsString('checked', $unchecked);

        self::assertTrue(TodoChecklist::parse(TodoChecklist::withResolvedState($unchecked, 0, true))[0]['resolved']);
    }

    public function testParseKeepsUmlautsAndTreatsTypeCaseInsensitively(): void
    {
        $html = '<ul><li><input type="CHECKBOX">Bilder prüfen</li></ul>';

        self::assertSame([['index' => 0, 'text' => 'Bilder prüfen', 'resolved' => false]], TodoChecklist::parse($html));
    }

    public function testIndexIgnoresInputsWhoseTypeIsOnlyPartOfAnotherAttribute(): void
    {
        $html = '<ul><li><input data-type="checkbox" type="text">X</li><li><input type="checkbox">Y</li></ul>';

        self::assertSame('Y', TodoChecklist::parse($html)[0]['text']);
        self::assertTrue(TodoChecklist::parse(TodoChecklist::withResolvedState($html, 0, true))[0]['resolved']);
    }

    public function testUncheckingLeavesOtherAttributeValuesContainingTheWordCheckedUntouched(): void
    {
        $html = '<ul><li><input type="checkbox" title="is checked later" checked="checked">A</li></ul>';

        $result = TodoChecklist::withResolvedState($html, 0, false);

        self::assertStringContainsString('title="is checked later"', $result);
        self::assertFalse(TodoChecklist::parse($result)[0]['resolved']);
    }
}
