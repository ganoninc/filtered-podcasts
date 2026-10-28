<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260322110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add stored artwork URL to podcast feeds.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE podcast_feed ADD artwork_url VARCHAR(2048) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__podcast_feed AS SELECT id, title, slug, source_url, description, filters, cached_xml, cache_generated_at, created_at, updated_at FROM podcast_feed');
        $this->addSql('DROP TABLE podcast_feed');
        $this->addSql('CREATE TABLE podcast_feed (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, source_url VARCHAR(2048) NOT NULL, description CLOB DEFAULT NULL, filters CLOB NOT NULL, cached_xml CLOB DEFAULT NULL, cache_generated_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('INSERT INTO podcast_feed (id, title, slug, source_url, description, filters, cached_xml, cache_generated_at, created_at, updated_at) SELECT id, title, slug, source_url, description, filters, cached_xml, cache_generated_at, created_at, updated_at FROM __temp__podcast_feed');
        $this->addSql('DROP TABLE __temp__podcast_feed');
    }
}
