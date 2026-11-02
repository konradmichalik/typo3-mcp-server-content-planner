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

use KonradMichalik\Typo3McpServerContentPlanner\MCP\Tool\{ListContentPlannerAssigneesTool, SetContentPlannerAssigneeTool, SetContentPlannerStatusTool};
use KonradMichalik\Typo3McpServerContentPlanner\Tests\Functional\AbstractFunctionalTestCase;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;

/**
 * SetContentPlannerAssigneeToolTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class SetContentPlannerAssigneeToolTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loginBackendUser();
        $this->importFixture('status.csv');
        $this->importFixture('pages.csv');
    }

    public function testExecuteAssignsWithoutChangingTheStatus(): void
    {
        $this->importFixture('be_users_restricted.csv');
        (new SetContentPlannerStatusTool())->execute(['table' => 'pages', 'uid' => 1, 'status' => 'In Progress']);

        $result = (new SetContentPlannerAssigneeTool($this->getConnectionPool()))->execute([
            'table' => 'pages',
            'uid' => 1,
            'assigneeBackendUsername' => 'no-table-grants',
        ]);

        self::assertFalse($result->isError, json_encode($result->jsonSerialize()));
        $record = $this->get(RecordRepository::class)->findByUid('pages', 1);
        self::assertSame(2, (int) $record['tx_ximatypo3contentplanner_assignee']);
        self::assertSame(2, (int) $record['tx_ximatypo3contentplanner_status']);
    }

    public function testExecuteRemovesTheAssignmentWhenNoUsernameIsGiven(): void
    {
        (new SetContentPlannerStatusTool())->execute(['table' => 'pages', 'uid' => 1, 'status' => 'In Progress']);

        $result = (new SetContentPlannerAssigneeTool($this->getConnectionPool()))->execute(['table' => 'pages', 'uid' => 1]);

        self::assertFalse($result->isError, json_encode($result->jsonSerialize()));
        $record = $this->get(RecordRepository::class)->findByUid('pages', 1);
        self::assertSame(0, (int) $record['tx_ximatypo3contentplanner_assignee']);
    }

    public function testExecuteReturnsErrorForUnknownUser(): void
    {
        $result = (new SetContentPlannerAssigneeTool($this->getConnectionPool()))->execute([
            'table' => 'pages',
            'uid' => 1,
            'assigneeBackendUsername' => 'nonexistent-user',
        ]);

        self::assertTrue($result->isError);
        self::assertStringContainsString('nonexistent-user', $result->content[0]->text);
    }

    public function testExecuteReturnsErrorWhenTheCurrentUserMayNotAssignOthers(): void
    {
        // Targets the uid=2 subpage: the pid=0 root page is denied to non-admins by TYPO3 core before
        // the permission under test is reached.
        $this->loginRestrictedBackendUser(4);

        $result = (new SetContentPlannerAssigneeTool($this->getConnectionPool()))->execute([
            'table' => 'pages',
            'uid' => 2,
            'assigneeBackendUsername' => 'admin',
        ]);

        self::assertTrue($result->isError);
        self::assertStringContainsString('not allowed to assign', $result->content[0]->text);
    }

    public function testStatusToolAlsoEnforcesTheAssignPermission(): void
    {
        // Group 6: may change status, but has no assign permission.
        $this->loginRestrictedBackendUser(7);

        $result = (new SetContentPlannerStatusTool())->execute([
            'table' => 'pages',
            'uid' => 2,
            'status' => 'In Progress',
            'assigneeBackendUsername' => 'admin',
        ]);

        self::assertTrue($result->isError);
        self::assertStringContainsString('not allowed to assign', $result->content[0]->text);
        self::assertStringContainsString('not allowed to assign', $result->content[0]->text);
    }

    public function testExecuteRefusesUnassigningSomeoneElseWithoutTheAssignOthersPermission(): void
    {
        $this->getConnectionPool()->getConnectionForTable('pages')
            ->update('pages', ['tx_ximatypo3contentplanner_assignee' => 1], ['uid' => 2]);
        // Group 7: may assign itself, but not others (and so not unassign them).
        $this->loginRestrictedBackendUser(8);

        $result = (new SetContentPlannerAssigneeTool($this->getConnectionPool()))->execute(['table' => 'pages', 'uid' => 2]);

        self::assertTrue($result->isError);
        self::assertStringContainsString('not allowed to change assignments', $result->content[0]->text);
        self::assertSame(1, (int) $this->get(RecordRepository::class)->findByUid('pages', 2)['tx_ximatypo3contentplanner_assignee']);
    }

    public function testListOffersNoAssigneesToAUserWithoutAssignPermission(): void
    {
        // Group 6: may change status, but has no assign permission.
        $this->loginRestrictedBackendUser(7);

        $data = json_decode((new ListContentPlannerAssigneesTool())->execute([])->content[0]->text, true);

        self::assertSame([], $data['assignees']);
    }

    public function testListReturnsAssignableUsers(): void
    {
        $result = (new ListContentPlannerAssigneesTool())->execute([]);

        self::assertFalse($result->isError, json_encode($result->jsonSerialize()));
        $data = json_decode($result->content[0]->text, true);
        self::assertContains('admin', array_column($data['assignees'], 'username'));
    }
}
