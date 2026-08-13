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

use KonradMichalik\Typo3McpServerContentPlanner\MCP\Tool\AbstractPlannerTool;
use KonradMichalik\Typo3McpServerContentPlanner\Tests\Functional\AbstractFunctionalTestCase;
use Mcp\Types\CallToolResult;

/**
 * AbstractPlannerToolTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class AbstractPlannerToolTest extends AbstractFunctionalTestCase
{
    public function testCurrentBackendUserThrowsWithoutAnAuthenticatedUser(): void
    {
        unset($GLOBALS['BE_USER']);

        $tool = new class extends AbstractPlannerTool {
            public function getSchema(): array
            {
                return [];
            }

            protected function doExecute(array $params): CallToolResult
            {
                $this->currentBackendUserUid();

                return $this->createSuccessResult('should never be reached');
            }
        };

        $result = $tool->execute([]);

        self::assertTrue($result->isError);
        self::assertStringContainsString('No authenticated backend user', $result->content[0]->text);
    }

    public function testAssertRegisteredTableThrowsForAnUnregisteredTable(): void
    {
        $this->loginBackendUser();

        $tool = new class extends AbstractPlannerTool {
            public function getSchema(): array
            {
                return [];
            }

            protected function doExecute(array $params): CallToolResult
            {
                $this->assertRegisteredTable('be_users');

                return $this->createSuccessResult('should never be reached');
            }
        };

        $result = $tool->execute([]);

        self::assertTrue($result->isError);
        self::assertStringContainsString('is not a content planner record table', $result->content[0]->text);
    }

    public function testAssertContentPlannerVisibleThrowsWhenHiddenForTheCurrentUser(): void
    {
        $this->loginBackendUser();
        $GLOBALS['BE_USER']->user['tx_ximatypo3contentplanner_hide'] = 1;

        $tool = new class extends AbstractPlannerTool {
            public function getSchema(): array
            {
                return [];
            }

            protected function doExecute(array $params): CallToolResult
            {
                $this->assertContentPlannerVisible();

                return $this->createSuccessResult('should never be reached');
            }
        };

        $result = $tool->execute([]);

        self::assertTrue($result->isError);
        self::assertStringContainsString('not allowed to see content planner data', $result->content[0]->text);
    }

    public function testCreateJsonResultFallsBackToAnEmptyObjectWhenEncodingFails(): void
    {
        $this->loginBackendUser();

        // NAN has no JSON representation - json_encode() returns false for it
        // (JSON_ERROR_INF_OR_NAN), which is otherwise very hard to trigger through
        // the tools' real, string-only data (JSON_INVALID_UTF8_SUBSTITUTE already
        // absorbs malformed UTF-8 before this fallback would ever be needed).
        $tool = new class extends AbstractPlannerTool {
            public function getSchema(): array
            {
                return [];
            }

            protected function doExecute(array $params): CallToolResult
            {
                return $this->createJsonResult(['value' => \NAN]);
            }
        };

        $result = $tool->execute([]);

        self::assertFalse($result->isError);
        self::assertSame('{}', $result->content[0]->text);
    }
}
