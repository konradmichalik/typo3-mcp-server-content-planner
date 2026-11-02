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

use KonradMichalik\Typo3McpServerContentPlanner\MCP\Tool\{GetContentPlannerInfoTool, SetContentPlannerCommentResolvedTool};
use KonradMichalik\Typo3McpServerContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * SetContentPlannerCommentResolvedToolTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class SetContentPlannerCommentResolvedToolTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loginBackendUser();
        $this->importFixture('pages.csv');
        $this->importFixture('comments.csv');
    }

    public function testExecuteResolvesAndReopensAComment(): void
    {
        $tool = new SetContentPlannerCommentResolvedTool();

        $result = $tool->execute(['commentUid' => 1, 'resolved' => true]);

        self::assertFalse($result->isError, json_encode($result->jsonSerialize()));
        $row = $this->fetchComment(1);
        self::assertGreaterThan(0, (int) $row['resolved_date']);
        self::assertSame(1, (int) $row['resolved_user']);

        $open = json_decode((new GetContentPlannerInfoTool())->execute(['table' => 'pages', 'uid' => 1])->content[0]->text, true);
        self::assertSame([], $open['comments'], 'Resolved comments are hidden unless includeResolved is set.');

        $info = json_decode((new GetContentPlannerInfoTool())->execute(['table' => 'pages', 'uid' => 1, 'includeResolved' => true])->content[0]->text, true);
        self::assertTrue($info['comments'][0]['resolved']);
        self::assertSame('admin', $info['comments'][0]['resolvedBy']);
        self::assertSame(date(\DATE_ATOM, (int) $row['resolved_date']), $info['comments'][0]['resolvedAt']);

        $result = $tool->execute(['commentUid' => 1, 'resolved' => false]);

        self::assertFalse($result->isError, json_encode($result->jsonSerialize()));
        $row = $this->fetchComment(1);
        self::assertSame(0, (int) $row['resolved_date']);
        self::assertSame(0, (int) $row['resolved_user']);
        $page = $this->getConnectionPool()->getConnectionForTable('pages')
            ->select(['tx_ximatypo3contentplanner_comments'], 'pages', ['uid' => 1])
            ->fetchAssociative();
        self::assertSame(1, (int) $page['tx_ximatypo3contentplanner_comments'], 'The record keeps counting its open comments.');
    }

    public function testExecuteLeavesAnAlreadyResolvedCommentUntouched(): void
    {
        $this->getConnectionPool()->getConnectionForTable('tx_ximatypo3contentplanner_comment')
            ->update('tx_ximatypo3contentplanner_comment', ['resolved_date' => 1700000500, 'resolved_user' => 6], ['uid' => 1]);

        $result = (new SetContentPlannerCommentResolvedTool())->execute(['commentUid' => 1, 'resolved' => true]);

        self::assertFalse($result->isError, json_encode($result->jsonSerialize()));
        self::assertStringContainsString('already', $result->content[0]->text);
        $row = $this->fetchComment(1);
        self::assertSame(1700000500, (int) $row['resolved_date'], 'The original resolve time must stay.');
        self::assertSame(6, (int) $row['resolved_user'], 'The original resolver must stay.');
    }

    public function testExecuteReportsWhenTheDataHandlerRefusesTheWrite(): void
    {
        // Group 8 may resolve comments but has no tables_modify on the comment table, so the
        // DataHandler drops the write although the planner permission check passes.
        $this->loginRestrictedBackendUser(9);

        $result = (new SetContentPlannerCommentResolvedTool())->execute(['commentUid' => 2, 'resolved' => true]);

        self::assertTrue($result->isError);
        self::assertStringContainsString('could not be resolved', $result->content[0]->text);
        self::assertSame(0, (int) $this->fetchComment(2)['resolved_date']);
    }

    public function testExecuteReturnsErrorForUnknownComment(): void
    {
        $result = (new SetContentPlannerCommentResolvedTool())->execute(['commentUid' => 999, 'resolved' => true]);

        self::assertTrue($result->isError);
        self::assertStringContainsString('not found', $result->content[0]->text);
    }

    public function testExecuteReturnsErrorWhenTheCurrentUserMayNotResolveComments(): void
    {
        // Group 5 can edit its own comments but has no comment-resolve permission. Comment uid 2
        // sits on the uid=2 subpage because the pid=0 root page is denied to non-admins by core.
        $this->loginRestrictedBackendUser(6);

        $result = (new SetContentPlannerCommentResolvedTool())->execute(['commentUid' => 2, 'resolved' => true]);

        self::assertTrue($result->isError);
        self::assertStringContainsString('not allowed to resolve', $result->content[0]->text);
        self::assertSame(0, (int) $this->fetchComment(2)['resolved_date']);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchComment(int $uid): array
    {
        return $this->getConnectionPool()->getConnectionForTable('tx_ximatypo3contentplanner_comment')
            ->select(['resolved_date', 'resolved_user'], 'tx_ximatypo3contentplanner_comment', ['uid' => $uid])
            ->fetchAssociative();
    }
}
