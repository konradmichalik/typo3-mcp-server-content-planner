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

use Mcp\Types\CallToolResult;
use stdClass;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\BackendUserRepository;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

/**
 * ListContentPlannerAssigneesTool.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class ListContentPlannerAssigneesTool extends AbstractPlannerTool
{
    /**
     * @return array<string, mixed>
     */
    public function getSchema(): array
    {
        return [
            'description' => 'List the backend users a record can be assigned to in the Content Planner (username and real name). Use the username with SetContentPlannerAssignee or SetContentPlannerStatus.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => new stdClass(),
            ],
            'annotations' => [
                'readOnlyHint' => true,
                'idempotentHint' => true,
            ],
        ];
    }

    /**
     * @param array<array-key, mixed> $params
     */
    protected function doExecute(array $params): CallToolResult
    {
        $this->assertContentPlannerVisible();

        $users = GeneralUtility::makeInstance(BackendUserRepository::class)->findAllWithPermission();
        $currentUid = $this->currentBackendUserUid();
        $canAssignOthers = PermissionUtility::canAssignOthers();
        $canAssignSelf = PermissionUtility::canAssignSelf();

        $assignees = [];
        foreach ($users as $user) {
            $uid = (int) $user['uid'];
            $isCurrentUser = $uid === $currentUid;
            if (!$canAssignOthers && !($isCurrentUser && $canAssignSelf)) {
                continue;
            }

            $assignees[] = [
                'username' => (string) $user['username'],
                'realName' => (string) ($user['realName'] ?? ''),
                'isCurrentUser' => $isCurrentUser,
            ];
        }

        return $this->createJsonResult(['assignees' => $assignees]);
    }
}
