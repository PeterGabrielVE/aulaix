<?php

/**
 * SPEC-000 (FND-R06): keeps specs/ honest. Every spec has valid metadata,
 * criterion IDs are unique, the traceability matrix only points at tests that
 * exist, and an implemented spec traces every one of its criteria.
 */
const SPEC_STATES = ['borrador', 'aprobada', 'implementando', 'implementada', 'obsoleta'];

const CRITERION_ID = '[A-Z]{3}-(?:R\d{2}\.\d+|NF\d{2})';

function specsPath(string $path = ''): string
{
    return dirname(__DIR__, 2).'/specs'.($path === '' ? '' : '/'.$path);
}

/**
 * @return array<string, array{string}>
 */
function specDirectories(): array
{
    $directories = glob(specsPath('[0-9][0-9][0-9]-*'), GLOB_ONLYDIR) ?: [];

    return collect($directories)
        ->mapWithKeys(fn (string $directory) => [basename($directory) => [basename($directory)]])
        ->all();
}

function specState(string $file): ?string
{
    preg_match('/\A---\R.*?^estado:\s*(\S+)/ms', file_get_contents($file), $match);

    return $match[1] ?? null;
}

/**
 * @return array{0: string, 1: string} The requirements before and after the traceability matrix heading.
 */
function requirementsParts(string $spec): array
{
    $parts = explode('## Matriz de trazabilidad', file_get_contents(specsPath("{$spec}/requirements.md")), 2);

    return [$parts[0], $parts[1] ?? ''];
}

/**
 * @return list<string>
 */
function criteriaOf(string $spec): array
{
    preg_match_all('/\*\*('.CRITERION_ID.')\*\*/', requirementsParts($spec)[0], $matches);

    return $matches[1];
}

/**
 * @return list<array{criterion: string, file: string, test: string}>
 */
function matrixReferences(string $spec): array
{
    $references = [];

    foreach (preg_split('/\R/', requirementsParts($spec)[1]) as $row) {
        if (! preg_match('/^\| ('.CRITERION_ID.') \|/', $row, $criterion)) {
            continue;
        }

        preg_match_all('/`(tests\/[^`]+)` › «([^»]+)»/u', $row, $cells, PREG_SET_ORDER);

        foreach ($cells as [, $file, $test]) {
            $references[] = ['criterion' => $criterion[1], 'file' => $file, 'test' => $test];
        }
    }

    return $references;
}

/**
 * Names of the test() / it() / arch() declarations of a test file, unescaped.
 *
 * @return list<string>
 */
function declaredTestNames(string $file): array
{
    preg_match_all('/^\s*(?:test|it|arch)\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1/m', file_get_contents($file), $matches);

    return array_map(fn (string $name) => stripslashes($name), $matches[2]);
}

test('every spec has requirements and valid states in its documents', function (string $spec) {
    expect(specsPath("{$spec}/requirements.md"))->toBeFile();

    foreach (['requirements', 'design', 'tasks'] as $document) {
        $file = specsPath("{$spec}/{$document}.md");

        if (is_file($file)) {
            expect(specState($file))->toBeIn(SPEC_STATES, "{$spec}/{$document}.md has no valid estado.");
        }
    }
})->with(specDirectories());

test('a spec document is never further along than the previous phase')
    ->todo('Pending a decision on SPEC-000 FND-R06.1 (see tasks.md T-09).');

test('criterion IDs are unique across all specs', function () {
    $criteria = collect(specDirectories())->keys()->flatMap(fn (string $spec) => criteriaOf($spec));

    expect($criteria->duplicates()->values()->all())->toBe([]);
});

test('the traceability matrix only points at tests that exist', function (string $spec) {
    $missing = collect(matrixReferences($spec))
        ->reject(function (array $reference) {
            $file = dirname(__DIR__, 2).'/'.$reference['file'];

            return is_file($file) && in_array($reference['test'], declaredTestNames($file), true);
        })
        ->map(fn (array $reference) => "{$reference['criterion']}: {$reference['file']} › {$reference['test']}")
        ->values()
        ->all();

    expect($missing)->toBe([]);
})->with(specDirectories());

test('an implemented spec traces every acceptance criterion to a test', function (string $spec) {
    if (specState(specsPath("{$spec}/requirements.md")) !== 'implementada') {
        $this->markTestSkipped("{$spec} is not implemented yet.");
    }

    $traced = collect(matrixReferences($spec))->pluck('criterion')->unique();
    $untraced = collect(criteriaOf($spec))
        ->filter(fn (string $criterion) => str_contains($criterion, '-R'))
        ->diff($traced)
        ->values()
        ->all();

    expect($untraced)->toBe([]);
})->with(specDirectories());
