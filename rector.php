<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Symfony\Set\SymfonySetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withCache(__DIR__.'/var/rector')
    // Without arguments both follow composer.json: the PHP sets up to the required
    // PHP version, and the upgrade sets of every supported package that is installed
    // (Symfony and PHPUnit today, Doctrine once it arrives) up to its installed version.
    ->withPhpSets()
    ->withComposerBased()
    ->withSets([
        SymfonySetList::SYMFONY_CODE_QUALITY,
    ])
;
