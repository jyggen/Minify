<?php
/*
 * Minify Class by Jonas Stendahl
 * http://www.jyggen.com/
 *
 * JSMin Class by Ryan Grove
 * http://code.google.com/p/jsmin-php/
 *
 * CSSMin Class by Corey Hart
 * http://www.codenothing.com/
 */

require 'minify/JSMin.class.php';
require 'minify/CSSMin.class.php';

class minify {
    
    /* minify vars */
    private $debug;
    private $file_dir;
    private $min_file;
    private $min_path;
    public  $files;
    private $type;
    private $ext;
    private $hash_file;
    private $algorithm;
    private $hashes;
    private $priority;
    public  $link;
    
    /* debug vars */
    private $start;
    private $stop;

    public function __construct($type, $dir, $file, $filter = array(), $priority = array(), $debug = false) {
        
        if($this->debug) $this->start = microtime();
        
        /* private variables */
        $this->debug     = $debug;
        $this->file_dir  = $dir;
        $this->min_file  = $file;
        $this->min_path  = $dir.$file;
        $this->type      = $type;
        $this->filter    = $filter;
        $this->priority  = $priority;
        $this->hash_file = 'checksums.sfv';
        $this->algorithm = 'md4';
        
        if($this->debug) echo '<pre><strong>Minify - Compress your files on the fly!</strong>' . "\n" . 'debug information for .' . $this->type . ' files' . "\n\n";
        
        /* push the minified file into the filter array, we don't want to compress it with the other files */
        array_push($this->filter, $this->min_file);
        
        /* check if the type is allowed */
        if($type != 'css' && $type != 'js')
            return false;
        
        /* set the extension and hash file variables depending on the type */
        if($type == 'css')    $this->ext = '*.css';
        elseif($type == 'js') $this->ext = '*.js';
		
		 /* get all the files from the dir */
        if(!$this->getFiles())
        	trigger_error($this->file_dir . ' does not exist', E_USER_ERROR);
        	
        if(empty($this->files)) {
        	
        	trigger_error('no files found', E_USER_NOTICE);
        	return false;
		
		}
			
        /* check if the minified file and the file with the hashes exists */
        if(file_exists($this->min_path) && file_exists($this->file_dir . $this->hash_file) && !$this->compare()) {

            /* debug output */
            if($this->debug) $this->debug('keep', $this->min_file, hash_file($this->algorithm, $this->min_path), 'OK!');

        } else {

            /* compress the files */
            $min = $this->compress();

            /* generate a new hash file */
            $this->generate_hash_file();

            /* put the compressed code in the minified file */
            file_put_contents($this->min_path, $min);

            /* debug output */
            if($this->debug) $this->debug('save', $this->min_file, hash_file($this->algorithm, $this->min_path), 'OK!');

        }
     
        if($this->debug) {
       
            $this->stop = microtime();
            $res = ($this->stop - $this->start);
            echo "\n" . 'Execution Time: ' . round($res, 6) . '</pre>';

        }
        
        $this->link = $this->min_path . '?' . filemtime($this->min_path);
 
    }
    
    /* print debug information */
    private function debug($action, $file, $hash, $status, $pad = 40) {
        
        echo str_pad($action, $pad, ' ') . str_pad($file, $pad, ' ') . str_pad($hash, $pad, ' ') . $status . "\n";
    
    }

    /* get all files in a folder */
    private function getFiles() {
		
		if(!file_exists($this->file_dir))
			return false;
		
        $dir[$this->file_dir] = scandir($this->file_dir);
		$sdir = array();
		
        foreach($dir[$this->file_dir] as $i => $entry) {
            if($entry != '.' && $entry != '..' && fnmatch($this->ext, $entry) && (!is_array($this->filter) || !in_array($entry, $this->filter))) {
                $sdir[] = $this->file_dir . $entry;
                $this->hashes[$entry] = hash_file($this->algorithm, $this->file_dir . $entry);
            }
        }
        
        $sdir = $this->order($sdir);
        $this->files = $sdir;
        
        return true;

    }
    
    /* order the files after priority */
    private function order($dir) {
        
        $sdir = array();
        
        foreach($this->priority as $file) {
        	
        	if(file_exists($this->file_dir . $file))
        		$sdir[] = $this->file_dir . $file;
        		
        	else
        		trigger_error($file . ' does not exist', E_USER_NOTICE);
        
        }
           
        foreach($dir as $file) {

            if(!in_array(str_replace($this->file_dir, '', $file), $this->priority)) {

                $sdir[] = $file;

            }

        }
        
        return $sdir;
    
    }

    /* compare every files md4 with the hashes */
    private function compare() {
        
        $cache = file_get_contents($this->file_dir . $this->hash_file);
        $cache = explode("\n", $cache);

        foreach($cache as $key => $val) {
            
            $data = explode(' ', $val);
            $hashes[$data[0]] = $data[1];

        }

        foreach($this->files as $file) {
                        
            $file = explode('/', $file);
            
            if(!array_key_exists($file[1], $hashes)) {
                if($this->debug) $this->debug('check', $file[1], $this->hashes[$file[1]], 'FAIL!');
                return true;
            }
            
            if($this->hashes[$file[1]] != $hashes[$file[1]]) {
                if($this->debug) $this->debug('check', $file[1], $this->hashes[$file[1]], 'FAIL!');
                return true;
            }
            
            unset($hashes[$file[1]]);
            if($this->debug) $this->debug('check', $file[1], $this->hashes[$file[1]], 'OK!');

        }
        
        if(empty($hashes))
        	return false;

        return false;

    }

    /* compress the code with it's own minify class */
    private function compress() {

        $code = '';
        foreach($this->files as $file)
            $code .= file_get_contents($file) . ' ';
        
        if($this->type == 'js') {
            return trim(JSMin::minify($code));
        } else {
            $css = new CSSMin();
            return trim($css->compress($code));
        }

    }

    /* generate a new hash file */
    private function generate_hash_file() {
        
        $cache = '';
        
        foreach($this->hashes as $file => $hash) {
            $cache .= $file . ' ' . $hash . "\n";
            if($this->debug) $this->debug('cache', $file, $hash, 'OK!');
        }
        
        file_put_contents($this->file_dir . $this->hash_file, trim($cache));

    }

}
