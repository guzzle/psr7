<?php

$config = (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PHP71Migration:risky' => true,
        '@PHPUnit75Migration:risky' => true,
        '@Symfony' => true,
        'global_namespace_import' => false,
        'no_superfluous_phpdoc_tags' => [
            'allow_mixed' => true,
        ],
        'phpdoc_annotation_without_dot' => false,
        'phpdoc_summary' => false,
        'phpdoc_to_comment' => false,
        'single_line_throw' => false,
        'yoda_style' => false,
        'nullable_type_declaration_for_default_null_value' => true,
    ])
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in(__DIR__.'/src')
            ->in(__DIR__.'/tests')
            ->name('*.php')
    )
;

return $config;
