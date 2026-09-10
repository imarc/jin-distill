<?php

require __DIR__ . "/../vendor/autoload.php";

use JinDistill\Encoder;

$csv_config = [
	'excludes' => [
		'form.name'
	],
	'extensions' => false,
	'cloak' => [
		'form.fields'
	],
	'mappings' => [
		'true' => 'required',
		'false' => 'hide',
		'null' => 'display'
	]
];

$encoder = new Encoder(new JinDistill\Formats\CsvFormat(...$csv_config), new JinDistill\Output\FileOutput(__DIR__ . '/examples/default.csv'));

$config = include('examples/default.php');

$encoder->encode($config);

