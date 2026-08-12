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

use KonradMichalik\Typo3McpServerContentPlanner\MCP\Tool\ListContentPlannerStatusesTool;
use KonradMichalik\Typo3McpServerContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * ListContentPlannerStatusesToolTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ListContentPlannerStatusesToolTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loginBackendUser();
        $this->importFixture('status.csv');
    }

    public function testExecuteReturnsAllStatuses(): void
    {
        $result = (new ListContentPlannerStatusesTool())->execute([]);

        self::assertFalse($result->isError, json_encode($result->jsonSerialize()));
        $data = json_decode($result->content[0]->text, true);
        self::assertCount(3, $data['statuses']);
        self::assertSame('Open', $data['statuses'][0]['title']);
        self::assertSame('Done', $data['statuses'][2]['title']);
    }
}
