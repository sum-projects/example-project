<?php declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/*
 * Layer and module rules. Source of truth: docs/03-architecture.md ("Dependency rules").
 *
 * Every directory in src/ other than Shared and Delivery is a module and gets the
 * same layers and rules, so a new module is covered without touching this file.
 */
return static function (ContainerConfigurator $container): void {
    $modules = array_values(array_diff(
        array_map(basename(...), glob(__DIR__.'/src/*', \GLOB_ONLYDIR) ?: []),
        ['Shared', 'Delivery'],
    ));

    // A namespace separator inside a regular expression.
    $sep = '\\\\';
    // The regular expression for "every class in this namespace"; a segment may be a pattern.
    $in = static fn (string ...$segments): string => '#^'.implode($sep, $segments).$sep.'#';
    $classes = static fn (string $regex): array => ['type' => 'classNameRegex', 'value' => $regex];
    $layer = static fn (string $name, string $regex): array => ['name' => $name, 'collectors' => [$classes($regex)]];
    $each = static fn (array $names, string $suffix): array => array_map(static fn (string $name): string => $name.$suffix, $names);

    $sharedKernel = ['SharedDomain', 'SharedApplication'];
    // Vendor code, for the layers that may use it at all.
    $vendor = ['Vendor', 'ValidatorConstraints'];

    $layers = [
        $layer('SharedDomain', $in('App', 'Shared', 'Domain')),
        $layer('SharedApplication', $in('App', 'Shared', 'Application')),
        $layer('SharedInfrastructure', $in('App', 'Shared', 'Infrastructure')),
        $layer('SharedUserInterface', $in('App', 'Shared', 'UserInterface')),
        $layer('Delivery', $in('App', 'Delivery')),
        // Doctrine is persistence, which only Infrastructure may touch. Symfony's
        // bridge to it counts as Doctrine.
        $layer('Doctrine', "#^(Doctrine|Symfony{$sep}Bridge{$sep}Doctrine){$sep}#"),
        // The one vendor namespace Contracts and Application may use: constraints
        // declared as attributes on commands and queries (ADR 0009).
        $layer('ValidatorConstraints', $in('Symfony', 'Component', 'Validator', 'Constraints')),
        // Every other class-like outside App\ that is not a PHP internal class. PHP
        // internals belong to no layer, so they stay allowed everywhere.
        [
            'name' => 'Vendor',
            'collectors' => [[
                'type' => 'bool',
                'must' => [$classes("#^(?!App{$sep}|Doctrine{$sep}|Symfony{$sep}Bridge{$sep}Doctrine{$sep}|Symfony{$sep}Component{$sep}Validator{$sep}Constraints{$sep})#")],
                'must_not' => [['type' => 'php_internal', 'value' => '.*']],
            ]],
        ],
    ];

    $rules = [
        'SharedDomain' => [],
        'SharedApplication' => ['SharedDomain'],
        'SharedInfrastructure' => [...$sharedKernel, ...$vendor, 'Doctrine'],
        'SharedUserInterface' => [...$sharedKernel, 'SharedInfrastructure', ...$vendor],
        // Of a module, the delivery layer sees only the use cases it exposes.
        'Delivery' => [...$each($modules, 'ContractsUseCase'), ...$sharedKernel, ...$vendor],
    ];

    foreach ($modules as $module) {
        $others = array_values(array_diff($modules, [$module]));
        $ownContracts = [$module.'ContractsUseCase', $module.'ContractsFacade', $module.'ContractsEvent'];
        $application = [$module.'Domain', ...$ownContracts, ...$sharedKernel, 'ValidatorConstraints'];

        array_push(
            $layers,
            // Commands and queries exposed to the delivery layer, with their results.
            $layer($module.'ContractsUseCase', $in('App', $module, 'Contracts', '(Command|Query)')),
            // Integration events, consumed by other modules' event handlers.
            $layer($module.'ContractsEvent', $in('App', $module, 'Contracts', 'Event')),
            // The rest of Contracts: the facade interface and its DTOs, consumed by
            // other modules' ACL adapters.
            $layer($module.'ContractsFacade', "#^App{$sep}{$module}{$sep}Contracts{$sep}(?!(Command|Query|Event){$sep})#"),
            $layer($module.'Domain', $in('App', $module, 'Domain')),
            $layer($module.'ApplicationEventHandler', $in('App', $module, 'Application', 'EventHandler')),
            $layer($module.'Application', "#^App{$sep}{$module}{$sep}Application{$sep}(?!EventHandler{$sep})#"),
            $layer($module.'InfrastructureAcl', $in('App', $module, 'Infrastructure', 'Acl')),
            $layer($module.'Infrastructure', "#^App{$sep}{$module}{$sep}Infrastructure{$sep}(?!Acl{$sep})#"),
            $layer($module.'UserInterface', $in('App', $module, 'UserInterface')),
        );

        $rules[$module.'ContractsUseCase'] = ['ValidatorConstraints'];
        $rules[$module.'ContractsEvent'] = [];
        $rules[$module.'ContractsFacade'] = [];
        $rules[$module.'Domain'] = ['SharedDomain'];
        $rules[$module.'Application'] = $application;
        $rules[$module.'ApplicationEventHandler'] = [
            ...$application,
            $module.'Application',
            // Another module is heard only through its integration events, in an event handler...
            ...$each($others, 'ContractsEvent'),
        ];
        $rules[$module.'Infrastructure'] = [
            $module.'Domain',
            $module.'Application',
            ...$ownContracts,
            ...$sharedKernel,
            'SharedInfrastructure',
            ...$vendor,
            'Doctrine',
        ];
        $rules[$module.'InfrastructureAcl'] = [
            $module.'Domain',
            $module.'Application',
            ...$sharedKernel,
            'SharedInfrastructure',
            ...$vendor,
            // ...and called only through its facade, from an adapter in Infrastructure\Acl.
            ...$each($others, 'ContractsFacade'),
        ];
        $rules[$module.'UserInterface'] = [
            $module.'Application',
            $module.'ContractsUseCase',
            ...$sharedKernel,
            'SharedInfrastructure',
            ...$vendor,
        ];
    }

    // Anything under App\ that no layer above claims: a class outside the layer
    // directories of a module, of Shared or of Delivery. It may depend on nothing
    // and nothing may depend on it, so misplaced code fails the build.
    $claimed = [
        $classes("#^App{$sep}Kernel$#"),
        $classes($in('App', 'Delivery')),
        $classes($in('App', 'Shared', '(Domain|Application|Infrastructure|UserInterface)')),
    ];
    if ([] !== $modules) {
        $claimed[] = $classes($in('App', '('.implode('|', $modules).')', '(Contracts|Domain|Application|Infrastructure|UserInterface)'));
    }
    $layers[] = [
        'name' => 'Unassigned',
        'collectors' => [['type' => 'bool', 'must' => [$classes($in('App'))], 'must_not' => $claimed]],
    ];
    $rules['Unassigned'] = [];

    $container->extension('deptrac', [
        'paths' => ['./src'],
        'cache_file' => './var/.deptrac.cache',
        'layers' => $layers,
        'ruleset' => $rules,
    ]);
};
