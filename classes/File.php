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

	protected $hash, $code, $path;

	public function __construct($path) {

		// If is remote file.
		if(preg_match('/((http|ftp|https):\/\/[\w\-_]+(\.[\w\-_]+)+([\w\-\.,@?^=%&amp;:\/~\+#]*[\w\-\@?^=%&amp;\/~\+#])?)/siU', $path) !== 0) {

			if(self::remoteIsCached($path)) {

				$data = self::getRemoteCache($path);

			} else {

				$ch = curl_init();

				curl_setopt($ch, CURLOPT_URL, $path);
				curl_setopt($ch, CURLOPT_HEADER, 0);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				
				$data = curl_exec($ch);
				$info = curl_getinfo($ch);

				if($info['http_code'] !== 200)
					throw new MinifyException('Failed to retrieve remote file.');

				curl_close($ch);

				$data = $data['content'];

				self::setRemoteCache($path, $data);

			}

		} else if (File::isValid($path) === true) {

			print 'Local'."\n";

		} else throw new MinifyException('Invalid file: '.basename($path));

	}

	protected static function remoteIsCached($path) {

		$cache = Cache::getInstance();
		$key   = self::getKeyFromPath($path);

		return $cache->exists($key);

	}

	protected static function getRemoteCache($path) {

		$cache = Cache::getInstance();
		$key   = self::getKeyFromPath($path);

		return $cache->get($key);

	}

	protected static function setRemoteCache($path, $data) {

		$cache = Cache::getInstance();
		$key   = self::getKeyFromPath($path);

		$cache->set($key, $data);

	}

	protected static function getKeyFromPath($path) {

		return md5($path);

	}

	protected static function isValid($path) {

		if (file_exists($path) === true) {

			if (is_readable($path) === true) {

				return true;

			} else return false;

		} else return false;

	}

}