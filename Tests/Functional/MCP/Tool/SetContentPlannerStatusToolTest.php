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
use KonradMichalik\Typo3McpServerContentPlanner\MCP\Tool\SetContentPlannerStatusTool;
use KonradMichalik\Typo3McpServerContentPlanner\Tests\Functional\AbstractFunctionalTestCase;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;

/**
 * SetContentPlannerStatusToolTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class SetContentPlannerStatusToolTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loginBackendUser();
        $this->importFixture('status.csv');
        $this->importFixture('pages.csv');
    }

    public function testExecuteSetsStatusAndAssignee(): void
    {
        $result = (new SetContentPlannerStatusTool())->execute([
            'table' => 'pages',
            'uid' => 1,
            'status' => 'In Progress',
        ]);

        self::assertFalse($result->isError, json_encode($result->jsonSerialize()));
        $record = $this->get(RecordRepository::class)->findByUid('pages', 1);
        self::assertSame(2, (int) $record['tx_ximatypo3contentplanner_status']);
        self::assertSame(1, (int) $record['tx_ximatypo3contentplanner_assignee'], 'Defaults to the authenticated backend user.');
    }

    public function testExecuteAssignsToAnExplicitlyNamedBackendUser(): void
    {
        $this->importFixture('be_users_restricted.csv');

        $result = (new SetContentPlannerStatusTool())->execute([
            'table' => 'pages',
            'uid' => 1,
            'status' => 'In Progress',
            'assigneeBackendUsername' => 'no-table-grants',
        ]);

        self::assertFalse($result->isError, json_encode($result->jsonSerialize()));
        $record = $this->get(RecordRepository::class)->findByUid('pages', 1);
        self::assertSame(2, (int) $record['tx_ximatypo3contentplanner_assignee']);
    }

    public function testExecuteReturnsErrorForUnknownRecord(): void
    {
        $result = (new SetContentPlannerStatusTool())->execute([
            'table' => 'pages',
            'uid' => 999,
            'status' => 'In Progress',
        ]);

        self::assertTrue($result->isError);
        self::assertStringContainsString('not found', $result->content[0]->text);
    }

    public function testExecuteReturnsErrorForUnknownStatus(): void
    {
        $result = (new SetContentPlannerStatusTool())->execute([
            'table' => 'pages',
            'uid' => 1,
            'status' => 'Nonexistent',
        ]);

        self::assertTrue($result->isError);
        self::assertStringContainsString('Open', $result->content[0]->text);
        self::assertStringContainsString('In Progress', $result->content[0]->text);
        self::assertStringContainsString('Done', $result->content[0]->text);
    }

    public function testExecuteReturnsErrorForUnknownAssigneeBackendUsername(): void
    {
        $result = (new SetContentPlannerStatusTool())->execute([
            'table' => 'pages',
            'uid' => 1,
            'status' => 'In Progress',
            'assigneeBackendUsername' => 'nonexistent-user',
        ]);

        self::assertTrue($result->isError);
        self::assertStringContainsString('nonexistent-user', $result->content[0]->text);

        $record = $this->get(RecordRepository::class)->findByUid('pages', 1);
        self::assertSame(0, (int) $record['tx_ximatypo3contentplanner_status'], 'Status must not change when the assignee validation fails.');
    }

    public function testExecuteReturnsErrorWhenTheCurrentUserHasNoAccessToTheRecord(): void
    {
        // Group 1: view-only, but no `tables_select` grant for "pages".
        $this->loginRestrictedBackendUser(2);

        $result = (new SetContentPlannerStatusTool())->execute([
            'table' => 'pages',
            'uid' => 1,
            'status' => 'In Progress',
        ]);

        self::assertTrue($result->isError);
        self::assertStringContainsString('does not have access to this record', $result->content[0]->text);
    }

    public function testExecuteReturnsErrorWhenTheTableIsNotAllowedForTheCurrentUser(): void
    {
        // Group 2: view-only, but `tx_ximatypo3contentplanner_allowed_tables` excludes "pages".
        $this->loginRestrictedBackendUser(3);

        $result = (new SetContentPlannerStatusTool())->execute([
            'table' => 'pages',
            'uid' => 1,
            'status' => 'In Progress',
        ]);

        self::assertTrue($result->isError);
        self::assertStringContainsString('not allowed to change the status of table', $result->content[0]->text);
    }

    public function testExecuteReturnsErrorWhenTheCurrentUserCannotChangeStatus(): void
    {
        // Group 3: view-only and can select "pages", but lacks the status-change permission.
        // Targets the uid=2 subpage (pid=1), not the uid=1 root page (pid=0): TYPO3 core's
        // BackendUtility::readPageAccess() denies non-admins access to pid=0 unconditionally,
        // which would otherwise fail on that earlier check instead of the one under test here.
        $this->loginRestrictedBackendUser(4);

        $result = (new SetContentPlannerStatusTool())->execute([
            'table' => 'pages',
            'uid' => 2,
            'status' => 'In Progress',
        ]);

        self::assertTrue($result->isError);
        self::assertStringContainsString('not allowed to set status', $result->content[0]->text);
    }

    public function testExecuteWritesToLiveEvenWhileUserIsInAWorkspace(): void
    {
        GeneralUtility::makeInstance(WorkspaceContextService::class)->switchToOptimalWorkspace($GLOBALS['BE_USER']);
        self::assertGreaterThan(0, $GLOBALS['BE_USER']->workspace, 'Test prerequisite: backend user must be inside a workspace.');

        $result = (new SetContentPlannerStatusTool())->execute([
            'table' => 'pages',
            'uid' => 1,
            'status' => 'Done',
        ]);

        self::assertFalse($result->isError, json_encode($result->jsonSerialize()));

        $liveRow = $this->getConnectionPool()->getConnectionForTable('pages')
            ->select(['tx_ximatypo3contentplanner_status', 't3ver_wsid'], 'pages', ['uid' => 1])
            ->fetchAssociative();
        self::assertSame(3, (int) $liveRow['tx_ximatypo3contentplanner_status']);
        self::assertSame(0, (int) $liveRow['t3ver_wsid'], 'Status must land on the live row, not a workspace version.');
        self::assertGreaterThan(0, $GLOBALS['BE_USER']->workspace, 'The backend user\'s workspace must be restored after the call.');
    }
}
