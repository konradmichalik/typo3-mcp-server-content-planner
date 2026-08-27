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

namespace KonradMichalik\Typo3McpServerContentPlanner\MCP\Tool;

use Hn\McpServer\MCP\Tool\AbstractTool;
use InvalidArgumentException;
use Mcp\Types\{CallToolResult, TextContent};
use RuntimeException;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Context\{Context, WorkspaceAspect};
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

/**
 * AbstractPlannerTool.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
abstract class AbstractPlannerTool extends AbstractTool
{
    final protected function currentBackendUser(): BackendUserAuthentication
    {
        if (!isset($GLOBALS['BE_USER'])) {
            throw new InvalidArgumentException('No authenticated backend user in this MCP session.', 1755000001);
        }

        return $GLOBALS['BE_USER'];
    }

    final protected function currentBackendUserUid(): int
    {
        return (int) ($this->currentBackendUser()->user['uid'] ?? 0);
    }

    final protected function assertContentPlannerVisible(): void
    {
        if (!PermissionUtility::checkContentStatusVisibility()) {
            throw new InvalidArgumentException('The current backend user is not allowed to see content planner data.', 1755000002);
        }
    }

    final protected function assertRegisteredTable(string $table): void
    {
        if (!ExtensionUtility::isRegisteredRecordTable($table)) {
            throw new InvalidArgumentException('Table "'.$table.'" is not a content planner record table.', 1755000003);
        }
    }

    /**
     * Runs $callback with the backend user forced into the live workspace (0), then
     * restores whatever workspace it was in before. Content planner status/assignee/
     * comments are editorial workflow metadata that must be visible immediately, not
     * staged behind a workspace publish (see spec §3).
     *
     * Uses setTemporaryWorkspace() rather than setWorkspace(): the latter persists the
     * workspace_id to be_users and writes a system log entry on every call, which would
     * silently change the real backend user's workspace selection. setTemporaryWorkspace()
     * only mutates in-memory state - this mirrors what Hn\McpServer\Service\
     * WorkspaceContextService::setWorkspaceContext() already does for the same reason.
     */
    final protected function withLiveWorkspace(callable $callback): mixed
    {
        $beUser = $this->currentBackendUser();
        $previousWorkspace = $beUser->workspace;

        try {
            $this->switchWorkspace($beUser, 0);

            return $callback();
        } finally {
            $this->switchWorkspace($beUser, $previousWorkspace);
        }
    }

    final protected function createSuccessResult(string $text): CallToolResult
    {
        return new CallToolResult([new TextContent($text)]);
    }

    /**
     * @param array<string, mixed> $data
     */
    final protected function createJsonResult(array $data): CallToolResult
    {
        $encoded = json_encode(
            $data,
            \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_INVALID_UTF8_SUBSTITUTE,
        );
        if (false === $encoded) {
            $encoded = '{}';
        }

        return $this->createSuccessResult($encoded);
    }

    private function switchWorkspace(BackendUserAuthentication $beUser, int $workspaceId): void
    {
        if (!$beUser->setTemporaryWorkspace($workspaceId)) {
            throw new RuntimeException('The current backend user could not be switched to workspace "'.$workspaceId.'".', 1755000004);
        }

        GeneralUtility::makeInstance(Context::class)->setAspect(
            'workspace',
            GeneralUtility::makeInstance(WorkspaceAspect::class, $workspaceId),
        );
    }
}
