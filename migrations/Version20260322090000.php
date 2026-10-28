<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\PodcastFeed;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260322090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace fixed filter columns with a reusable JSON filters column.';
    }

    public function up(Schema $schema): void
    {
        $rows = $this->connection->fetchAllAssociative('SELECT * FROM podcast_feed');

        $this->connection->executeStatement('CREATE TABLE podcast_feed_new (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, source_url VARCHAR(2048) NOT NULL, description CLOB DEFAULT NULL, filters CLOB NOT NULL, cached_xml CLOB DEFAULT NULL, cache_generated_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');

        foreach ($rows as $row) {
            $filters = [];

            if (!empty($row['include_title_contains'])) {
                $filters[] = [
                    'category' => PodcastFeed::CATEGORY_TITLE,
                    'operator' => PodcastFeed::OPERATOR_CONTAINS,
                    'value' => $row['include_title_contains'],
                    'combinator' => PodcastFeed::COMBINATOR_AND,
                ];
            }

            if (!empty($row['exclude_title_contains'])) {
                $filters[] = [
                    'category' => PodcastFeed::CATEGORY_TITLE,
                    'operator' => PodcastFeed::OPERATOR_NOT_CONTAINS,
                    'value' => $row['exclude_title_contains'],
                    'combinator' => PodcastFeed::COMBINATOR_AND,
                ];
            }

            if ($row['min_duration_minutes'] !== null) {
                $filters[] = [
                    'category' => PodcastFeed::CATEGORY_DURATION,
                    'operator' => PodcastFeed::OPERATOR_LONGER_THAN,
                    'value' => (int) $row['min_duration_minutes'],
                    'combinator' => PodcastFeed::COMBINATOR_AND,
                ];
            }

            if ($row['max_duration_minutes'] !== null) {
                $filters[] = [
                    'category' => PodcastFeed::CATEGORY_DURATION,
                    'operator' => PodcastFeed::OPERATOR_SHORTER_THAN,
                    'value' => (int) $row['max_duration_minutes'],
                    'combinator' => PodcastFeed::COMBINATOR_AND,
                ];
            }

            $this->connection->insert('podcast_feed_new', [
                'id' => $row['id'],
                'title' => $row['title'],
                'slug' => $row['slug'],
                'source_url' => $row['source_url'],
                'description' => $row['description'],
                'filters' => json_encode($filters, JSON_THROW_ON_ERROR),
                'cached_xml' => $row['cached_xml'],
                'cache_generated_at' => $row['cache_generated_at'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ]);
        }

        $this->connection->executeStatement('DROP TABLE podcast_feed');
        $this->connection->executeStatement('ALTER TABLE podcast_feed_new RENAME TO podcast_feed');
    }

    public function down(Schema $schema): void
    {
        $rows = $this->connection->fetchAllAssociative('SELECT * FROM podcast_feed');

        $this->connection->executeStatement('CREATE TABLE podcast_feed_old (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, source_url VARCHAR(2048) NOT NULL, description CLOB DEFAULT NULL, include_title_contains VARCHAR(255) DEFAULT NULL, exclude_title_contains VARCHAR(255) DEFAULT NULL, min_duration_minutes INTEGER DEFAULT NULL, max_duration_minutes INTEGER DEFAULT NULL, cached_xml CLOB DEFAULT NULL, cache_generated_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');

        foreach ($rows as $row) {
            $filters = json_decode((string) $row['filters'], true);

            $includeTitleContains = null;
            $excludeTitleContains = null;
            $minDurationMinutes = null;
            $maxDurationMinutes = null;

            foreach (is_array($filters) ? $filters : [] as $filter) {
                $operator = $filter['operator'] ?? null;
                $value = $filter['value'] ?? null;

                if ($operator === PodcastFeed::OPERATOR_CONTAINS && $includeTitleContains === null) {
                    $includeTitleContains = is_string($value) ? $value : null;
                }

                if ($operator === PodcastFeed::OPERATOR_NOT_CONTAINS && $excludeTitleContains === null) {
                    $excludeTitleContains = is_string($value) ? $value : null;
                }

                if ($operator === PodcastFeed::OPERATOR_LONGER_THAN && $minDurationMinutes === null) {
                    $minDurationMinutes = is_numeric($value) ? (int) $value : null;
                }

                if ($operator === PodcastFeed::OPERATOR_SHORTER_THAN && $maxDurationMinutes === null) {
                    $maxDurationMinutes = is_numeric($value) ? (int) $value : null;
                }
            }

            $this->connection->insert('podcast_feed_old', [
                'id' => $row['id'],
                'title' => $row['title'],
                'slug' => $row['slug'],
                'source_url' => $row['source_url'],
                'description' => $row['description'],
                'include_title_contains' => $includeTitleContains,
                'exclude_title_contains' => $excludeTitleContains,
                'min_duration_minutes' => $minDurationMinutes,
                'max_duration_minutes' => $maxDurationMinutes,
                'cached_xml' => $row['cached_xml'],
                'cache_generated_at' => $row['cache_generated_at'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ]);
        }

        $this->connection->executeStatement('DROP TABLE podcast_feed');
        $this->connection->executeStatement('ALTER TABLE podcast_feed_old RENAME TO podcast_feed');
    }
}
