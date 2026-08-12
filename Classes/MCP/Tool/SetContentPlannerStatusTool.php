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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, RecordRepository};
use Xima\XimaTypo3ContentPlanner\Utility\{ExtensionUtility, PlannerUtility};
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function is_array;

/**
 * SetContentPlannerStatusTool.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class SetContentPlannerStatusTool extends AbstractPlannerTool
{
    /**
     * @return array<string, mixed>
     */
    public function getSchema(): array
    {
        $statusTitles = array_map(
            static fn (Status $status): string => $status->getTitle(),
            PlannerUtility::getListOfStatus(),
        );

        return [
            'description' => 'Set the Content Planner status (and optionally the assignee) of a TYPO3 record. Writes immediately to live - not staged in a workspace.',
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
                    'status' => [
                        'type' => 'string',
                        'enum' => $statusTitles,
                        'description' => 'Title of the status to assign.',
                    ],
                    'assigneeBackendUsername' => [
                        'type' => 'string',
                        'description' => 'Optional: backend username to assign the record to. Defaults to the currently authenticated backend user.',
                    ],
                ],
                'required' => ['table', 'uid', 'status'],
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
            throw new InvalidArgumentException('The current backend user is not allowed to change the status of table "'.$table.'".', 1755000020);
        }

        $record = GeneralUtility::makeInstance(RecordRepository::class)->findByUid($table, $uid);
        if (!is_array($record)) {
            throw new InvalidArgumentException('Record "'.$uid.'" in table "'.$table.'" not found.', 1755000021);
        }

        if (!PermissionUtility::checkAccessForRecord($table, $record)) {
            throw new InvalidArgumentException('The current backend user does not have access to this record.', 1755000022);
        }

        $status = PlannerUtility::getStatus((string) $params['status']);
        if (null === $status) {
            $availableTitles = array_map(
                static fn (Status $s): string => $s->getTitle(),
                PlannerUtility::getListOfStatus(),
            );
            throw new InvalidArgumentException('Status "'.$params['status'].'" is not a valid Content Planner status. Available statuses: '.implode(', ', $availableTitles).'.', 1755000023);
        }

        if (!PermissionUtility::canChangeStatus($status->getUid())) {
            throw new InvalidArgumentException('The current backend user is not allowed to set status "'.$status->getTitle().'".', 1755000024);
        }

        $assignee = $this->currentBackendUserUid();
        if (isset($params['assigneeBackendUsername'])) {
            $assigneeUsername = (string) $params['assigneeBackendUsername'];
            $assigneeRecord = GeneralUtility::makeInstance(BackendUserRepository::class)->findByUsername($assigneeUsername);
            if (!is_array($assigneeRecord)) {
                throw new InvalidArgumentException('Backend user "'.$assigneeUsername.'" does not exist.', 1755000025);
            }

            $assignee = $assigneeUsername;
        }

        $this->withLiveWorkspace(
            static fn () => PlannerUtility::updateStatusForRecord($table, $uid, $status, $assignee),
        );

        return $this->createSuccessResult('Status of '.$table.':'.$uid.' set to "'.$status->getTitle().'".');
    }
}
