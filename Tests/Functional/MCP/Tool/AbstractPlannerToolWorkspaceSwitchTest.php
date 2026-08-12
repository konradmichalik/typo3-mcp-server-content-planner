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
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

/**
 * AbstractPlannerToolWorkspaceSwitchTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class AbstractPlannerToolWorkspaceSwitchTest extends AbstractFunctionalTestCase
{
    public function testWithLiveWorkspaceReturnsAnErrorResultWhenSwitchingToLiveFails(): void
    {
        $this->loginBackendUser();

        // Simulate a non-admin backend user that lacks access to the live workspace:
        // BackendUserAuthentication::setTemporaryWorkspace() returns false for the
        // switch-to-live(0) call but true for the restore call, exactly as it would
        // for a real user who is validly in a non-live workspace but not allowed
        // into live. The previous implementation discarded this return value and
        // proceeded as if the switch had succeeded.
        $beUser = $this->createMock(BackendUserAuthentication::class);
        $beUser->method('setTemporaryWorkspace')
            ->willReturnCallback(static fn (int $workspaceId): bool => 0 !== $workspaceId);
        $beUser->workspace = 3;
        $GLOBALS['BE_USER'] = $beUser;

        $tool = new class extends AbstractPlannerTool {
            public function getSchema(): array
            {
                return [];
            }

            protected function doExecute(array $params): CallToolResult
            {
                return $this->withLiveWorkspace(
                    fn (): CallToolResult => $this->createSuccessResult('should never be reached'),
                );
            }
        };

        $result = $tool->execute([]);

        self::assertTrue($result->isError, 'A failed workspace switch must be reported as an error, not silently ignored.');
    }
}
