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

use InvalidArgumentException;
use Mcp\Types\CallToolResult;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, RecordRepository};
use Xima\XimaTypo3ContentPlanner\Service\Header\InfoGenerator;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function is_array;

/**
 * SetContentPlannerAssigneeTool.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class SetContentPlannerAssigneeTool extends AbstractPlannerTool
{
    public function __construct(private readonly ConnectionPool $connectionPool) {}

    /**
     * @return array<string, mixed>
     */
    public function getSchema(): array
    {
        return [
            'description' => 'Assign a TYPO3 record to a backend user in the Content Planner, or remove the assignment, without changing its status. Writes immediately to live - not staged in a workspace.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'table' => [
                        'type' => 'string',
                        'enum' => ExtensionUtility::getRecordTables(),
                        'description' => 'Database table of the record, e.g. "pages".',
                    ],
                    'uid' => [
                        'type' => 'integer',
                        'description' => 'UID of the record.',
                    ],
                    'assigneeBackendUsername' => [
                        'type' => 'string',
                        'description' => 'Backend username to assign the record to (see ListContentPlannerAssignees). Omit or pass an empty string to remove the assignment.',
                    ],
                ],
                'required' => ['table', 'uid'],
            ],
        ];
    }

    /**
     * @param array<array-key, mixed> $params
     */
    protected function doExecute(array $params): CallToolResult
    {
        $this->assertContentPlannerVisible();

        $table = (string) $params['table'];
        $uid = (int) $params['uid'];
        $this->assertRegisteredTable($table);

        if (!PermissionUtility::isTableAllowedForUser($table)) {
            throw new InvalidArgumentException('The current backend user is not allowed to change the assignee of table "'.$table.'".', 1755000060);
        }

        $record = GeneralUtility::makeInstance(RecordRepository::class)->findByUid($table, $uid);
        if (!is_array($record)) {
            throw new InvalidArgumentException('Record "'.$uid.'" in table "'.$table.'" not found.', 1755000061);
        }

        if (!PermissionUtility::checkAccessForRecord($table, $record)) {
            throw new InvalidArgumentException('The current backend user does not have access to this record.', 1755000062);
        }

        $assigneeUid = $this->resolveAssigneeUid((string) ($params['assigneeBackendUsername'] ?? ''), $record);

        $this->withLiveWorkspace(
            fn () => $this->connectionPool
                ->getConnectionForTable($table)
                ->update($table, [Configuration::FIELD_ASSIGNEE => $assigneeUid], ['uid' => $uid]),
        );

        return $this->createSuccessResult(0 === $assigneeUid
            ? 'Assignee of '.$table.':'.$uid.' removed.'
            : 'Record '.$table.':'.$uid.' assigned to "'.(string) $params['assigneeBackendUsername'].'".');
    }

    /**
     * @param array<string, mixed> $record
     */
    private function resolveAssigneeUid(string $username, array $record): int
    {
        if ('' === $username) {
            if (!InfoGenerator::canUnassignRecord($record)) {
                throw new InvalidArgumentException('The current backend user is not allowed to change assignments.', 1755000063);
            }

            return 0;
        }

        $assignee = GeneralUtility::makeInstance(BackendUserRepository::class)->findByUsername($username);
        if (!is_array($assignee)) {
            throw new InvalidArgumentException('Backend user "'.$username.'" does not exist.', 1755000064);
        }

        $assigneeUid = (int) $assignee['uid'];
        $allowed = $assigneeUid === $this->currentBackendUserUid()
            ? PermissionUtility::canAssignSelf()
            : PermissionUtility::canAssignOthers();
        if (!$allowed) {
            throw new InvalidArgumentException('The current backend user is not allowed to assign this record to "'.$username.'".', 1755000065);
        }

        return $assigneeUid;
    }
}
