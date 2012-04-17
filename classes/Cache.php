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

	public static function load($driver) {

		if (self::isValidDriver($driver)) {

			$name = 'Cache_'.ucfirst($driver);
			return new $name;

		} else {

			throw new MinifyException('Invalid cache driver: '.$driver);

		}

	}

	protected static function isValidDriver($name) {

		return file_exists(__DIR__.'/cache/'.ucfirst($name).'.php');

	}

	public function set() {}
	public function get() {}
	public function exists() {}

}