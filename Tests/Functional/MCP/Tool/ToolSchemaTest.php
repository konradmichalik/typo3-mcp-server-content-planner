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

namespace KonradMichalik\Typo3McpServerContentPlanner\Tests\Functional\MCP\Tool;

use KonradMichalik\Typo3McpServerContentPlanner\MCP\Tool\{
    AbstractPlannerTool,
    AddContentPlannerCommentTool,
    GetContentPlannerInfoTool,
    ListContentPlannerStatusesTool,
    SetContentPlannerStatusTool
};
use KonradMichalik\Typo3McpServerContentPlanner\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

/**
 * ToolSchemaTest.
 *
 * hn/typo3-mcp-server serializes getSchema()'s array directly to JSON for the
 * "tools/list" response. An empty PHP array ([]) and an empty JSON object ({})
 * are indistinguishable in PHP source but not on the wire: MCP clients validate
 * "inputSchema.properties" as a JSON object, so a tool with no parameters must
 * return an object there, not an array - PHPUnit's assertions can't tell the two
 * apart once json_decode()'d back with $assoc=true, so this asserts against the
 * raw encoded string instead, exactly like a real MCP client would receive it.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ToolSchemaTest extends AbstractFunctionalTestCase
{
    /**
     * @return iterable<string, array{0: class-string<AbstractPlannerTool>}>
     */
    public static function toolClassProvider(): iterable
    {
        yield ListContentPlannerStatusesTool::class => [ListContentPlannerStatusesTool::class];
        yield GetContentPlannerInfoTool::class => [GetContentPlannerInfoTool::class];
        yield SetContentPlannerStatusTool::class => [SetContentPlannerStatusTool::class];
        yield AddContentPlannerCommentTool::class => [AddContentPlannerCommentTool::class];
    }

    /**
     * @param class-string<AbstractPlannerTool> $toolClass
     */
    #[DataProvider('toolClassProvider')]
    public function testGetSchemaProducesAnObjectForEmptyProperties(string $toolClass): void
    {
        $schema = (new $toolClass())->getSchema();
        $properties = $schema['inputSchema']['properties'] ?? null;

        if ([] !== $properties && !$properties instanceof stdClass) {
            // Non-empty properties (an associative array of parameter definitions)
            // always encode as a JSON object regardless of type - nothing to check.
            self::assertIsArray($properties);

            return;
        }

        $encoded = json_encode(['inputSchema' => $schema['inputSchema']]);
        self::assertIsString($encoded);
        self::assertStringNotContainsString(
            '"properties":[]',
            $encoded,
            'Empty inputSchema.properties must serialize as a JSON object ({}), not an array ([]) - MCP clients reject the latter.',
        );
    }
}
