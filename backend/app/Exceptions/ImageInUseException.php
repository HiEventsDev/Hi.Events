<?php

namespace HiEvents\Exceptions;

use Exception;
use HiEvents\Services\Domain\Image\DTO\ImageReferenceDTO;

class ImageInUseException extends Exception
{
    /**
     * @param  ImageReferenceDTO[]  $references
     */
    public function __construct(
        public readonly array $references,
        public readonly bool $hasProtectedReferences,
        string $message = '',
    ) {
        parent::__construct($message ?: 'Image is referenced by other content', 409);
    }
}
