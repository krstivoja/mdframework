<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/lib')
    ->in(__DIR__ . '/../tests')
    ->in(__DIR__ . '/../public')
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12'                             => true,
        'declare_strict_types'               => true,
        'strict_param'                       => true,
        'array_syntax'                       => ['syntax' => 'short'],
        'ordered_imports'                    => ['sort_algorithm' => 'alpha'],
        'no_unused_imports'                  => true,
        'single_quote'                       => true,
        'trailing_comma_in_multiline'        => true,
        'no_trailing_whitespace'             => true,
        'blank_line_after_namespace'         => true,
        'binary_operator_spaces'             => ['default' => 'align_single_space_minimal'],
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder);
