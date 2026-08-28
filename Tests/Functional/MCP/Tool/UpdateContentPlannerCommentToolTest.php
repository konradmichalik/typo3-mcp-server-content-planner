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

use Hn\McpServer\Service\WorkspaceContextService;
use KonradMichalik\Typo3McpServerContentPlanner\MCP\Tool\{AddContentPlannerCommentTool, UpdateContentPlannerCommentTool};
use KonradMichalik\Typo3McpServerContentPlanner\Tests\Functional\AbstractFunctionalTestCase;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * UpdateContentPlannerCommentToolTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class UpdateContentPlannerCommentToolTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loginBackendUser();
        $this->importFixture('pages.csv');
        $this->importFixture('comments.csv');
    }

    public function testExecuteUpdatesTheCommentTextAndSetsTheEditedFlag(): void
    {
        $result = (new UpdateContentPlannerCommentTool())->execute([
            'commentUid' => 1,
            'comment' => 'Actually, the SEO title is fine now.',
        ]);

        self::assertFalse($result->isError, json_encode($result->jsonSerialize()));

        $row = $this->getConnectionPool()->getConnectionForTable('tx_ximatypo3contentplanner_comment')
            ->select(['content', 'edited'], 'tx_ximatypo3contentplanner_comment', ['uid' => 1])
            ->fetchAssociative();
        self::assertSame('Actually, the SEO title is fine now.', $row['content']);
        self::assertSame(1, (int) $row['edited']);
    }

    public function testExecuteReturnsErrorForUnknownComment(): void
    {
        $result = (new UpdateContentPlannerCommentTool())->execute([
            'commentUid' => 999,
            'comment' => 'Should be rejected.',
        ]);

        self::assertTrue($result->isError);
        self::assertStringContainsString('not found', $result->content[0]->text);
    }

    public function testExecuteReturnsErrorWhenTheCurrentUserCannotEditAForeignComment(): void
    {
        // Group 5: can edit own comments, but not foreign ones. Comment uid 1 is authored
        // by the admin user (uid 1), so this is a foreign comment for user 6.
        $this->loginRestrictedBackendUser(6);

        $result = (new UpdateContentPlannerCommentTool())->execute([
            'commentUid' => 1,
            'comment' => 'Should be rejected.',
        ]);

        self::assertTrue($result->isError);
        self::assertStringContainsString('not allowed to edit this comment', $result->content[0]->text);
    }

    public function testExecuteAllowsTheAuthorToEditTheirOwnComment(): void
    {
        // Comment uid 2 is authored by user 6, seeded by comments.csv. It targets the uid=2
        // subpage (pid=1), not the uid=1 root page (pid=0): TYPO3 core's
        // BackendUtility::readPageAccess() denies non-admins access to pid=0 unconditionally,
        // which would otherwise fail on checkAccessForRecord() instead of the check under test.
        $this->loginRestrictedBackendUser(6);

        $result = (new UpdateContentPlannerCommentTool())->execute([
            'commentUid' => 2,
            'comment' => 'Edited by its own author.',
        ]);

        self::assertFalse($result->isError, json_encode($result->jsonSerialize()));

        $row = $this->getConnectionPool()->getConnectionForTable('tx_ximatypo3contentplanner_comment')
            ->select(['content'], 'tx_ximatypo3contentplanner_comment', ['uid' => 2])
            ->fetchAssociative();
        self::assertSame('Edited by its own author.', $row['content']);
    }

    public function testExecuteRegeneratesTodoMarkupAndRecalculatesCounts(): void
    {
        $result = (new UpdateContentPlannerCommentTool())->execute([
            'commentUid' => 1,
            'comment' => 'Before publishing:',
            'todos' => ['Check links', 'Check images'],
        ]);

        self::assertFalse($result->isError, json_encode($result->jsonSerialize()));

        $row = $this->getConnectionPool()->getConnectionForTable('tx_ximatypo3contentplanner_comment')
            ->select(['content', 'todo_total', 'todo_resolved'], 'tx_ximatypo3contentplanner_comment', ['uid' => 1])
            ->fetchAssociative();
        self::assertStringContainsString('Before publishing:', $row['content']);
        self::assertStringContainsString('Check links', $row['content']);
        self::assertStringContainsString('todo-list', $row['content']);
        self::assertSame(2, (int) $row['todo_total']);
        self::assertSame(0, (int) $row['todo_resolved']);
    }

    public function testExecutePreservesTheResolvedStateOfUnchangedTodoItems(): void
    {
        // Create a comment with two to-dos, then resolve one exactly as the backend UI would:
        // by flipping its checkbox's "checked" attribute directly in the stored HTML.
        $created = (new AddContentPlannerCommentTool())->execute([
            'table' => 'pages',
            'uid' => 1,
            'comment' => 'Before publishing:',
            'todos' => ['Check links', 'Check images'],
        ]);
        self::assertFalse($created->isError, json_encode($created->jsonSerialize()));

        $connection = $this->getConnectionPool()->getConnectionForTable('tx_ximatypo3contentplanner_comment');
        $created = $connection->select(['uid', 'content'], 'tx_ximatypo3contentplanner_comment', ['foreign_uid' => 1], [], ['uid' => 'DESC'])
            ->fetchAssociative();
        $resolvedContent = str_replace(
            '<input type="checkbox" disabled="disabled"><span class="todo-list__label__description">Check links</span>',
            '<input type="checkbox" disabled="disabled" checked="checked"><span class="todo-list__label__description">Check links</span>',
            (string) $created['content'],
        );
        $connection->update('tx_ximatypo3contentplanner_comment', ['content' => $resolvedContent, 'todo_resolved' => 1], ['uid' => (int) $created['uid']]);

        $result = (new UpdateContentPlannerCommentTool())->execute([
            'commentUid' => (int) $created['uid'],
            'comment' => 'Before publishing:',
            'todos' => ['Check links', 'Check images', 'Check meta description'],
        ]);

        self::assertFalse($result->isError, json_encode($result->jsonSerialize()));

        $row = $connection->select(['content', 'todo_total', 'todo_resolved'], 'tx_ximatypo3contentplanner_comment', ['uid' => (int) $created['uid']])
            ->fetchAssociative();
        self::assertSame(3, (int) $row['todo_total'], 'Total must include the newly added to-do.');
        self::assertSame(1, (int) $row['todo_resolved'], '"Check links" must keep its resolved state.');
        self::assertStringContainsString(
            '<input type="checkbox" disabled="disabled" checked="checked"><span class="todo-list__label__description">Check links</span>',
            (string) $row['content'],
            '"Check links" must keep its checked markup.',
        );
    }

    public function testExecuteWritesToLiveEvenWhileUserIsInAWorkspace(): void
    {
        GeneralUtility::makeInstance(WorkspaceContextService::class)->switchToOptimalWorkspace($GLOBALS['BE_USER']);
        self::assertGreaterThan(0, $GLOBALS['BE_USER']->workspace, 'Test prerequisite: backend user must be inside a workspace.');

        $result = (new UpdateContentPlannerCommentTool())->execute([
            'commentUid' => 1,
            'comment' => 'Edited while "in" a workspace.',
        ]);

        self::assertFalse($result->isError, json_encode($result->jsonSerialize()));

        $row = $this->getConnectionPool()->getConnectionForTable('tx_ximatypo3contentplanner_comment')
            ->select(['content', 't3ver_wsid'], 'tx_ximatypo3contentplanner_comment', ['uid' => 1])
            ->fetchAssociative();
        self::assertSame('Edited while "in" a workspace.', $row['content']);
        self::assertSame(0, (int) $row['t3ver_wsid'], 'Comment must land on the live row, not a workspace version.');
        self::assertGreaterThan(0, $GLOBALS['BE_USER']->workspace, 'The backend user\'s workspace must be restored after the call.');
    }
}
