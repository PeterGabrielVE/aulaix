<?php

use AulaX\Tenancy\Application\Files\AssetKind;
use AulaX\Tenancy\Application\Files\ImageUpload;
use AulaX\Tenancy\Application\Port\InstitutionFiles;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\Tenancy\Images;

/**
 * SPEC-006: the files of each institution live in its own private folder,
 * under names the system generates (INS-R04.8, R07.2, NF02).
 */
beforeEach(function () {
    Storage::fake(config('tenancy.files_disk'));
});

function institutionFiles(): InstitutionFiles
{
    return app(InstitutionFiles::class);
}

it('stores an image under the active institution folder with a generated name', function () {
    $institution = tenant();

    $first = institutionFiles()->store(AssetKind::Logo, ImageUpload::fromContents(Images::png()));
    $second = institutionFiles()->store(AssetKind::Logo, ImageUpload::fromContents(Images::png()));

    expect($first)->toMatch('#^institutions/'.$institution->id.'/logo-[A-Za-z0-9]{40}\.png$#')
        ->and($second)->not->toBe($first);
    Storage::disk(config('tenancy.files_disk'))->assertExists($first);
});

it('reads back the stored image with its type', function () {
    tenant();
    $path = institutionFiles()->store(AssetKind::DirectorSignature, ImageUpload::fromContents(Images::jpeg()));

    $image = institutionFiles()->read($path);

    expect($image->contents)->toBe(Images::jpeg())
        ->and($image->mimeType)->toBe('image/jpeg');
});

it('neither reads nor deletes the files of another institution, even through ..', function (string $pathOf) {
    $other = tenant();
    $foreign = institutionFiles()->store(AssetKind::Logo, ImageUpload::fromContents(Images::png()));
    $own = tenant();
    $path = str_replace(['{own}', '{foreign}'], [$own->id, $foreign], $pathOf);

    institutionFiles()->delete($path);

    expect(institutionFiles()->read($path))->toBeNull();
    Storage::disk(config('tenancy.files_disk'))->assertExists($foreign);
})->with([
    'its path' => ['{foreign}'],
    'climbing out of the own folder' => ['institutions/{own}/../../{foreign}'],
]);

it('deletes a file of the active institution', function () {
    tenant();
    $path = institutionFiles()->store(AssetKind::Logo, ImageUpload::fromContents(Images::png()));

    institutionFiles()->delete($path);

    Storage::disk(config('tenancy.files_disk'))->assertMissing($path);
});

it('uses the configured disk, private by default', function () {
    config(['tenancy.files_disk' => 'elsewhere']);
    Storage::fake('elsewhere');
    tenant();

    $path = institutionFiles()->store(AssetKind::Logo, ImageUpload::fromContents(Images::png()));

    Storage::disk('elsewhere')->assertExists($path);
    expect(config('filesystems.disks.institution_files.serve') ?? false)->toBeFalse()
        ->and(config('filesystems.disks.institution_files.visibility'))->toBe('private');
});
