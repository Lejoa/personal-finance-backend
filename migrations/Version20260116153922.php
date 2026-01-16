<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260116153922 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE transactions ALTER synchronized TYPE VARCHAR(10) USING CASE WHEN synchronized = true THEN \'done\' ELSE \'pending\' END');
        $this->addSql('ALTER TABLE transactions ALTER synchronized SET DEFAULT \'pending\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE transactions ALTER synchronized TYPE BOOLEAN');
        $this->addSql('ALTER TABLE transactions ALTER synchronized SET DEFAULT false');
    }
}
