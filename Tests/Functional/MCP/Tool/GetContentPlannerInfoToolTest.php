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

use KonradMichalik\Typo3McpServerContentPlanner\MCP\Tool\GetContentPlannerInfoTool;
use KonradMichalik\Typo3McpServerContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * GetContentPlannerInfoToolTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class GetContentPlannerInfoToolTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loginBackendUser();
        $this->importFixture('status.csv');
        $this->importFixture('pages.csv');
        $this->importFixture('comments.csv');

        $connection = $this->getConnectionPool()->getConnectionForTable('pages');
        $connection->update('pages', [
            'tx_ximatypo3contentplanner_status' => 2,
            'tx_ximatypo3contentplanner_assignee' => 1,
        ], ['uid' => 1]);
    }

    public function testExecuteReturnsStatusAssigneeAndComments(): void
    {
        $result = (new GetContentPlannerInfoTool())->execute([
            'table' => 'pages',
            'uid' => 1,
        ]);

        self::assertFalse($result->isError, json_encode($result->jsonSerialize()));
        $data = json_decode($result->content[0]->text, true);
        self::assertSame('In Progress', $data['status']['title']);
        self::assertSame('admin', $data['assignee']);
        self::assertCount(1, $data['comments']);
        self::assertSame('Please double-check the SEO title.', $data['comments'][0]['content']);
    }

    public function testExecuteReturnsErrorForUnknownRecord(): void
    {
        $result = (new GetContentPlannerInfoTool())->execute([
            'table' => 'pages',
            'uid' => 999,
        ]);

        self::assertTrue($result->isError);
    }
}
