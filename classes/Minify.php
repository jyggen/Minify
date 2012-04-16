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

	public function add($path) {

		if (File::isValid($path) === true) {

			$this->files[] = new File($path);

		} else {

			throw new MinifyException();

		}

	}

}