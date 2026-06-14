<?php

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class VectorType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $dimensions = $column['dimensions'] ?? 1536;

        return "vector($dimensions)";
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return $value;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return $value;
    }
}
