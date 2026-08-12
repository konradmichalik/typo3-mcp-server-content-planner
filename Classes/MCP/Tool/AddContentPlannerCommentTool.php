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
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;
use Xima\XimaTypo3ContentPlanner\Utility\{ExtensionUtility, PlannerUtility};
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function is_array;

/**
 * AddContentPlannerCommentTool.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class AddContentPlannerCommentTool extends AbstractPlannerTool
{
    public function getSchema(): array
    {
        return [
            'description' => 'Leave a Content Planner comment on a TYPO3 record, optionally with a to-do checklist or as a reply within an existing thread. Writes immediately to live - not staged in a workspace.',
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
                    'comment' => [
                        'type' => 'string',
                        'description' => 'The comment text.',
                    ],
                    'todos' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'Optional: to-do items rendered as an unchecked checklist appended to the comment.',
                    ],
                    'parentCommentUid' => [
                        'type' => 'integer',
                        'description' => 'Optional: UID of an existing comment (from GetContentPlannerInfo) to reply to, creating a threaded reply instead of a new top-level comment. Must belong to the same record.',
                    ],
                ],
                'required' => ['table', 'uid', 'comment'],
            ],
        ];
    }

    protected function doExecute(array $params): CallToolResult
    {
        $this->assertContentPlannerVisible();

        $table = (string) $params['table'];
        $uid = (int) $params['uid'];
        $this->assertRegisteredTable($table);

        if (!PermissionUtility::canCreateComment()) {
            throw new InvalidArgumentException('The current backend user is not allowed to create comments.', 1755000030);
        }

        $record = GeneralUtility::makeInstance(RecordRepository::class)->findByUid($table, $uid);
        if (!is_array($record)) {
            throw new InvalidArgumentException('Record "'.$uid.'" in table "'.$table.'" not found.', 1755000031);
        }

        if (!PermissionUtility::checkAccessForRecord($table, $record)) {
            throw new InvalidArgumentException('The current backend user does not have access to this record.', 1755000032);
        }

        $content = (string) $params['comment'];
        if (isset($params['todos']) && [] !== $params['todos']) {
            $content .= PlannerUtility::generateTodoForComment($params['todos']);
        }

        $parentCommentUid = (int) ($params['parentCommentUid'] ?? 0);

        $this->withLiveWorkspace(
            fn () => PlannerUtility::addCommentsToRecord($table, $uid, $content, $this->currentBackendUserUid(), $parentCommentUid),
        );

        return $this->createSuccessResult('Comment added to '.$table.':'.$uid.'.');
    }
}
