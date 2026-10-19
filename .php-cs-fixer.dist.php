<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
	->in([__DIR__ . '/appinfo', __DIR__ . '/lib', __DIR__ . '/templates', __DIR__ . '/tests']);

return (new PhpCsFixer\Config())
	->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
	->setRiskyAllowed(true)
	->setIndent("\t")
	->setRules([
		'@PSR12' => true,
		'braces_position' => ['classes_opening_brace' => 'same_line', 'functions_opening_brace' => 'same_line'],
		'declare_strict_types' => true,
		'no_unused_imports' => true,
		'ordered_imports' => true,
		'array_syntax' => ['syntax' => 'short'],
	])
	->setFinder($finder);
