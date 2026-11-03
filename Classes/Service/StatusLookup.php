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

namespace KonradMichalik\Typo3McpServerContentPlanner\Service;

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;

use function is_int;

/**
 * StatusLookup.
 *
 * Reads Content Planner statuses through the QueryBuilder. The Extbase StatusRepository of
 * xima_typo3_content_planner needs a frontend TypoScript request attribute that the MCP
 * endpoint does not provide.
 *
 * @phpstan-type StatusRow array{uid: int, title: string, color: string}
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class StatusLookup
{
    /**
     * @return list<StatusRow>
     */
    public function findAll(): array
    {
        return $this->fetch();
    }

    /**
     * @return StatusRow|null
     */
    public function findByTitle(string $title): ?array
    {
        return $this->fetch(['title' => $title])[0] ?? null;
    }

    /**
     * @return StatusRow|null
     */
    public function findByUid(int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }

        return $this->fetch(['uid' => $uid])[0] ?? null;
    }

    /**
     * @param array<string, int|string> $conditions
     *
     * @return list<StatusRow>
     */
    private function fetch(array $conditions = []): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable(Configuration::TABLE_STATUS);
        $queryBuilder
            ->select('uid', 'title', 'color')
            ->from(Configuration::TABLE_STATUS)
            ->orderBy('sorting');

        foreach ($conditions as $field => $value) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq($field, $queryBuilder->createNamedParameter($value, is_int($value) ? ParameterType::INTEGER : ParameterType::STRING)));
        }

        return array_map(
            static fn (array $row): array => [
                'uid' => (int) $row['uid'],
                'title' => (string) $row['title'],
                'color' => (string) $row['color'],
            ],
            $queryBuilder->executeQuery()->fetchAllAssociative(),
        );
    }
}
