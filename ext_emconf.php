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

$EM_CONF[$_EXTKEY] = [
    'title' => 'MCP Server Content Planner Bridge',
    'description' => 'Exposes xima-typo3-content-planner status, assignee and comment workflows as tools for typo3-mcp-server, so AI assistants can read and leave editorial comments on TYPO3 records.',
    'category' => 'services',
    'author' => 'Konrad Michalik',
    'author_email' => 'hej@konradmichalik.dev',
    'state' => 'stable',
    'version' => '0.1.0',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.5.99',
            'typo3' => '13.4.0-14.3.99',
            'mcp_server' => '0.5.0-0.5.99',
            'xima_typo3_content_planner' => '2.4.0-2.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
