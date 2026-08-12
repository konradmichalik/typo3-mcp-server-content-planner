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

use Mcp\Types\CallToolResult;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Utility\PlannerUtility;

/**
 * ListContentPlannerStatusesTool.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class ListContentPlannerStatusesTool extends AbstractPlannerTool
{
    /**
     * @return array<string, mixed>
     */
    public function getSchema(): array
    {
        return [
            'description' => 'List all available Content Planner statuses (e.g. "Open", "In Progress", "Done") that can be assigned to a TYPO3 record via SetContentPlannerStatus.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [],
            ],
            'annotations' => [
                'readOnlyHint' => true,
                'idempotentHint' => true,
            ],
        ];
    }

    /**
     * @param array<array-key, mixed> $params
     */
    protected function doExecute(array $params): CallToolResult
    {
        $this->assertContentPlannerVisible();

        $statuses = array_map(
            static fn (Status $status): array => [
                'uid' => $status->getUid(),
                'title' => $status->getTitle(),
                'color' => $status->getColor(),
            ],
            PlannerUtility::getListOfStatus(),
        );

        return $this->createJsonResult(['statuses' => $statuses]);
    }
}
