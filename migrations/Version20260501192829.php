<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260501192829 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE loan_calculations ADD amount DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE loan_calculations ADD annual_rate DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE loan_calculations ADD extra_payment DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE loan_calculations ADD periodic_payment DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE loan_calculations ADD total_interest DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE loan_calculations ADD total_paid DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE loan_calculations DROP monto');
        $this->addSql('ALTER TABLE loan_calculations DROP tasa_anual');
        $this->addSql('ALTER TABLE loan_calculations DROP pago_extra');
        $this->addSql('ALTER TABLE loan_calculations DROP pago_mensual');
        $this->addSql('ALTER TABLE loan_calculations DROP interes_total');
        $this->addSql('ALTER TABLE loan_calculations DROP total_pagado');
        $this->addSql('ALTER TABLE loan_calculations RENAME COLUMN plazo_meses TO term_months');
        $this->addSql('ALTER TABLE loan_calculations RENAME COLUMN frecuencia TO frequency');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE loan_calculations ADD monto DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE loan_calculations ADD tasa_anual DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE loan_calculations ADD pago_extra DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE loan_calculations ADD pago_mensual DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE loan_calculations ADD interes_total DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE loan_calculations ADD total_pagado DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE loan_calculations DROP amount');
        $this->addSql('ALTER TABLE loan_calculations DROP annual_rate');
        $this->addSql('ALTER TABLE loan_calculations DROP extra_payment');
        $this->addSql('ALTER TABLE loan_calculations DROP periodic_payment');
        $this->addSql('ALTER TABLE loan_calculations DROP total_interest');
        $this->addSql('ALTER TABLE loan_calculations DROP total_paid');
        $this->addSql('ALTER TABLE loan_calculations RENAME COLUMN term_months TO plazo_meses');
        $this->addSql('ALTER TABLE loan_calculations RENAME COLUMN frequency TO frecuencia');
    }
}
