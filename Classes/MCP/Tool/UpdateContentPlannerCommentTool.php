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

use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use Mcp\Types\CallToolResult;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{CommentRepository, RecordRepository};
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function is_array;

/**
 * UpdateContentPlannerCommentTool.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class UpdateContentPlannerCommentTool extends AbstractPlannerTool
{
    /**
     * @return array<string, mixed>
     */
    public function getSchema(): array
    {
        return [
            'description' => 'Update an existing Content Planner comment: correct its text or its to-do checklist. The "edited" flag is set and to-do counts are recalculated automatically. Writes immediately to live - not staged in a workspace.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'commentUid' => [
                        'type' => 'integer',
                        'description' => 'UID of the comment to update (from GetContentPlannerInfo).',
                    ],
                    'comment' => [
                        'type' => 'string',
                        'description' => 'The new comment text, replacing the previous one.',
                    ],
                    'todos' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'Optional: to-do items rendered as a checklist appended to the comment. An item whose text matches an existing to-do keeps that to-do\'s resolved state; new items start unresolved. Omit entirely to drop any existing to-do checklist.',
                    ],
                ],
                'required' => ['commentUid', 'comment'],
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
        $comment = GeneralUtility::makeInstance(CommentRepository::class)->findByUid($commentUid);
        if (!is_array($comment)) {
            throw new InvalidArgumentException('Comment "'.$commentUid.'" not found.', 1755000040);
        }

        if (!PermissionUtility::canEditComment($comment)) {
            throw new InvalidArgumentException('The current backend user is not allowed to edit this comment.', 1755000041);
        }

        $table = (string) $comment['foreign_table'];
        $uid = (int) $comment['foreign_uid'];
        $this->assertRegisteredTable($table);

        $record = GeneralUtility::makeInstance(RecordRepository::class)->findByUid($table, $uid);
        if (!is_array($record) || !PermissionUtility::checkAccessForRecord($table, $record)) {
            throw new InvalidArgumentException('The current backend user does not have access to this record.', 1755000042);
        }

        $content = (string) $params['comment'];
        if (isset($params['todos']) && [] !== $params['todos']) {
            $content .= $this->generateTodoMarkup($params['todos'], $this->extractResolvedTodoTexts((string) $comment['content']));
        }

        $this->withLiveWorkspace(fn () => $this->updateCommentContent($commentUid, $content));

        return $this->createSuccessResult('Comment '.$commentUid.' updated.');
    }

    private function updateCommentContent(int $commentUid, string $content): void
    {
        $data = [
            Configuration::TABLE_COMMENT => [
                $commentUid => [
                    'content' => $content,
                ],
            ],
        ];

        /** @var DataHandler $dataHandler */
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, []);
        $dataHandler->process_datamap();
    }

    /**
     * PlannerUtility::generateTodoForComment() always renders unchecked items, so it cannot express
     * "this to-do was already resolved" - that state has to be reconstructed by text-matching against
     * the previous content and re-applied here.
     *
     * @return array<string, bool>
     */
    private function extractResolvedTodoTexts(string $content): array
    {
        if ('' === $content) {
            return [];
        }

        $dom = new DOMDocument();
        $previousLibXmlUseErrors = libxml_use_internal_errors(true);
        $success = $dom->loadHTML($content);
        libxml_use_internal_errors($previousLibXmlUseErrors);
        if (!$success) {
            return [];
        }

        $resolved = [];
        foreach ($dom->getElementsByTagName('li') as $item) {
            $checkbox = $item->getElementsByTagName('input')->item(0);
            if (!$checkbox instanceof DOMElement || !$checkbox->hasAttribute('checked')) {
                continue;
            }
            $resolved[trim($item->textContent)] = true;
        }

        return $resolved;
    }

    /**
     * @param string[]            $todos
     * @param array<string, bool> $resolvedTexts
     */
    private function generateTodoMarkup(array $todos, array $resolvedTexts): string
    {
        $html = '<ul class="todo-list">';
        foreach ($todos as $todo) {
            $checked = isset($resolvedTexts[$todo]) ? ' checked="checked"' : '';
            $html .= '<li><label class="todo-list__label">'
                .'<input type="checkbox" disabled="disabled"'.$checked.'>'
                .'<span class="todo-list__label__description">'.htmlspecialchars($todo, \ENT_QUOTES | \ENT_HTML5).'</span>'
                .'</label></li>';
        }
        $html .= '</ul>';

        return $html;
    }
}
