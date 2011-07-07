<?php
/*
 * Minify Class by Jonas Stendahl
 * http://www.jyggen.com/
 *
 * CSS Compressor by Corey Hart
 * http://www.codenothing.com/
 */

require 'minify/CSSCompression.php';
require 'minify/curl.php';

class minify {

    /* minify vars */
    protected $options;
    protected $posteriority;
    protected $priority;
    protected $ignore;
    protected $allowed_types;
    protected $files;
    protected $hashes;
    protected $code;
    protected $merge_path;
    protected $path_pattern;
    protected $all_files;

    public $links = array();

    /* debug vars */
    private $start;
    private $stop;
    private $debug_output = '';

	/* when Minify is initiated */
    public function __construct() {
        
        $this->start = microtime(true);
        
        /* Reset everything to their default values */
        $this->reset();

	}

	/* when Minify is closed */
	public function __destruct() {

		/* if debug is enabled output lots of data collected during runtime */
		if($this->options['debug'] === true) {
		
			print '<pre>' . $this->debug_output;
		
			$this->stop = microtime(true);
            $res = $this->stop - $this->start;
			            
            print "\n" . 'Execution Time: ' . round($res, 6) . '</pre>';
			
		}
	
	}
	
	/* Minify! */
	public function run() {
        
        /* check if options->type is valid */
        if(!in_array($this->options['type'], $this->allowed_types))
			trigger_error($this->options['type'] . ' is not a valid file type', E_USER_ERROR);
		
		/* generate merge_path */
		$this->get_merge_path();		
		
		if((!file_exists($this->options['directory'] . $this->options['cache'])) ||  // options->cache doesn't exists
			!file_exists($this->merge_path) || 										 // options->merge is true and merge_path doesn't exist
			!$this->compare()) {								    				 // files and hashes don't match
		
			$this->compress();
			$this->save();
			$this->generate_hash_file();
			$this->debug('Status: Something is wrong!', true);

		/* roll with our existing files */
		} else {
		
			$this->debug('Status: Everything seems OK!', true);
		
		}
		
		/* fetch the html code for all files */
		$this->get_links();
		
		$this->debug('--------------------------------------', true);

    }
    
    /* set option $name to $value */
    public function set($name, $value) {
		$this->options[$name] = $value;
  
    }
	
	/* shortcut to set type */
	public function setType($value) {
		$this->set('type', $value);
	}
	
	/* shortcut to set directory */
	public function setDirectory($value) {
		$this->set('directory', $value);
	}
	
	public function addFile($files) {
		if(is_array($files)) {
			foreach($files as $file) {
				$this->files[] = $file;
			}
		} else {
			$this->files[] = $files;
		}
	}
    
    /* reset everything to their default values */
    public function reset() {

		$this->options       = array();
    	$this->posteriority  = array();
    	$this->priority      = array();
    	$this->ignore        = array();
    	$this->allowed_types = array('css', 'js');
    	$this->files		 = array();
    	$this->hashes		 = array();
    	$this->code			 = array();
    	$this->merge_path	 = '';
    	$this->path_pattern  = '';
    	$this->all_files	 = array();

		$this->options = array(
			'algorithm'  => 'crc32b',
			'cache'      => 'minify.sfv',
			'debug'      => false,
			'directory'  => '',
			'absolute'   => true,
			'name'       => 'all',
			'prefix'     => false,
			'regex'      => '/^.*\.min\.(css|js)$/i',
			'script_src' => '<script type="text/javascript" src="%s?%s"></script>' . "\n",
			'style_link' => '<link rel="stylesheet" type="text/css" media="screen" href="%s?%s" />' . "\n",
			'suffix'     => 'min',
			'type'       => ''
		);

    }
    
    /* save $output for later use. $linebreak will add a new line in front of $output */
    private function debug($output, $linebreak = false) {
    	
    	if($this->options['debug'] === TRUE) {
    		
    		if($linebreak)
    			$this->debug_output .= "\n";
    		
    		if(is_array($output)) {
    		
    			foreach($output as $ent)
					$this->debug_output .= ' - ' . $ent . "\n";
    		
    		} else $this->debug_output .= $output . "\n";
    		
    		return TRUE;
    	
    	} else return FALSE;
    
    }
    
    /* generate the path and name of the merged file */
    private function get_merge_path() {
		
		if(empty($this->options['directory'])) {
			trigger_error('missing option directory', E_USER_ERROR);
			exit(1);
		}

    	$this->merge_path = $this->options['directory'];
		
		if(!is_dir($this->merge_path)) {
			trigger_error('invalid directory ' . $this->merge_path, E_USER_ERROR);
			exit(1);
		}
    	
    	if($this->options['prefix'])
    		$this->merge_path .= $this->options['prefix'] . '.';
    	
    	$this->merge_path .= $this->options['name'];
    	
    	if($this->options['suffix'])
    		$this->merge_path .= '.' . $this->options['suffix'];
    		
		$this->merge_path .= '.' . $this->options['type'];
		
		$this->debug('Merge Path: ' . $this->merge_path, true);
		return true;
    
    }
    
    /* generate an array with html code for each file */
    private function get_links() {
		
		$hash = hash_file($this->options['algorithm'], $this->merge_path);
		
		if($this->options['absolute'])
			$path = '/' . $this->merge_path;
		else
			$path = $this->merge_path;
		
		switch($this->options['type']) {
			case 'js':
				array_push($this->links, sprintf($this->options['script_src'], $path, $hash));
				break;
			case 'css':
				array_push($this->links, sprintf($this->options['style_link'], $path, $hash));
				break;
		}
    
    }

    /* compare every file with the hashes in the cache file */
    private function compare() {
        
        $cache = file_get_contents($this->options['directory'] . $this->options['cache']);
        $cache = explode("\n", $cache);

        foreach($cache as $line) {
            
            list($file, $hash) = explode(' ', $line);
            $hashes[$file] = $hash;

        }
        
        $this->debug('Compare:', true);

        foreach($this->files as $file) {
            
            if(!array_key_exists($file, $hashes)) {
            
            	$this->debug('check' . "\t" . $file . "\t" . $this->hashes[$file] . "\t" . 'FAIL!');
            	return false;
            
            }
            
            if($this->hashes[$file] != $hashes[$file]) {
            
                $this->debug('check' . "\t" . $file . "\t" . $this->hashes[$file] . "\t" . 'FAIL!');
                return false;
                
            }
            
			unset($hashes[$file]);
			$this->debug('check' . "\t" . $file . "\t" . $this->hashes[$file] . "\t" . 'OK!');

        }
        
        if(!empty($hashes))
        	return false;

        return true;

    }

    /* compress the code in the files */
    private function compress() {

		$code = '';
		
		foreach($this->files as $file)
			$code .= file_get_contents($file);
			
		switch($this->options['type']) {
			case 'js':
				$curl = new CURLRequest();
				$data = $curl->get('http://closure-compiler.appspot.com/compile', array(
					CURLOPT_RETURNTRANSFER => true, 
					CURLOPT_POSTFIELDS     => 'js_code=' . urlencode($code) . '&compilation_level=SIMPLE_OPTIMIZATIONS&output_format=json&output_info=errors&output_info=compiled_code',
					CURLOPT_POST           => true
				));
				$data = json_decode($data['content'], true);
				
				if(isset($data['errors'])) {
					trigger_error($data['errors'][0]['error'], E_USER_ERROR);
					exit(1);
				}
				
				$code = $data['compiledCode'];
				break;
			case 'css':
				$code = trim(CSSCompression::express($code, 'small'));
				break;
		}
		
		$this->code = $code;

    }
    
    /* save the code to disk in a merged file */
    private function save() {
		
		$this->debug('Save:', true);	

		file_put_contents($this->merge_path, $this->code);
		chmod($this->merge_path, 0755);

		$this->debug('Code saved to ' . $this->merge_path);

    	return true;
    
    }
	
	/* generate hashes */
	private function generate_hashes() {
		foreach($this->files as $k => $file) {
			$this->hashes[$file] = hash_file('crc32b', $file);
		}
	}

    /* generate and save a new cache */
    private function generate_hash_file() {
        
        $cache = '';
        $this->debug('Cache:', true);
  
        foreach($this->hashes as $file => $hash) {
        
            $cache .= $file . ' ' . $hash . "\n";
            $this->debug('cache' . "\t" . $file . "\t" . $hash . "\t" . 'OK!');
            
        }
        
        file_put_contents($this->options['directory'] . $this->options['cache'], trim($cache));
        chmod($this->options['directory'] . $this->options['cache'], 0600);

    }

}