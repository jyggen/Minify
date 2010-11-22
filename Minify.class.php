<?php
/*
 * Minify Class by Jonas Stendahl
 * http://www.jyggen.com/
 *
 * JSMin Class by Nicolas Martin
 * http://joliclic.free.fr/php/javascript-packer/en/
 *
 * CSSMin Class by Corey Hart
 * http://www.codenothing.com/
 */

require 'minify/JSMin.class.php';
require 'minify/CSSMin.class.php';

class minify {
    
    /* minify vars */
    protected $options       = array();
    protected $posteriority  = array();
    protected $priority      = array();
    protected $ignore        = array();
    protected $allowed_types = array('css', 'js');
    protected $files		 = array();
    protected $hashes		 = array();
    protected $code			 = array();
    
    protected $merge_path;
    protected $path_pattern;
    
    public $link;
    
    /* debug vars */
    private $start;
    private $stop;
    private $debug_output = '';

    public function __construct() {
        
        $this->start = microtime();
        $this->reset();

	}
	
	public function __destruct() {
	
		if($this->options['debug']) {
		
			print '<pre>' . $this->debug_output;
		
			$this->stop = microtime();
            $res = ($this->stop - $this->start);
            
            print "\n" . 'Execution Time: ' . round($res, 6) . '</pre>';
			
		}
	
	}
	
	public function run() {
        
        /* check if options->type is valid */
        if(!in_array($this->options['type'], $this->allowed_types))
			trigger_error($this->options['type'] . ' is not a valid file type', E_USER_ERROR);
		
		/* get all the files from the options->dir */
		$this->get_files();
		
		/* if options->merge is true generate the file path */
		if($this->options['merge'])
			$this->get_merge_file();
		else
			$this->get_file_pattern();

		$this->debug('--------------------------------------', true);
		
		/* compress everything if options->cache doesn't exists */
		if(!file_exists($this->options['directory'] . $this->options['cache'])) {
			
			$this->debug('Trigger: Couldn\'t find ' . $this->options['cache'] . '.', true);
			$this->compress();				
			$this->save();
			$this->generate_hash_file();
		
        /* compress and merge everything if options->merge is true and merge_path doesn't exist */
        } elseif($this->options['merge'] == true && !file_exists($this->merge_path)) {
        	
        	$this->debug('Trigger: Couldn\'t find ' . $this->merge_path . '.', true);
			$this->compress();
			$this->save();
			$this->generate_hash_file();
		
		/* compress everything if files and hashes don't match */
		} elseif(!$this->compare()) {
			
			$this->debug('Trigger: Files don\'t match.', true);
			$this->compress();
			$this->save();
			$this->generate_hash_file();
		
		} else {
		
			$this->debug('Trigger: Everything seems OK!', true);
		
		}
		
		if($this->options['merge']) {
		
			$hash = hash_file($this->options['algorithm'], $this->merge_path);
			
			switch($this->options['type']) {
				case 'js':
					$this->link = '<script text="text/javascript" src="' . $this->merge_path . '?' . $hash . '"></script>' . "\n";
					break;
				case 'css':
					$this->link = '<link rel="stylesheet" href="' . $this->merge_path . '?' . $hash . '" type="text/css" media="screen" />' . "\n";
					break;
			}
		
		} else {
		
			foreach($this->files as $file) {
					
				$hash = hash_file($this->options['algorithm'], $this->options['directory'] . $file);
				
				switch($this->options['type']) {
					case 'js':
						$this->link .= '<script text="text/javascript" src="' . $file . '?' . $hash . '"></script>' . "\n";
						break;
					case 'css':
						$this->link .= '<link rel="stylesheet" href="' . $file . '?' . $hash . '" type="text/css" media="screen" />' . "\n";
						break;
				}
					
			}
			
		}

    }
    
    public function set($name, $value) {

		$this->options[$name] = $value;
		return $this->options[$name];
    
    }
    
    public function reset($clear = false) {
    
    	if($clear) {

			$this->options = array();
			return true;

		}
		
		$this->options = array(
			'algorithm' => 'crc32b',
			'cache'     => 'minify.sfv',
			'debug'     => false,
			'directory' => '',
			'merge'     => true,
			'name'      => 'all',
			'prefix'    => false,
			'regex'     => '/^.*\.minify\.(css|js)$/i',
			'suffix'    => 'minify',
			'type'      => ''
		);
		
		return $this->options;
    
    }
    
    private function debug($output, $linebreak = false) {
    	
    	if($this->options['debug'] == true) {
    		
    		if($linebreak)
    			$this->debug_output .= "\n";
    		
    		if(is_array($output)) {
    		
    			foreach($output as $ent)
					$this->debug_output .= ' - ' . $ent . "\n";
    		
    		} else
    			$this->debug_output .= $output . "\n";
    		
    		return true;
    	
    	} else return false;
    
    }
    
    private function get_merge_file() {
    
    	$this->merge_path = $this->options['directory'];
    	
    	if($this->options['prefix'])
    		$this->merge_path .= $this->options['prefix'] . '.';
    	
    	$this->merge_path .= $this->options['name'];
    	
    	if($this->options['suffix'])
    		$this->merge_path .= '.' . $this->options['suffix'];
    		
		$this->merge_path .= '.' . $this->options['type'];
		
		$this->debug('Path: ' . $this->merge_path, true);
		return true;
    
    }
    
    private function get_file_pattern() {
    
    	$this->path_pattern = $this->options['directory'];
    	
    	if($this->options['prefix'])
    		$this->path_pattern .= $this->options['prefix'] . '.';
    	
    	$this->path_pattern .= '%s';
    	
    	if($this->options['suffix'])
    		$this->path_pattern .= '.' . $this->options['suffix'];
    		
		$this->path_pattern .= '.' . $this->options['type'];
		
		$this->debug('Path: ' . $this->path_pattern, true);
		return true;
    
    }

    /* get all files in a folder */
    private function get_files() {
		
		if(!file_exists($this->options['directory']))
			trigger_error($this->options['directory'] . ' does not exist', E_USER_ERROR);
		
        $directory = scandir($this->options['directory']);
		$files     = array();
		
        foreach($directory as $file) {
        	
        	if($file != '.' && $file != '..') {
        		if(fnmatch('*.' . $this->options['type'], $file)) {
        			if(!in_array($file, $this->ignore)) {
        				if(!preg_match($this->options['regex'], $file)) {
							
							array_push($files, $file);
							$this->hashes[$file] = hash_file($this->options['algorithm'], $this->options['directory'] . $file);
							
        				}
        			}
        		}
        	}
        	
        }
        
        if(empty($files))
        	trigger_error('no files found', E_USER_NOTICE);
       	
       	$this->debug('Files:', true);
		$this->debug($files);	
       	
        $this->order($files);
        return true;

    }
    
    /* order the files */
    private function order($files) {
        
        $return = array();
        
        foreach($this->priority as $file) {
        	
        	if(file_exists($this->options['directory'] . $file))
        		array_push($return, $file);
        		
        	else
        		trigger_error($file . ' does not exist', E_USER_NOTICE);
        
        }
           
        foreach($files as $file) {

            if(!in_array(str_replace($this->options['directory'], '', $file), $this->priority) && !in_array(str_replace($this->options['directory'], '', $file), $this->posteriority))
				array_push($return, $file);

        }
        
        foreach($this->posteriority as $file) {
        	
        	if(file_exists($this->options['directory'] . $file))
        		array_push($return, $file);
        		
        	else
        		trigger_error($file . ' does not exist', E_USER_NOTICE);
        
        }
        
        $this->debug('Order:', true);
		$this->debug($files);	
        
		$this->files = $return;
		return true;
    
    }

    /* compare every file with the hash */
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

        return true;

    }

    /* compress the files */
    private function compress() {

		foreach($this->files as $file) {
		
	        $code = file_get_contents($this->options['directory'] . $file);
	        
	        switch($this->options['type']) {
			   	case 'js':
			   		$js   = new JSMin($code);
			   		$code = trim($js->pack());
			   		break;
			   	case 'css':
			   		$css  = new CSSMin();
			   		$code = trim($css->compress($code));
			   		break;
		   	}
		   	
		   	$this->code[$file] = $code;
	        
		}

    }
    
    private function save() {

    	if($this->options['merge']) {
    	
			$code = '';
			
			foreach($this->code as $string)
				$code .= $string;
			
			file_put_contents($this->merge_path, $code);

			$this->debug('Code saved to ' . $this->merge_path, true);
			return true;
			
    	} else {
    	
    		foreach($this->code as $file => $string) {
    		
				$ext = strrchr($file, '.');  
					
					if($ext !== false)  
						$file = substr($file, 0, -strlen($ext));  
				
				$path = sprintf($this->path_pattern, $file);
				
				file_put_contents($path, $string);
				$this->debug('Code saved to ' . $path, true);
			
			}
			
			return true;
    	
    	}
    
    }

    /* generate a new hash file */
    private function generate_hash_file() {
        
        $cache = '';
        $this->debug('Cache:', true);
  
        foreach($this->hashes as $file => $hash) {
        
            $cache .= $file . ' ' . $hash . "\n";
            $this->debug('cache' . "\t" . $file . "\t" . $hash . "\t" . 'OK!');
            
        }
        
        file_put_contents($this->options['directory'] . $this->options['cache'], trim($cache));

    }

}
