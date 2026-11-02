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

use KonradMichalik\Typo3McpServerContentPlanner\MCP\Tool\{GetContentPlannerInfoTool, SetContentPlannerTodoStateTool};
use KonradMichalik\Typo3McpServerContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * SetContentPlannerTodoStateToolTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class SetContentPlannerTodoStateToolTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loginBackendUser();
        $this->importFixture('pages.csv');
        $this->importFixture('comments_todos.csv');
    }

    public function testExecuteChecksATodoAndUpdatesTheCounter(): void
    {
        $result = (new SetContentPlannerTodoStateTool($this->getConnectionPool()))->execute(['commentUid' => 10, 'todoIndex' => 0, 'resolved' => true]);

        self::assertFalse($result->isError, json_encode($result->jsonSerialize()));
        $row = $this->fetchComment(10);
        self::assertSame(2, (int) $row['todo_resolved']);
        self::assertSame(0, (int) $row['edited'], 'Ticking a box is not an edit of the comment.');

        $info = json_decode((new GetContentPlannerInfoTool())->execute(['table' => 'pages', 'uid' => 2])->content[0]->text, true);
        self::assertSame(
            [['index' => 0, 'text' => 'First', 'resolved' => true], ['index' => 1, 'text' => 'Second', 'resolved' => true]],
            $info['comments'][0]['todos'],
        );
    }

    public function testExecuteUnchecksATodo(): void
    {
        $result = (new SetContentPlannerTodoStateTool($this->getConnectionPool()))->execute(['commentUid' => 10, 'todoIndex' => 1, 'resolved' => false]);

        self::assertFalse($result->isError, json_encode($result->jsonSerialize()));
        self::assertSame(0, (int) $this->fetchComment(10)['todo_resolved']);
    }

    public function testExecuteReturnsErrorForUnknownTodoIndex(): void
    {
        $result = (new SetContentPlannerTodoStateTool($this->getConnectionPool()))->execute(['commentUid' => 10, 'todoIndex' => 5, 'resolved' => true]);

        self::assertTrue($result->isError);
        self::assertStringContainsString('no to-do with index 5', $result->content[0]->text);
    }

    public function testExecuteReturnsErrorForUnknownComment(): void
    {
        $result = (new SetContentPlannerTodoStateTool($this->getConnectionPool()))->execute(['commentUid' => 999, 'todoIndex' => 0, 'resolved' => true]);

        self::assertTrue($result->isError);
        self::assertStringContainsString('not found', $result->content[0]->text);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchComment(int $uid): array
    {
        return $this->getConnectionPool()->getConnectionForTable('tx_ximatypo3contentplanner_comment')
            ->select(['todo_resolved', 'edited'], 'tx_ximatypo3contentplanner_comment', ['uid' => $uid])
            ->fetchAssociative();
    }
}
