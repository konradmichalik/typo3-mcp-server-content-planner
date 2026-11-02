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
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{CommentRepository, RecordRepository};
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function is_array;

/**
 * SetContentPlannerCommentResolvedTool.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class SetContentPlannerCommentResolvedTool extends AbstractPlannerTool
{
    /**
     * @return array<string, mixed>
     */
    public function getSchema(): array
    {
        return [
            'description' => 'Mark a Content Planner comment (or reply) as resolved, or reopen it. Writes immediately to live - not staged in a workspace.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'commentUid' => [
                        'type' => 'integer',
                        'description' => 'UID of the comment to resolve or reopen (from GetContentPlannerInfo).',
                    ],
                    'resolved' => [
                        'type' => 'boolean',
                        'description' => 'true to mark the comment as resolved, false to reopen it.',
                    ],
                ],
                'required' => ['commentUid', 'resolved'],
            ],
        ];
    }

    /**
     * @param array<array-key, mixed> $params
     */
    protected function doExecute(array $params): CallToolResult
    {
        $this->assertContentPlannerVisible();

        $commentUid = (int) $params['commentUid'];
        $resolved = (bool) $params['resolved'];

        $comment = GeneralUtility::makeInstance(CommentRepository::class)->findByUid($commentUid);
        if (!is_array($comment)) {
            throw new InvalidArgumentException('Comment "'.$commentUid.'" not found.', 1755000070);
        }

        // The DataHandler hook drops the resolve fields without an error when this is missing,
        // so it has to be checked up front to report the refusal.
        if (!PermissionUtility::canResolveComment()) {
            throw new InvalidArgumentException('The current backend user is not allowed to resolve or reopen comments.', 1755000071);
        }

        $table = (string) $comment['foreign_table'];
        $this->assertRegisteredTable($table);

        $record = GeneralUtility::makeInstance(RecordRepository::class)->findByUid($table, (int) $comment['foreign_uid']);
        if (!is_array($record) || !PermissionUtility::checkAccessForRecord($table, $record)) {
            throw new InvalidArgumentException('The current backend user does not have access to this record.', 1755000072);
        }

        // Writing again would overwrite the original resolver and time and fire the resolve event twice.
        if (((int) $comment['resolved_date'] > 0) === $resolved) {
            return $this->createSuccessResult('Comment '.$commentUid.' is already '.($resolved ? 'resolved' : 'open').'.');
        }

        $this->withLiveWorkspace(fn () => $this->writeResolvedState($commentUid, $resolved));

        return $this->createSuccessResult('Comment '.$commentUid.' '.($resolved ? 'resolved' : 'reopened').'.');
    }

    private function writeResolvedState(int $commentUid, bool $resolved): void
    {
        // Through the DataHandler on purpose: the extension's hook sets resolved_user and the
        // timestamp server-side, refreshes the record's open-comment counter and dispatches
        // CommentResolvedEvent. A direct update would skip all three.
        /** @var DataHandler $dataHandler */
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([Configuration::TABLE_COMMENT => [$commentUid => ['resolved_date' => $resolved ? time() : 0]]], []);
        $dataHandler->process_datamap();

        // The DataHandler drops a refused write without throwing, e.g. when the user has no
        // tables_modify on the comment table, which the planner permission check cannot see.
        $stored = GeneralUtility::makeInstance(CommentRepository::class)->findByUid($commentUid);
        if (!is_array($stored) || ((int) $stored['resolved_date'] > 0) !== $resolved) {
            throw new InvalidArgumentException('Comment "'.$commentUid.'" could not be '.($resolved ? 'resolved' : 'reopened').('' !== implode('', $dataHandler->errorLog) ? ': '.implode(' ', $dataHandler->errorLog) : '.'), 1755000073);
        }
    }
}
