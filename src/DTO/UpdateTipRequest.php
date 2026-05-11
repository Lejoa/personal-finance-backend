<?php

namespace App\DTO;

use App\Entity\Tip;
use Symfony\Component\Validator\Constraints as Assert;

class UpdateTipRequest
{
    #[Assert\Length(
        max: 255,
        maxMessage: 'El título no puede tener más de {{ limit }} caracteres.'
    )]
    public ?string $title = null;

    #[Assert\Length(
        max: 255,
        maxMessage: 'El autor no puede tener más de {{ limit }} caracteres.'
    )]
    public ?string $author = null;

    #[Assert\Length(
        max: 2000,
        maxMessage: 'La descripción no puede tener más de {{ limit }} caracteres.'
    )]
    public ?string $description = null;

    #[Assert\Length(
        max: 500,
        maxMessage: 'La descripción corta no puede tener más de {{ limit }} caracteres.'
    )]
    public ?string $shortDescription = null;

    #[Assert\Length(
        max: 255,
        maxMessage: 'El título del autor no puede tener más de {{ limit }} caracteres.'
    )]
    public ?string $authorTitle = null;

    #[Assert\Length(
        max: 500,
        maxMessage: 'La URL de imagen no puede tener más de {{ limit }} caracteres.'
    )]
    public ?string $imageSrc = null;

    #[Assert\Length(
        max: 500,
        maxMessage: 'Los tags no pueden tener más de {{ limit }} caracteres.'
    )]
    public ?string $tags = null;

    /**
     * Applies all non-null DTO fields to the given Tip entity.
     * Fields left as null are not modified on the entity.
     */
    public function applyTo(Tip $tip): void
    {
        if (null !== $this->title) {
            $tip->setTitle($this->title);
        }
        if (null !== $this->author) {
            $tip->setAuthor($this->author);
        }
        if (null !== $this->description) {
            $tip->setDescription($this->description);
        }
        if (null !== $this->shortDescription) {
            $tip->setShortDescription($this->shortDescription);
        }
        if (null !== $this->authorTitle) {
            $tip->setAuthorTitle($this->authorTitle);
        }
        if (null !== $this->imageSrc) {
            $tip->setImageSrc($this->imageSrc);
        }
        if (null !== $this->tags) {
            $tip->setTags($this->tags);
        }
    }
}
