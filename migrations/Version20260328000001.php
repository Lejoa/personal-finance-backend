<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260328000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add shortDescription, authorTitle, imageSrc and tags columns to tips table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tips ADD short_description VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE tips ADD author_title VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE tips ADD image_src VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE tips ADD tags VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tips DROP COLUMN short_description');
        $this->addSql('ALTER TABLE tips DROP COLUMN author_title');
        $this->addSql('ALTER TABLE tips DROP COLUMN image_src');
        $this->addSql('ALTER TABLE tips DROP COLUMN tags');
    }
}