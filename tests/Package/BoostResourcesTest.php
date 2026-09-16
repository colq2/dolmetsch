<?php

/**
 * Laravel Boost discovers a package's guidelines and skills by exact path, with no
 * error if they are missing — so a rename would silently stop them shipping. These
 * tests pin the paths and the frontmatter Boost requires.
 */
function packagePath(string $path): string
{
    return dirname(__DIR__, 2).'/'.$path;
}

it('ships a boost guideline at the path boost discovers', function (): void {
    $guideline = packagePath('resources/boost/guidelines/core.blade.php');

    expect($guideline)->toBeReadableFile()
        ->and(file_get_contents($guideline))->toContain('Dolmetsch');
});

it('ships a boost skill with the required frontmatter', function (): void {
    $skill = packagePath('resources/boost/skills/dolmetsch-translations/SKILL.md');

    expect($skill)->toBeReadableFile();

    $contents = file_get_contents($skill);

    expect($contents)->toStartWith('---')
        ->and($contents)->toContain('name: dolmetsch-translations')
        ->and($contents)->toMatch('/^description: .+$/m');
});

it('does not export-ignore the boost resources from the composer dist', function (): void {
    $gitattributes = file_get_contents(packagePath('.gitattributes'));

    expect($gitattributes)->not->toContain('/resources');
});
