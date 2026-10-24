<?php

use AulaX\Tenancy\Application\Files\ImageUpload;
use AulaX\Tenancy\Application\Files\InvalidImage;
use Tests\Fixtures\Tenancy\Images;

/**
 * SPEC-006: only real PNG or JPEG images of up to 1 MB are accepted, judged
 * by their content (INS-R04.3, R04.4, NF02).
 */
it('accepts a PNG or a JPEG, with the type its content shows', function (string $contents, string $type, string $extension) {
    $upload = ImageUpload::fromContents($contents);

    expect($upload->mimeType)->toBe($type)
        ->and($upload->extension())->toBe($extension)
        ->and($upload->contents)->toBe($contents);
})->with([
    'png' => [fn () => Images::png(), 'image/png', 'png'],
    'jpeg' => [fn () => Images::jpeg(), 'image/jpeg', 'jpg'],
]);

it('rejects other types, files over 1 MB and files that only look like an image', function (string $contents) {
    expect(fn () => ImageUpload::fromContents($contents))
        ->toThrow(InvalidImage::class, 'Sube una imagen PNG o JPEG de hasta 1 MB.');
})->with([
    'gif' => [fn () => Images::gif()],
    'svg' => ['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
    'php script' => ['<?php system($_GET["c"]);'],
    'png header only' => [fn () => Images::pngHeaderOnly()],
    'over 1 MB' => [fn () => Images::pngOverOneMegabyte()],
    'empty' => [''],
]);

it('reports the rejection on the file field', function () {
    try {
        ImageUpload::fromContents(Images::gif());
    } catch (InvalidImage $invalid) {
        expect($invalid->field())->toBe('file');

        return;
    }

    $this->fail('The GIF was accepted.');
});
