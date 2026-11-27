<?php

use AulaX\Documents\Application\Port\DocumentStorage;
use AulaX\Tenancy\Application\Port\InstitutionFiles;
use Illuminate\Support\Facades\Storage;

/**
 * SPEC-012 DOC-R05.4, R10.1, NF04 (ADR 0015): private per-institution PDF
 * storage isolated under `institutions/{id}/documents/`, counted by
 * `InstitutionFiles::usedBytes()` and immune to cross-tenant path traversal.
 */
test('stores PDFs under the active institution folder and refuses reading paths of another institution', function () {
    Storage::fake(config('tenancy.files_disk'));

    $north = tenant(['subdomain' => 'doc-store-norte']);
    $storage = app(DocumentStorage::class);
    $payload = '%PDF-1.4 fake document contents';

    $path = $storage->store($payload);

    expect($path)->toStartWith("institutions/{$north->id}/documents/")
        ->and($path)->toEndWith('.pdf')
        ->and($storage->read($path))->toBe($payload)
        ->and(app(InstitutionFiles::class)->usedBytes())->toBe(strlen($payload));

    // Another institution cannot read or delete North's PDF even knowing its path.
    tenant(['subdomain' => 'doc-store-sur']);
    expect($storage->read($path))->toBeNull();
    $storage->delete($path);

    // Traversal attempts are refused.
    expect($storage->read("institutions/{$north->id}/documents/../logo.png"))->toBeNull();

    // Still intact for North.
    useTenant($north);
    expect($storage->read($path))->toBe($payload);
});
