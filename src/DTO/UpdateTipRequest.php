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
        if ($this->title !== null)            { $tip->setTitle($this->title); }
        if ($this->author !== null)           { $tip->setAuthor($this->author); }
        if ($this->description !== null)      { $tip->setDescription($this->description); }
        if ($this->shortDescription !== null) { $tip->setShortDescription($this->shortDescription); }
        if ($this->authorTitle !== null)      { $tip->setAuthorTitle($this->authorTitle); }
        if ($this->imageSrc !== null)         { $tip->setImageSrc($this->imageSrc); }
        if ($this->tags !== null)             { $tip->setTags($this->tags); }
    }
}