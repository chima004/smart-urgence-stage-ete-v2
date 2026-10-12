<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Add id_unite_cible and renfort_termine to reaffectation table.
 */
final class Version20260911060000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add id_unite_cible and renfort_termine columns to reaffectation table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reaffectation ADD id_unite_cible INT DEFAULT NULL, ADD renfort_termine TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE reaffectation ADD CONSTRAINT FK_FA77446AE83DDCBA FOREIGN KEY (id_unite_cible) REFERENCES unite (id_unite) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_FA77446AE83DDCBA ON reaffectation (id_unite_cible)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reaffectation DROP FOREIGN KEY FK_FA77446AE83DDCBA');
        $this->addSql('DROP INDEX IDX_FA77446AE83DDCBA ON reaffectation');
        $this->addSql('ALTER TABLE reaffectation DROP id_unite_cible, DROP renfort_termine');
    }
}
