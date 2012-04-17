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

		// If is remote file.
		if(preg_match('/((http|ftp|https):\/\/[\w\-_]+(\.[\w\-_]+)+([\w\-\.,@?^=%&amp;:\/~\+#]*[\w\-\@?^=%&amp;\/~\+#])?)/siU', $path) !== 0) {

			$cache = md5($path).'.cache';

		} else {

			// If file exists.
			if (File::isValid($path) === true) {

				$this->files[] = new File($path);

			} else {

				throw new MinifyException('Invalid file: '.basename($path));

			}

		}

	}

	public static function loadConfig() { }

}