<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\RefreshTokenRepository;

#[ORM\Entity(repositoryClass: RefreshTokenRepository::class)]
#[ORM\Table(name: 'refresh_tokens')]
#[ORM\Index(name: 'idx_refresh_token', columns: ['token'])]
#[ORM\Index(name: 'idx_user_id', columns: ['user_id'])]
class RefreshToken  {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(
        name: 'user_id', 
        referencedColumnName: 'id', 
        onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $token;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $expiresAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'boolean')]
    private bool $isRevoked = false;

    public function __construct() {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getUser(): User {
        return $this->user;
    }
    
    public function setUser(User $user): self {
        $this->user = $user;
        return $this;
    }

    public function getToken(): string {
        return $this->token;
    }

    public function setToken(string $token): self {
        $this->token = $token;
        return $this;
    }

    public function getExpiresAt(): \DateTimeInterface {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeInterface $expiresAt): self {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface {
        return $this->createdAt;
    }

    public function isExpired(): bool {
        return $this->expiresAt < new \DateTime();
    }

    public function isRevoked(): bool {
        return $this->isRevoked;
    }

    public function setIsRevoked(bool $isRevoked): self {
        $this->isRevoked = $isRevoked;
        return $this;
    }

    public function isValid(): bool {
        return !$this->isExpired() && !$this->isRevoked();
    }
}