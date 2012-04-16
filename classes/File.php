<?php
/**
 * @package    Minify
 * @author     Jonas Stendahl
 * @license    MIT License
 * @copyright  2012 Jonas Stendahl
 * @link       http://www.jyggen.com
 */

namespace Minify;

class File
{

	public static function isValid($path) {

		if (file_exists($path) === true) {

			if (is_readable($path) === true) {

				return true;

			} else return false;

		} else return false;

	}

}