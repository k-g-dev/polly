<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250831133455 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change agreed_terms_at to nullable.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE
            user
        CHANGE
            agreed_terms_at agreed_terms_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE
            user
        CHANGE
            agreed_terms_at agreed_terms_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
