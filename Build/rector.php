<?php
declare(strict_types = 1);

use Rector\Config\RectorConfig;
use Rector\Core\ValueObject\PhpVersion;
use Rector\DeadCode\Rector\Cast\RecastingRemovalRector;
use Rector\DeadCode\Rector\ClassConst\RemoveUnusedPrivateClassConstantRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodParameterRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPromotedPropertyRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\DeadCode\Rector\Property\RemoveUselessVarTagRector;
use Rector\Php73\Rector\ConstFetch\SensitiveConstantNameRector;
use Rector\Php74\Rector\LNumber\AddLiteralSeparatorToNumberRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\PostRector\Rector\NameImportingPostRector;
use Rector\Renaming\Rector\Name\RenameClassRector;
use Rector\Set\ValueObject\SetList;
use Ssch\TYPO3Rector\Configuration\Typo3Option;
use Ssch\TYPO3Rector\Rector\General\ConvertImplicitVariablesToExplicitGlobalsRector;
use Ssch\TYPO3Rector\Rector\General\ExtEmConfRector;
use Ssch\TYPO3Rector\Rector\v10\v0\ChangeDefaultCachingFrameworkNamesRector;
use Ssch\TYPO3Rector\Rector\v9\v0\InjectAnnotationRector;
use Ssch\TYPO3Rector\Rector\v9\v0\SubstituteConstantParsetimeStartRector;
use Ssch\TYPO3Rector\Set\Typo3LevelSetList;
use Ssch\TYPO3Rector\Set\Typo3SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->parallel();
    $rectorConfig->paths(
        [
            getcwd(),
        ]
    );
    $rectorConfig->sets([
        Typo3SetList::DATABASE_TO_DBAL,
        Typo3LevelSetList::UP_TO_TYPO3_12,
        PHPUnitSetList::PHPUNIT_90,
        PHPUnitSetList::PHPUNIT_91,
        SetList::PHP_81,
        SetList::DEAD_CODE,
    ]);

    // In order to have a better analysis from phpstan we teach it here some more things
    $rectorConfig->phpstanConfig(Typo3Option::PHPSTAN_FOR_RECTOR_PATH);
    // FQN classes are not imported by default. If you don't do it manually after every Rector run, enable it by:
    $rectorConfig->importNames();
    // this will not import root namespace classes, like \DateTime or \Exception
    $rectorConfig->importShortClasses();

    // Define your target version which you want to support
    $rectorConfig->phpVersion(PhpVersion::PHP_81);
    // If you use the option --config change __DIR__ to getcwd()
    $rectorConfig->skip([
        // @see https://github.com/sabbelasichon/typo3-rector/issues/2536
        getcwd() . '/**/Configuration/ExtensionBuilder/*',
        // We skip those directories on purpose as there might be node_modules or similar
        // that include typescript which would result in false positive processing
        getcwd() . '/**/Resources/**/node_modules/*',
        getcwd() . '/**/Resources/**/NodeModules/*',
        getcwd() . '/**/Resources/**/BowerComponents/*',
        getcwd() . '/**/Resources/**/bower_components/*',
        getcwd() . '/**/Resources/**/build/*',
        getcwd() . '/vendor/*',
        getcwd() . '/Build/*',
        getcwd() . '/public/*',
        getcwd() . '/.github/*',
        getcwd() . '/.Build/*',
    ]);

    // This is used by the class \Ssch\TYPO3Rector\Rector\PostRector\FullQualifiedNamePostRector to force FQN in this paths and files
    //    $parameters->set(Typo3Option::PATHS_FULL_QUALIFIED_NAMESPACES, [
    //        # If you are targeting TYPO3 Version 11 use can now use Short namespace
    //        # @see namespace https://docs.typo3.org/m/typo3/reference-coreapi/master/en-us/ExtensionArchitecture/ConfigurationFiles/Index.html
    //        'ext_localconf.php',
    //        'ext_tables.php',
    //        'ClassAliasMap.php',
    //        __DIR__ . '/**/Configuration/*.php',
    //        __DIR__ . '/**/Configuration/**/*.php',
    //    ]);

    // If you have trouble that rector cannot run because some TYPO3 constants are not defined add an additional constants file
    // @see https://github.com/sabbelasichon/typo3-rector/blob/master/typo3.constants.php
    // @see https://github.com/rectorphp/rector/blob/main/docs/static_reflection_and_autoload.md#include-files
    // $parameters->set(Option::BOOTSTRAP_FILES, [
    //    __DIR__ . '/typo3.constants.php'
    // ]);

    // Rewrite your extbase persistence class mapping from typoscript into php according to official docs.
    // This processor will create a summarized file with all of the typoscript rewrites combined into a single file.
    // The filename can be passed as argument, "Configuration_Extbase_Persistence_Classes.php" is default.
    // $rectorConfig->ruleWithConfiguration(ExtbasePersistenceTypoScriptRector::class, []);

    // Add some general TYPO3 rules
    $rectorConfig->rule(ConvertImplicitVariablesToExplicitGlobalsRector::class);
    $rectorConfig->ruleWithConfiguration(ExtEmConfRector::class, [
        ExtEmConfRector::ADDITIONAL_VALUES_TO_BE_REMOVED => [],
    ]);
    //    $rectorConfig->ruleWithConfiguration(ExtensionComposerRector::class, [
    //        ExtensionComposerRector::TYPO3_VERSION_CONSTRAINT => ''
    //    ]);
};
