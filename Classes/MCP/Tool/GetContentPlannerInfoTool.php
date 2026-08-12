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
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\CommentItem;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;
use Xima\XimaTypo3ContentPlanner\Utility\Data\ContentUtility;
use Xima\XimaTypo3ContentPlanner\Utility\{ExtensionUtility, PlannerUtility};
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function is_array;

/**
 * GetContentPlannerInfoTool.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class GetContentPlannerInfoTool extends AbstractPlannerTool
{
    public function getSchema(): array
    {
        return [
            'description' => 'Get the Content Planner status, assignee and comments (including threads) for a single TYPO3 record.',
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
                        'description' => 'UID of the record (the live UID, as returned by typo3-mcp-server\'s GetPage/ReadTable tools).',
                    ],
                ],
                'required' => ['table', 'uid'],
            ],
            'annotations' => [
                'readOnlyHint' => true,
                'idempotentHint' => true,
            ],
        ];
    }

    protected function doExecute(array $params): CallToolResult
    {
        $this->assertContentPlannerVisible();

        $table = (string) $params['table'];
        $uid = (int) $params['uid'];
        $this->assertRegisteredTable($table);

        $record = GeneralUtility::makeInstance(RecordRepository::class)->findByUid($table, $uid);
        if (!is_array($record)) {
            throw new InvalidArgumentException('Record "'.$uid.'" in table "'.$table.'" not found.', 1755000010);
        }

        if (!PermissionUtility::checkAccessForRecord($table, $record)) {
            throw new InvalidArgumentException('The current backend user does not have access to this record.', 1755000011);
        }

        $status = PlannerUtility::getStatusOfRecord($table, $uid);
        $assigneeUid = (int) ($record[Configuration::FIELD_ASSIGNEE] ?? 0);

        $comments = array_map(
            static fn (CommentItem $comment): array => [
                'uid' => (int) $comment->data['uid'],
                'content' => $comment->data['content'],
                'author' => ContentUtility::getBackendUsernameById((int) $comment->data['author']),
                'resolved' => $comment->isResolved(),
                'replies' => array_map(
                    static fn (CommentItem $reply): array => [
                        'uid' => (int) $reply->data['uid'],
                        'content' => $reply->data['content'],
                        'author' => ContentUtility::getBackendUsernameById((int) $reply->data['author']),
                        'resolved' => $reply->isResolved(),
                    ],
                    $comment->getReplies(),
                ),
            ],
            PlannerUtility::getCommentsOfRecord($table, $uid),
        );

        return $this->createJsonResult([
            'status' => null !== $status ? ['uid' => $status->getUid(), 'title' => $status->getTitle(), 'color' => $status->getColor()] : null,
            'assignee' => $assigneeUid > 0 ? ContentUtility::getBackendUsernameById($assigneeUid) : null,
            'comments' => $comments,
        ]);
    }
}
