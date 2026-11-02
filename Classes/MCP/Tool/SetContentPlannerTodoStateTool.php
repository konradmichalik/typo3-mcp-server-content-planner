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
use KonradMichalik\Typo3McpServerContentPlanner\Service\TodoChecklist;
use Mcp\Types\CallToolResult;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{CommentRepository, RecordRepository};
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function count;
use function is_array;

/**
 * SetContentPlannerTodoStateTool.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class SetContentPlannerTodoStateTool extends AbstractPlannerTool
{
    public function __construct(private readonly ConnectionPool $connectionPool) {}

    /**
     * @return array<string, mixed>
     */
    public function getSchema(): array
    {
        return [
            'description' => 'Check or uncheck a single to-do of a Content Planner comment, without touching the rest of the comment. Writes immediately to live - not staged in a workspace.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'commentUid' => [
                        'type' => 'integer',
                        'description' => 'UID of the comment (or reply) that contains the to-do (from GetContentPlannerInfo).',
                    ],
                    'todoIndex' => [
                        'type' => 'integer',
                        'minimum' => 0,
                        'description' => 'Zero-based index of the to-do inside the comment (the "index" from GetContentPlannerInfo).',
                    ],
                    'resolved' => [
                        'type' => 'boolean',
                        'description' => 'true to check the to-do, false to uncheck it.',
                    ],
                ],
                'required' => ['commentUid', 'todoIndex', 'resolved'],
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
        $todoIndex = (int) $params['todoIndex'];
        $resolved = (bool) $params['resolved'];

        $comment = GeneralUtility::makeInstance(CommentRepository::class)->findByUid($commentUid);
        if (!is_array($comment)) {
            throw new InvalidArgumentException('Comment "'.$commentUid.'" not found.', 1755000050);
        }

        if (!PermissionUtility::canResolveComment() && !PermissionUtility::canEditComment($comment)) {
            throw new InvalidArgumentException('The current backend user is not allowed to check or uncheck to-dos of this comment.', 1755000051);
        }

        $table = (string) $comment['foreign_table'];
        $this->assertRegisteredTable($table);

        $record = GeneralUtility::makeInstance(RecordRepository::class)->findByUid($table, (int) $comment['foreign_uid']);
        if (!is_array($record) || !PermissionUtility::checkAccessForRecord($table, $record)) {
            throw new InvalidArgumentException('The current backend user does not have access to this record.', 1755000052);
        }

        $content = (string) $comment['content'];
        $todos = TodoChecklist::parse($content);
        if (!isset($todos[$todoIndex])) {
            throw new InvalidArgumentException('Comment "'.$commentUid.'" has no to-do with index '.$todoIndex.' ('.count($todos).' to-dos available).', 1755000053);
        }

        $newContent = TodoChecklist::withResolvedState($content, $todoIndex, $resolved);
        $resolvedCount = count(array_filter(TodoChecklist::parse($newContent), static fn (array $todo): bool => $todo['resolved']));

        // Written directly instead of through the DataHandler: its hook marks every content
        // change as "edited" and drops it unless the user may edit the comment, neither of which
        // fits ticking a box.
        $this->withLiveWorkspace(
            fn () => $this->connectionPool
                ->getConnectionForTable(Configuration::TABLE_COMMENT)
                ->update(Configuration::TABLE_COMMENT, ['content' => $newContent, 'todo_resolved' => $resolvedCount], ['uid' => $commentUid]),
        );

        return $this->createSuccessResult('To-do '.$todoIndex.' of comment '.$commentUid.' '.($resolved ? 'checked' : 'unchecked').'.');
    }
}
