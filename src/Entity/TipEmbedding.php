<?php

namespace App\Entity;

use App\Repository\EmbeddingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmbeddingRepository::class)]
#[ORM\Table(name: 'tip_embeddings')]
class TipEmbedding
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Tip::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Tip $tip = null;

    // NULL → embedding del tip mismo; SET → embedding de la referencia bibliográfica
    #[ORM\ManyToOne(targetEntity: TipReference::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?TipReference $reference = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\Column(type: 'vector', nullable: true)]
    private ?string $embedding = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $chunkIndex = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTip(): ?Tip
    {
        return $this->tip;
    }

    public function setTip(?Tip $tip): static
    {
        $this->tip = $tip;

        return $this;
    }

    public function getReference(): ?TipReference
    {
        return $this->reference;
    }

    public function setReference(?TipReference $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getEmbedding(): ?string
    {
        return $this->embedding;
    }

    /**
     * @param float[] $vector
     */
    public function setEmbeddingFromArray(array $vector): static
    {
        $this->embedding = '[' . implode(',', $vector) . ']';

        return $this;
    }

    public function getChunkIndex(): int
    {
        return $this->chunkIndex;
    }

    public function setChunkIndex(int $chunkIndex): static
    {
        $this->chunkIndex = $chunkIndex;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }
}
