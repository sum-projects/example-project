<?php declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixerCustomFixers\Fixer\DeclareAfterOpeningTagFixer;
use PhpCsFixerCustomFixers\Fixers;

$finder = new Finder()
    ->in(__DIR__)
    ->ignoreDotFiles(false)
    ->exclude(['var', 'vendor', '.git', '.idea', '.claude'])
    // Flex-managed and generated files that are overwritten by tooling.
    ->notPath([
        'config/bundles.php',
        'config/reference.php',
    ])
;

return new Config()
    ->setRiskyAllowed(true)
    ->registerCustomFixers(new Fixers())
    ->setRules([
        '@PER-CS' => true,
        '@Symfony' => true,
        // These follow the PHP and PHPUnit versions required in composer.json,
        // so raising a version there also raises the syntax the fixer expects.
        '@autoPHPMigration' => true,
        '@autoPHPMigration:risky' => true,
        '@autoPHPUnitMigration:risky' => true,
        'declare_strict_types' => true,
        'ordered_imports' => true,
        'php_unit_method_casing' => ['case' => 'snake_case'],
        // Project style: "<?php declare(strict_types=1);" on the opening line.
        // The two stock rules would move the declaration back to its own line.
        'blank_line_after_opening_tag' => false,
        'linebreak_after_opening_tag' => false,
        DeclareAfterOpeningTagFixer::name() => true,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__.'/var/.php-cs-fixer.cache')
;
