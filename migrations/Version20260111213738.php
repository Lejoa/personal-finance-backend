<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260111213738 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE categories RENAME COLUMN nombre TO name');
        $this->addSql('ALTER TABLE categories RENAME COLUMN descripcion TO description');
        $this->addSql('ALTER TABLE transactions RENAME COLUMN nombre TO name');
        $this->addSql('ALTER TABLE transactions RENAME COLUMN tipo TO type');
        $this->addSql('ALTER TABLE transactions RENAME COLUMN monto TO amount');
        $this->addSql('ALTER TABLE transactions RENAME COLUMN fecha TO date');
        $this->addSql('ALTER TABLE transactions RENAME COLUMN nota TO note');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE categories RENAME COLUMN name TO nombre');
        $this->addSql('ALTER TABLE categories RENAME COLUMN description TO descripcion');
        $this->addSql('ALTER TABLE transactions RENAME COLUMN name TO nombre');
        $this->addSql('ALTER TABLE transactions RENAME COLUMN type TO tipo');
        $this->addSql('ALTER TABLE transactions RENAME COLUMN amount TO monto');
        $this->addSql('ALTER TABLE transactions RENAME COLUMN date TO fecha');
        $this->addSql('ALTER TABLE transactions RENAME COLUMN note TO nota');
    }
}
