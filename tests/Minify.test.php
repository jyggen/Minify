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
		
		$return = Min::validateDir('tests');
		$this->assertTrue($return);

		$return = Min::validateDir('tests');
		$this->assertTrue($return);

		chmod('tests', 0000);
		$this->expectException();
		Min::validateDir('tests');

		rmdir('tests');
		
	}

}