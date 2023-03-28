<?php
$finder = PhpCsFixer\Finder::create()
    //->exclude('somedir')
    //->notPath('src/Symfony/Component/Translation/Tests/fixtures/resources.php'
    ->in(__DIR__)
;

return PhpCsFixer\Config::create()
    ->setRules(array(
        '@PSR2' => true,
        'binary_operator_spaces' => true,
        'no_multiline_whitespace_around_double_arrow' => true,
        'concat_space' => array('spacing' => 'none'),
        'strict_param' => false,
        'array_syntax' => array('syntax' => 'long'),
        'array_indentation' => true,
        'braces' => array(
            'allow_single_line_closure' => true,
            'position_after_functions_and_oop_constructs' => 'same'),
        'phpdoc_add_missing_param_annotation' => array('only_untyped' => false)
    ))
    ->setFinder($finder)
;
