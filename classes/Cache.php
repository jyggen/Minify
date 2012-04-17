<?php
/**
 * @package    Minify
 * @author     Jonas Stendahl
 * @license    MIT License
 * @copyright  2012 Jonas Stendahl
 * @link       http://www.jyggen.com
 */

namespace Minify;

class Cache
{

	protected static $instance;

	public static function load($driver) {

		$name = 'Minify\\Cache_'.ucfirst($driver);
		self::$instance = new $name;

	}

	public static function getInstance() {

		if(self::$instance !== null) {

			return self::$instance;

		} else throw new MinifyException('No instance of Cache available.');

	}

	public function set() {}
	public function get() {}
	public function exists() {}

}