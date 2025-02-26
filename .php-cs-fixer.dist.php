<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR2' => true,
        '@Symfony' => true,
        'align_multiline_comment' => ['comment_type' => 'phpdocs_like'],
        'array_indentation' => true,
        'array_syntax' => ['syntax' => 'short'],
        'blank_line_before_statement' => false,
        'concat_space' => ['spacing' => 'one'],
        'no_useless_return' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'php_unit_method_casing' => true,
        'yoda_style' => ['equal' => false, 'identical' => false],
    ])
    ->setFinder($finder);
