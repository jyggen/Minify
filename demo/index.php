<?php
require_once '../bootstrap.php';

try {

	$javascript = new Minify\Minify;

	$javascript->add('https://ajax.googleapis.com/ajax/libs/chrome-frame/1.0.2/CFInstall.js');
	$javascript->add('https://ajax.googleapis.com/ajax/libs/dojo/1.6.1/dojo/dojo.xd.js.uncompressed.js');
	$javascript->add('https://ajax.googleapis.com/ajax/libs/ext-core/3.1.0/ext-core-debug.js');
	$javascript->add('jquery.js');
	$javascript->run();

	$stylesheet = new Minify\Minify;

} catch (Minify\MinifyException $e) {

	die($e->getMessage()."\n");

}