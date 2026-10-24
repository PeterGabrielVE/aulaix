<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\Files;

use finfo;

/**
 * An uploaded logo or signature that is a real PNG or JPEG image of up to
 * 1 MB. The type comes from the content, never from the name nor the type
 * the client declares (INS-R04.3, R04.4, NF02).
 */
final readonly class ImageUpload
{
    public const int MAX_BYTES = 1024 * 1024;

    /**
     * Detected type => the extension it is stored with.
     */
    private const array EXTENSIONS = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
    ];

    private function __construct(public string $contents, public string $mimeType) {}

    /**
     * @throws InvalidImage
     */
    public static function fromContents(string $contents): self
    {
        if ($contents === '' || strlen($contents) > self::MAX_BYTES) {
            throw InvalidImage::rejected();
        }

        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->buffer($contents);

        if (! isset(self::EXTENSIONS[$mimeType])) {
            throw InvalidImage::rejected();
        }

        // A file that only copies the first bytes of an image passes the
        // type sniffing but cannot be decoded as one.
        $decoded = @getimagesizefromstring($contents);

        if ($decoded === false || $decoded['mime'] !== $mimeType) {
            throw InvalidImage::rejected();
        }

        return new self($contents, $mimeType);
    }

    public function extension(): string
    {
        return self::EXTENSIONS[$this->mimeType];
    }
}
