<?php declare(strict_types=1);

namespace App\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The application imports the configuration of every top-level directory of src/
 * by file name pattern, and a file the pattern does not match is skipped without
 * an error. These tests turn such a file into a failure.
 */
final class ModuleConfigurationTest extends TestCase
{
    private const string IMPORTED = '#^(services\.yaml|routes\.yaml|packages/[^/]+\.yaml)$#';

    #[DataProvider('provideTopLevelDirectories')]
    public function test_a_top_level_directory_of_src_wires_its_own_services(string $directory): void
    {
        self::assertFileExists($directory.'/config/services.yaml');
    }

    #[DataProvider('provideTopLevelDirectories')]
    public function test_a_config_directory_holds_only_files_the_application_imports(string $directory): void
    {
        $config = $directory.'/config';
        self::assertDirectoryExists($config);

        $skipped = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($config, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            self::assertInstanceOf(\SplFileInfo::class, $file);
            $relative = substr($file->getPathname(), \strlen($config) + 1);
            if (1 !== preg_match(self::IMPORTED, $relative)) {
                $skipped[] = $relative;
            }
        }

        self::assertSame([], $skipped, 'These files are never loaded; see docs/03-architecture.md ("Module configuration").');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideTopLevelDirectories(): iterable
    {
        $directories = glob(\dirname(__DIR__, 2).'/src/*', \GLOB_ONLYDIR);

        foreach (false === $directories ? [] : $directories as $directory) {
            yield basename($directory) => [$directory];
        }
    }
}
