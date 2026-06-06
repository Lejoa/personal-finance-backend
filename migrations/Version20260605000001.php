<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260605000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix email column collation to C for correct equality comparisons';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ALTER COLUMN email TYPE VARCHAR(180) COLLATE "C"');
        $this->addSql('ALTER TABLE users ALTER COLUMN google_id TYPE VARCHAR(255) COLLATE "C"');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ALTER COLUMN email TYPE VARCHAR(180)');
        $this->addSql('ALTER TABLE users ALTER COLUMN google_id TYPE VARCHAR(255)');
    }
}
