<?php
require_once 'simpletest/autorun.php';
require_once '../Minify.class.php';

class Min extends Minify {
	
	public static function validateDir($dir) {
		
		parent::validateDir($dir);
		
	}
	
}

class TestOfMinify extends UnitTestCase {

	function testValidateDir() {
		
		$this->ignoreException(Min::validateDir('../tests'));
		$this->expectException(Min::validateDir('tests'));
		$this->expectException(Min::validateDir('../tests'));
		
	}

}