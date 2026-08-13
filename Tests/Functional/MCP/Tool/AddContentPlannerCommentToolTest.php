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
use KonradMichalik\Typo3McpServerContentPlanner\MCP\Tool\AddContentPlannerCommentTool;
use KonradMichalik\Typo3McpServerContentPlanner\Tests\Functional\AbstractFunctionalTestCase;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * AddContentPlannerCommentToolTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class AddContentPlannerCommentToolTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loginBackendUser();
        $this->importFixture('pages.csv');
        $this->importFixture('comments.csv');
    }

    public function testExecuteAddsAComment(): void
    {
        $result = (new AddContentPlannerCommentTool())->execute([
            'table' => 'pages',
            'uid' => 1,
            'comment' => 'Please review the meta description.',
        ]);

        self::assertFalse($result->isError, json_encode($result->jsonSerialize()));

        // comments.csv already seeds one comment on pages:1 (uid 1); order by uid DESC
        // to read the just-created comment rather than the pre-existing fixture row.
        $row = $this->getConnectionPool()->getConnectionForTable('tx_ximatypo3contentplanner_comment')
            ->select(['content', 'author'], 'tx_ximatypo3contentplanner_comment', ['foreign_uid' => 1], [], ['uid' => 'DESC'])
            ->fetchAssociative();
        self::assertSame('Please review the meta description.', $row['content']);
        self::assertSame(1, (int) $row['author']);
    }

    public function testExecuteReturnsErrorWhenTheCurrentUserHasNoAccessToTheRecord(): void
    {
        // Group 4: can create comments (has the comment-create permission and a
        // tables_modify grant for the comment table), but lacks tables_select for
        // "pages" - isolates checkAccessForRecord() from canCreateComment(), which
        // runs first and would otherwise mask it.
        $this->loginRestrictedBackendUser(5);

        $result = (new AddContentPlannerCommentTool())->execute([
            'table' => 'pages',
            'uid' => 1,
            'comment' => 'Should be rejected.',
        ]);

        self::assertTrue($result->isError);
        self::assertStringContainsString('does not have access to this record', $result->content[0]->text);
    }

    public function testExecuteReturnsErrorForUnknownRecord(): void
    {
        $result = (new AddContentPlannerCommentTool())->execute([
            'table' => 'pages',
            'uid' => 999,
            'comment' => 'Should be rejected.',
        ]);

        self::assertTrue($result->isError);
        self::assertStringContainsString('not found', $result->content[0]->text);
    }

    public function testExecuteAddsTodosAsMarkupInsideTheComment(): void
    {
        $result = (new AddContentPlannerCommentTool())->execute([
            'table' => 'pages',
            'uid' => 1,
            'comment' => 'Before publishing:',
            'todos' => ['Check links', 'Check images'],
        ]);

        self::assertFalse($result->isError, json_encode($result->jsonSerialize()));

        // comments.csv already seeds one comment on pages:1 (uid 1); order by uid DESC
        // to read the just-created comment rather than the pre-existing fixture row.
        $row = $this->getConnectionPool()->getConnectionForTable('tx_ximatypo3contentplanner_comment')
            ->select(['content'], 'tx_ximatypo3contentplanner_comment', ['foreign_uid' => 1], [], ['uid' => 'DESC'])
            ->fetchAssociative();
        self::assertStringContainsString('Before publishing:', $row['content']);
        self::assertStringContainsString('Check links', $row['content']);
        self::assertStringContainsString('todo-list', $row['content']);
    }

    public function testExecuteRepliesToAnExistingComment(): void
    {
        $result = (new AddContentPlannerCommentTool())->execute([
            'table' => 'pages',
            'uid' => 1,
            'comment' => 'Done, thanks!',
            'parentCommentUid' => 1,
        ]);

        self::assertFalse($result->isError, json_encode($result->jsonSerialize()));

        $row = $this->getConnectionPool()->getConnectionForTable('tx_ximatypo3contentplanner_comment')
            ->select(['content', 'parent_uid'], 'tx_ximatypo3contentplanner_comment', ['content' => 'Done, thanks!'])
            ->fetchAssociative();
        self::assertSame(1, (int) $row['parent_uid']);
    }

    public function testExecuteReturnsErrorWhenTheCurrentUserCannotCreateComments(): void
    {
        $this->loginRestrictedBackendUser(2);

        $result = (new AddContentPlannerCommentTool())->execute([
            'table' => 'pages',
            'uid' => 1,
            'comment' => 'Should be rejected.',
        ]);

        self::assertTrue($result->isError);
        self::assertStringContainsString('not allowed to create comments', $result->content[0]->text);
    }

    public function testExecuteReturnsErrorForParentCommentOnADifferentRecord(): void
    {
        $result = (new AddContentPlannerCommentTool())->execute([
            'table' => 'pages',
            'uid' => 2,
            'comment' => 'Reply to the wrong record.',
            'parentCommentUid' => 1,
        ]);

        self::assertTrue($result->isError);
    }

    public function testExecuteWritesToLiveEvenWhileUserIsInAWorkspace(): void
    {
        GeneralUtility::makeInstance(WorkspaceContextService::class)->switchToOptimalWorkspace($GLOBALS['BE_USER']);
        self::assertGreaterThan(0, $GLOBALS['BE_USER']->workspace, 'Test prerequisite: backend user must be inside a workspace.');

        $result = (new AddContentPlannerCommentTool())->execute([
            'table' => 'pages',
            'uid' => 1,
            'comment' => 'Left while "in" a workspace.',
        ]);

        self::assertFalse($result->isError, json_encode($result->jsonSerialize()));

        // comments.csv already seeds one comment on pages:1 (uid 1); order by uid DESC
        // to read the just-created comment rather than the pre-existing fixture row.
        $liveRow = $this->getConnectionPool()->getConnectionForTable('tx_ximatypo3contentplanner_comment')
            ->select(['content', 't3ver_wsid'], 'tx_ximatypo3contentplanner_comment', ['foreign_uid' => 1], [], ['uid' => 'DESC'])
            ->fetchAssociative();
        self::assertSame('Left while "in" a workspace.', $liveRow['content']);
        self::assertSame(0, (int) $liveRow['t3ver_wsid'], 'Comment must land on the live row, not a workspace version.');
        self::assertGreaterThan(0, $GLOBALS['BE_USER']->workspace, 'The backend user\'s workspace must be restored after the call.');
    }
}
