<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in(dirname(__DIR__))
    ->exclude([
        'Build',
        'Documentation',
        'Documentation-GENERATED-temp',
        'public',
        'vendor',
        '.Build',
    ])
    ->notPath('ext_emconf.php');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setFinder($finder)
    ->setRules([
        '@DoctrineAnnotation' => true,
        '@PER-CS' => true,
        'array_indentation' => true,
        'array_syntax' => ['syntax' => 'short'],
        'cast_spaces' => true,
        'concat_space' => ['spacing' => 'one'],
        'declare_equal_normalize' => ['space' => 'none'],
        'declare_parentheses' => true,
        'declare_strict_types' => true,
        'dir_constant' => true,
        'function_to_constant' => ['functions' => ['get_called_class', 'get_class', 'php_sapi_name', 'phpversion', 'pi']],
        'type_declaration_spaces' => true,
        'global_namespace_import' => ['import_classes' => false, 'import_constants' => false, 'import_functions' => false],
        'list_syntax' => ['syntax' => 'short'],
        'no_alias_functions' => true,
        'no_blank_lines_after_phpdoc' => true,
        'no_empty_phpdoc' => true,
        'no_empty_statement' => true,
        'no_extra_blank_lines' => true,
        'no_leading_namespace_whitespace' => true,
        'no_null_property_initialization' => true,
        'no_short_bool_cast' => true,
        'no_singleline_whitespace_before_semicolons' => true,
        'no_superfluous_elseif' => true,
        'no_trailing_comma_in_singleline' => true,
        'no_unneeded_control_parentheses' => true,
        'no_unused_imports' => true,
        'no_useless_else' => true,
        'no_useless_nullsafe_operator' => true,
        'ordered_imports' => ['imports_order' => ['class', 'function', 'const'], 'sort_algorithm' => 'alpha'],
        'php_unit_construct' => true,
        'php_unit_mock_short_will_return' => true,
        'php_unit_test_case_static_method_calls' => ['call_type' => 'self'],
        'phpdoc_no_access' => true,
        'phpdoc_no_empty_return' => true,
        'phpdoc_no_package' => true,
        'phpdoc_scalar' => true,
        'phpdoc_trim' => true,
        'phpdoc_types' => true,
        'phpdoc_types_order' => ['null_adjustment' => 'always_last', 'sort_algorithm' => 'none'],
        'return_type_declaration' => true,
        'single_quote' => true,
        'single_line_comment_style' => ['comment_types' => ['hash']],
        'single_trait_insert_per_statement' => true,
        'whitespace_after_comma_in_array' => true,
    ]);
