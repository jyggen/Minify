<?php
/**
* OO cURL Class
* Object oriented wrapper for the cURL library.
* @author David Hopkins (semlabs.co.uk)
* @version 0.3
*/
class CURL
{

	public $sessions 				=	array();
	public $retry					=	0;

	/**
	* Adds a cURL session to stack
	* @param $url string, session's URL
	* @param $opts array, optional array of cURL options and values
	*/
	public function addSession( $url, $opts = false )
	{
		$this->sessions[] = curl_init( $url );
		if( $opts != false )
		{
			$key = count( $this->sessions ) - 1;
			$this->setOpts( $opts, $key );
		}
	}

	/**
	* Sets an option to a cURL session
	* @param $option constant, cURL option
	* @param $value mixed, value of option
	* @param $key int, session key to set option for
	*/
	public function setOpt( $option, $value, $key = 0 )
	{
		curl_setopt( $this->sessions[$key], $option, $value );
	}

	/**
	* Sets an array of options to a cURL session
	* @param $options array, array of cURL options and values
	* @param $key int, session key to set option for
	*/
	public function setOpts( $options, $key = 0 )
	{
		curl_setopt_array( $this->sessions[$key], $options );
	}

	/**
	* Executes as cURL session
	* @param $key int, optional argument if you only want to execute one session
	*/
	public function exec( $key = false )
	{
		$no = count( $this->sessions );

		if( $no == 1 )
			$res = $this->execSingle();
		elseif( $no > 1 ) {
			if( $key === false )
				$res = $this->execMulti();
			else
				$res = $this->execSingle( $key );
		}

		if( $res )
			return $res;
	}

	/**
	* Executes a single cURL session
	* @param $key int, id of session to execute
	* @return array of content if CURLOPT_RETURNTRANSFER is set
	*/
	public function execSingle( $key = 0 )
	{
		if( $this->retry > 0 )
		{
			$retry = $this->retry;
			$code = 0;
			while( $retry >= 0 && ( $code[0] == 0 || $code[0] >= 400 ) )
			{
				$res = curl_exec( $this->sessions[$key] );
				$code = $this->info( $key, CURLINFO_HTTP_CODE );

				$retry--;
			}
		}
		else
			$res = curl_exec( $this->sessions[$key] );

		return $res;
	}

	/**
	* Executes a stack of sessions
	* @return array of content if CURLOPT_RETURNTRANSFER is set
	*/
	public function execMulti()
	{
		$mh = curl_multi_init();

		#Add all sessions to multi handle
		foreach ( $this->sessions as $i => $url )
			curl_multi_add_handle( $mh, $this->sessions[$i] );

		do
			$mrc = curl_multi_exec( $mh, $active );
		while ( $mrc == CURLM_CALL_MULTI_PERFORM );

		while ( $active && $mrc == CURLM_OK )
		{
			if ( curl_multi_select( $mh ) != -1 )
			{
				do
					$mrc = curl_multi_exec( $mh, $active );
				while ( $mrc == CURLM_CALL_MULTI_PERFORM );
			}
		}

		if ( $mrc != CURLM_OK )
			echo "Curl multi read error $mrc\n";

		#Get content foreach session, retry if applied
		foreach ( $this->sessions as $i => $url )
		{
			$code = $this->info( $i, CURLINFO_HTTP_CODE );
			if( $code[0] > 0 && $code[0] < 400 )
				$res[] = curl_multi_getcontent( $this->sessions[$i] );
			else
			{
				if( $this->retry > 0 )
				{
					$retry = $this->retry;
					$this->retry -= 1;
					$eRes = $this->execSingle( $i );

					if( $eRes )
						$res[] = $eRes;
					else
						$res[] = false;

					$this->retry = $retry;
					echo '1';
				}
				else
					$res[] = false;
			}

			curl_multi_remove_handle( $mh, $this->sessions[$i] );
		}

		curl_multi_close( $mh );

		return $res;
	}

	/**
	* Closes cURL sessions
	* @param $key int, optional session to close
	*/
	public function close( $key = false )
	{
		if( $key === false )
		{
			foreach( $this->sessions as $session )
				curl_close( $session );
		}
		else
			curl_close( $this->sessions[$key] );
	}

	/**
	* Remove all cURL sessions
	*/
	public function clear()
	{
		foreach( $this->sessions as $session )
			curl_close( $session );
		unset( $this->sessions );
	}

	/**
	* Returns an array of session information
	* @param $key int, optional session key to return info on
	* @param $opt constant, optional option to return
	*/
	public function info( $key = false, $opt = false )
	{
		if( $key === false )
		{
			foreach( $this->sessions as $key => $session )
			{
				if( $opt )
					$info[] = curl_getinfo( $this->sessions[$key], $opt );
				else
					$info[] = curl_getinfo( $this->sessions[$key] );
			}
		}
		else
		{
			if( $opt )
				$info[] = curl_getinfo( $this->sessions[$key], $opt );
			else
				$info[] = curl_getinfo( $this->sessions[$key] );
		}

		return $info;
	}

	/**
	* Returns an array of errors
	* @param $key int, optional session key to retun error on
	* @return array of error messages
	*/
	public function error( $key = false )
	{
		if( $key === false )
		{
			foreach( $this->sessions as $session )
				$errors[] = curl_error( $session );
		}
		else
			$errors[] = curl_error( $this->sessions[$key] );

		return $errors;
	}

	/**
	* Returns an array of session error numbers
	* @param $key int, optional session key to retun error on
	* @return array of error codes
	*/
	public function errorNo( $key = false )
	{
		if( $key === false )
		{
			foreach( $this->sessions as $session )
				$errors[] = curl_errno( $session );
		}
		else
			$errors[] = curl_errno( $this->sessions[$key] );

		return $errors;
	}

}

/**
* CURL Request class
* CURL shortcuts to request URLIs
* @author David Hopkins (semlabs.co.uk)
* @version 0.1
*/
class CURLRequest extends CURL
{

	public $opts = array();
	public $threads = 50;

	/**
	* Request a URL
	* @param $url string, URL to request
	* @param $ad_opts array, cURL options to use for the request
	* @param $post array, associative array of post data
	* @return array, array containing result of the request and request info
	*/
	public function get( $url, array $ad_opts = array(), array $post = array() )
	{
		$start = microtime(true);
		$opts = $this->opts;
		if( $ad_opts )
		{
			foreach( $ad_opts as $opt => $val )
				$opts[$opt] = $val;
		}
		if( $post )
		{
			$opts[CURLOPT_POST] = true;
			$opts[CURLOPT_POSTFIELDS] = http_build_query( $post );
		}

		$this->addSession( $url, $opts );

		$content = $this->exec();
		$info    = $this->info();
		$info    = $info[0];
		$url     = parse_url($info['url']);

		$this->clear();

		/* did curl successfully fetch the

			/* running through cli? print some information! */
			if(defined('STDIN'))
				printf("\n" . ' %s: Fetched %s in %s.' . "\n\n", $this->color('Success', 'GREEN'), $this->color($url['host']), $this->color($this->formatSeconds(microtime(true)-$start)));

			return array( 'content' => $content, 'info' => $info );


	}

	/* Request an array of URLs */
	public function getThreaded($urls, $opts = array(), $verbose=TRUE) {

		/* variables */
		$result   = array();
		$current  = 0;
		$total    = count($urls);
		$start    = microtime(true);
		$threads  = round(sqrt($total));
		$download = 0;
		$speed    = array();

		/* if threads is higher than our default value, use the default */
		$this->threads = ($threads < $this->threads) ? $threads : $this->threads;

		/* if we successfully build our url stacks */
		if($stacks = $this->buildStacks($urls)) {

			/* running through cli? print some information! */
			if(defined('STDIN') && $verbose)
				printf("\n" . 'Fetching %s URLs with %s threads:' . "\n\n", $this->color($total), $this->color($this->threads));

			/* loop trough our stacks */
			foreach($stacks as $stack) {

				/* add each url in the stack to the queue */
				foreach($stack as $url)
					$this->addSession($url, $opts);

				/* variables */
				$content  = $this->exec();
				$content  = (is_array($content)) ? $content : array($content);
				$info     = $this->info();
				$no       = 0;

				/* clear the queue */
				$this->clear();

				/* loop trough our stack */
				foreach($stack as $url_id => $url) {

					$url = parse_url($info[$no]['url']);
					$current++;

					/* did curl successfully fetch the url? */
					if($info[$no]['http_code'] == 200) {

						if(defined('STDIN') && $verbose)
							printf('  [%s][%s] %s' . "\n", $this->percent($total, $current), $this->color($info[$no]['http_code'], 'LIGHT_GREEN'), $info[$no]['url']);

						/* add stuff to return */
						$result[$url_id] = array(
							'url'     => $url,
							'content' => $content[$no],
							'info'    => $info[$no]
						);

					} else {

						if(defined('STDIN') && $verbose)
							printf('  [%s][%s] %s' . "\n", $this->percent($total, $current), $this->color($info[$no]['http_code'], 'LIGHT_RED'), $info[$no]['url'], 'LIGHT_RED');

					}

					/* save the time for eta calculation */
					$download += $info[$no]['size_download'];

					$no++;

				}

				/* calculate eta */
				$right_now = microtime(true) - $start;
				$urls_left = $total-$current;
				$per_url   = $right_now/$current;
				$eta_total = $per_url*$urls_left;

				/* this isn't the last stack and we're running through cli? print some information! */
				if($current != $total && defined('STDIN') && $verbose)
					printf("\n" . '  - %s remaining -' . "\n\n", $this->formatSeconds($eta_total));

			}
		}

		// Running through cli? print some information!
		if (defined('STDIN') && $verbose) {

			$total_time = microtime(true) - $start;
			$label      = $this->color('Success', 'GREEN');
			$per_url    = $this->color('~' . $this->formatSeconds($per_url));
			$total      = $this->color($total);
			$total_time = $this->color($this->formatSeconds($total_time));
			$download   = $this->color($this->mksize($download));

			printf("\n" . '%s: %s URLs (%s) fetched in %s, with %s per URL.' . "\n\n", $label, $total, $download, $total_time, $per_url);

		}

		// Return everything.
		return $result;

	}

	/**
	 * Build requst stacks
	 * @param $urls array, an array of URLs to create stacks for
	 * @return array, URL organised into stacks
	 */
	public function buildStacks($urls=array())
	{

		$stacks = array();
		$i      = 0;
		$no     = 1;

		foreach ($urls as $key => $url) {

			$stacks[$i][$key] = preg_replace('/&amp;/', '&', $url);

			if (preg_match('/^[a-z]{3,}:\/\//i', $url) === 0) {

				trigger_error($url.' does not use a valid protocol', E_USER_WARNING);

			}

			if (($no % $this->threads) === 0) {

				$i++;

			}

			$no++;

		}

		return $stacks;

	}

	protected function percent($total, $current)
	{

		$percent = round((($current / $total) * 100), 2);

		if ($percent !== 100) {

			$percent = preg_split('/([,])/siU', $percent);

			if (strlen($percent[0]) !== 2) {

				$return = '0'.$percent[0];

			} else {

				$return = $percent[0];

			}

			$return .= ',';

			if (isset($percent[1]) === true) {

				if (strlen($percent[1]) !== 2) {

					$return .= $percent[1].'0';

				} else {

					$return .= $percent[1];

				}

			} else {

				$return .= '00';

			}

		} else {

			$return = $percent.',0';

		}//end if

		$return = sprintf('%02.2f', $return);

		if (substr($return, 0, 3) === 100) {

			$return = substr($return, 0, -1);

		}


		if (strlen($return) === 4) {

			$return = '0'.$return;

		}

		$return .= '%';

		if ($return >= 0 && $return <= 25) {

			$return = $this->color($return, 'LIGHT_RED');

		} else if ($return > 25 && $return < 75) {

			$return = $this->color($return, 'YELLOW');

		} else if ($return >= 75 && $return < 100) {

			$return = $this->color($return, 'LIGHT_GREEN');

		} else {

			$return = $this->color($return, 'GREEN');

		}

		return $return;


	}

	protected function formatSeconds($secs)
	{

		$secs = round($secs);

		if ($secs < 60) {

			return $this->color(round($secs, 3)).' sec(s)';

        }

        $mins  = 0;
        $hours = 0;
        $days  = 0;
        $weeks = 0;

        if ($secs >= 60) {

            $mins = ($secs / 60);
            $secs = ($secs % 60);

        }
        if ($mins >= 60) {

            $hours = ($mins / 60);
            $mins  = ($mins % 60);

        }

        if ($hours >= 24) {

            $days  = ($hours / 24);
            $hours = ($hours % 60);

		}

		if ($days >= 7) {

            $weeks = ($days / 7);
            $days  = ($days % 7);

        }

        $result = '';

		if ($weeks !== 0) {

			$result .= $this->color($weeks).' week(s) ';

		}

		if ($days !== 0) {

			$result .= $this->color($days).' day(s) ';

		}

		if ($hours !== 0) {

			$result .= $this->color($hours).' hour(s) ';

		}

        if ($mins !== 0) {

			$result .= $this->color($mins).' min(s) ';

		}

        if ($secs !== 0) {

			$result .= $this->color($secs).' sec(s)';

		}

		$result = rtrim($result);

		return $result;

	}

	protected function color($text, $color='LIGHT_BLUE', $back=true)
	{

		$_colors = array(
			        'LIGHT_RED'   => '[1;31m',
			        'LIGHT_GREEN' => '[1;32m',
			        'YELLOW'      => '[1;33m',
			        'LIGHT_BLUE'  => '[1;34m',
			        'MAGENTA'     => '[1;35m',
			        'LIGHT_CYAN'  => '[1;36m',
			        'WHITE'       => '[1;37m',
			        'NORMAL'      => '[0m',
			        'BLACK'       => '[0;30m',
			        'RED'         => '[0;31m',
			        'GREEN'       => '[0;32m',
			        'BROWN'       => '[0;33m',
			        'BLUE'        => '[0;34m',
			        'CYAN'        => '[0;36m',
			        'BOLD'        => '[1m',
			        'UNDERSCORE'  => '[4m',
			        'REVERSE'     => '[7m',
	               );

		if (array_key_exists($color, $_colors) === true) {

			$out = $_colors[$color];

		} else {

			$out = '[0m';

		}

		if ($back === true) {

			return chr(27).$out.$text.chr(27).'[0m';

		} else {

			echo chr(27).$out.$text.chr(27).'[0m';

		}

	}

	protected function avg($arr)
	{

		$sum = array_sum($arr);
		$num = count($arr);

		return ($sum / $num);

	}

	protected function mksize($bytes)
	{

		if ($bytes < (1000 * 1024)) {

			return number_format(($bytes / 1024), 2).' kB';

		} else if ($bytes < (1000 * 1048576)) {

			return number_format(($bytes / 1048576), 2).' MB';

		} else if ($bytes < (1000 * 1073741824)) {

			return number_format(($bytes / 1073741824), 2).' GB';

		} else {

			return number_format(($bytes / 1099511627776), 2).' TB';

		}

	}

}