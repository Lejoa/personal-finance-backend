<?php

namespace App\Repository;

use App\Entity\Tip;
use App\Entity\TipEmbedding;
use App\Entity\TipReference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TipEmbedding>
 */
class EmbeddingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TipEmbedding::class);
    }

    /**
     * Persists a TipEmbedding for a tip (reference = null) or a bibliographic reference.
     *
     * @param float[] $vector
     */
    public function save(Tip $tip, ?TipReference $reference, string $content, array $vector, int $chunkIndex = 0): TipEmbedding
    {
        $embedding = new TipEmbedding();
        $embedding->setTip($tip);
        $embedding->setReference($reference);
        $embedding->setContent($content);
        $embedding->setEmbeddingFromArray($vector);
        $embedding->setChunkIndex($chunkIndex);

        $em = $this->getEntityManager();
        $em->persist($embedding);
        $em->flush();

        return $embedding;
    }

    /**
     * Deletes all embeddings for a given tip so they can be re-indexed cleanly.
     */
    public function deleteByTip(Tip $tip): void
    {
        $this->createQueryBuilder('e')
            ->delete()
            ->where('e.tip = :tip')
            ->setParameter('tip', $tip)
            ->getQuery()
            ->execute();
    }

    /**
     * Searches for the most semantically similar chunks using pgvector cosine distance.
     * Returns up to $limit results ordered by descending similarity.
     *
     * @return array<array{content: string, source_title: string|null, source_author: string|null, similarity: float}>
     */
    public function findSimilar(string $embeddingLiteral, int $limit = 3): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<<SQL
            SELECT
                te.content,
                t.title       AS tip_title,
                tr.title      AS source_title,
                tr.author     AS source_author,
                1 - (te.embedding <=> :vec::vector) AS similarity
            FROM tip_embeddings te
            JOIN tips t ON t.id = te.tip_id
            LEFT JOIN tip_references tr ON tr.id = te.reference_id
            ORDER BY te.embedding <=> :vec::vector
            LIMIT :limit
        SQL;

        $rows = $conn->fetchAllAssociative($sql, [
            'vec'   => $embeddingLiteral,
            'limit' => $limit,
        ]);

        return array_map(static function (array $row): array {
            return [
                'content'       => $row['content'],
                'tip_title'     => $row['tip_title'],
                'source_title'  => $row['source_title'],
                'source_author' => $row['source_author'],
                'similarity'    => (float) $row['similarity'],
            ];
        }, $rows);
    }
}
