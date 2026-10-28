<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260321103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users and podcast feed tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON user (email)');

        $this->addSql('CREATE TABLE podcast_feed (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, source_url VARCHAR(2048) NOT NULL, description CLOB DEFAULT NULL, include_title_contains VARCHAR(255) DEFAULT NULL, exclude_title_contains VARCHAR(255) DEFAULT NULL, min_duration_minutes INTEGER DEFAULT NULL, max_duration_minutes INTEGER DEFAULT NULL, cached_xml CLOB DEFAULT NULL, cache_generated_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE podcast_feed');
        $this->addSql('DROP TABLE "user"');
    }
}
