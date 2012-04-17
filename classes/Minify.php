<?php
/**
 * @package    Minify
 * @author     Jonas Stendahl
 * @license    MIT License
 * @copyright  2012 Jonas Stendahl
 * @link       http://www.jyggen.com
 */

namespace Minify;

class MinifyException extends \Exception {}

class Minify
{

	protected $files = array();
	protected $cache;

	public function __construct() {

		$this->cache = Cache::load('file');

	}

	public function add($path) {

		if(is_array($path)) {

			foreach($path as $file) {

				$this->files[] = new File($file);

			}

		} else {

			$this->files[] = new File($path);

		}

	}

	public function run() {}

	public static function autoloader($class) {

		$file = __DIR__.'/'.str_replace('_', DIRECTORY_SEPARATOR, substr($class, 7)).'.php';

		if(file_exists($file) === true && is_readable($file) === true) {

			include_once $file;
			return true;

		} else {

			print $file;
			throw new MinifyException('Invalid driver: '.$class);

		}

	}

	public static function loadConfig() { }

}