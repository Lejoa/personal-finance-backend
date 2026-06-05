<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260604000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enable pgvector extension and create tip_references and tip_embeddings tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS vector');

        $this->addSql('
            CREATE TABLE tip_references (
                id         SERIAL PRIMARY KEY,
                tip_id     INTEGER NOT NULL REFERENCES tips(id) ON DELETE CASCADE,
                title      VARCHAR(255) NOT NULL,
                author     VARCHAR(255) NOT NULL,
                type       VARCHAR(20)  NOT NULL,
                year       INTEGER,
                url        VARCHAR(500),
                excerpt    TEXT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT NOW()
            )
        ');

        $this->addSql('
            CREATE TABLE tip_embeddings (
                id           SERIAL PRIMARY KEY,
                tip_id       INTEGER NOT NULL REFERENCES tips(id) ON DELETE CASCADE,
                reference_id INTEGER REFERENCES tip_references(id) ON DELETE CASCADE,
                content      TEXT NOT NULL,
                embedding    vector(1536),
                chunk_index  INTEGER NOT NULL DEFAULT 0,
                created_at   TIMESTAMP NOT NULL DEFAULT NOW()
            )
        ');

        // ivfflat index for approximate cosine similarity search
        // lists=10 is suitable for corpora up to ~100k vectors
        $this->addSql('
            CREATE INDEX tip_embeddings_embedding_idx
            ON tip_embeddings
            USING ivfflat (embedding vector_cosine_ops)
            WITH (lists = 10)
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS tip_embeddings');
        $this->addSql('DROP TABLE IF EXISTS tip_references');
        $this->addSql('DROP EXTENSION IF EXISTS vector');
    }
}
