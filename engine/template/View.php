<?php 
namespace engine\template;

class View {
    /**
     * The variable property contains the variables
     * that can be used inside of the templates.
     *
     * @access private
     * @var array
     */
    private $variables = array();

    /**
     * The directory where the templates are stored
     *
     * @access private
     * @var string
     */
    private $template_dir = null;

    /**
     * Turns caching on or off
     *
     * @access private
     * @var bool
     */
    private $caching = false;

    /**
     * The directory where the cache files will be saved.
     *
     * @access private
     * @var string
     */
    private $cache_dir = 'cache';

    /**
     * Lifetime of a cache file in seconds.
     * @access private
     * @var int
     */
    private $cache_lifetime = 3000;
    
    /**
     *
     * The constructor, duh
     *
     */
    public function __construct() {
        // parent::__construct(array(), ArrayObject::ARRAY_AS_PROPS);
        // $this->setCacheDir();
    }

    /**
     * Adds a variable that can be used by the templates.
     *
     * Adds a new array index to the variable property. This
     * new array index will be treated as a variable by the templates.
     *
     * @param string $name The variable name to use in the template
     * @param string $value The content you assign to $name
     * @access public
     * @return void
     * @see getVars, $variables
     *
     */
    public function __set($name, $value) {
        $this->variables[$name] = $value;
    }

    /**
     * @Returns names of all the added variables
     *
     * Returns a numeral array containing the names of all
     * added variables.
     *
     * @access public
     * @return array
     * @see addVar, $variables
     *
     */
    public function getVars() {
         $variables = array_keys($this->variables);
         return !empty($variables) ? $variables : false;
    }

    /**
     *
     * Outputs the final template output
     *
     * Fetches the final template output, and echoes it to the browser.
     *
     * @param string $file Filename (with path) to the template you want to output
     * @param string $id The cache identification number/string of the template you want to fetch
     * @access public
     * @return void
     * @see fetch
     *
     */
    public function render($file, $id = null) {
        echo $this->fetch($file, $id);
    }

    /**
     *
     * Outputs the cache output
     * @param string $file Filename
     * @param string $id The cache identification
     * @access public
     * @return void
     * @see getCache
     */
    public function fetchCache($file, $id = null) {
        return $this->getCache($file, $id);
    }

    /**
     * Fetch the final template output and returns it
     *
     * @param string $template_file Filename (with path) to the template you want to fetch
     * @param string $id The cache identification number/string of the template you want to fetch
     * @access private
     * @return string Returns a string on success, FALSE on failure
     * @see render
     *
     */
    public function fetch($template_file, $id = null) {
        /*** if the template_dir property is set, add it to the filename ***/
        if (!empty($this->template_dir)) {
            $template_file = realpath($this->template_dir) .DIRECTORY_SEPARATOR. $template_file;
        }

        /*** get the cached file contents ***/
        if ($this->caching == true && $this->isCached($template_file, $id)) {
            $output = $this->getCache($template_file, $id);
        } else {
            $output = $this->getOutput($template_file);
            /*** create the cache file ***/
            if ($this->caching == true) {
                $this->addCache($output, $template_file, $id);
            }
        }
         return isset($output) ? $output : false;
    }

    /**
     *
     * Fetch the template output, and return it
     *
     * @param string $template_file Filename (with path) to the template to be processed
     * @return string Returns a string on success, and FALSE on failure
     * @access private
     * @see fetch, render
     *
     */
    public function getOutput($template_file) {
        /*** extract all the variables ***/
        extract($this->variables);
        if (file_exists($template_file)) {
            // if (ob_get_length() > 0){
            // 	ob_end_clean();	
            // }
            ob_start();
            include($template_file);
            $output = ob_get_contents();
            ob_end_clean();
        } else {
            throw new \Exception("The template file '$template_file' does not exist");
        }
        return !empty($output) ? $output : false;
    }

    /**
     *
     * Sets the template directory
     *
     * @param string $dir Path to the template dir you want to use
     * @access public
     * @return void
     *
     */
    public function setTemplateDir($dir) {
        $template_dir = realpath($dir);
        if (is_dir($template_dir)) {
            $this->template_dir = $template_dir;
        } else {
            throw new \Exception("The template directory '$dir' does not exist", 200);
        }
    }

    /**
     *
     * Sets the cache directory
     *
     * @param string $dir Path to the cache dir you want to use
     * @access public
     * @return void
     * @see setCacheLifetime
     *
     */
    function setCacheDir($cacheDir = null) {
        if(is_null($cacheDir)) {
            $cacheDir = \Tea::app()->config('cache_dir') .DIRECTORY_SEPARATOR.
                \Tea::app()->config('page_cache_dir');
        }

        if(!is_dir($cacheDir)) {
            //  TODO log error here FIXME
            // try to create the direcory
            if(mkdir($cacheDir) == false) {
                // unable to create the directory
                // option set to false and allow to continue.. 
                   // $this->setCaching( false );
                throw new \Exception("The cache directory '$cacheDir' does not exist!");
            }
        }

        if (!is_writable($cacheDir)) {
            // TODO log error here FIXME
            throw new \Exception("The cache directory '$cacheDir' is not writable");
        } else {
            $this->cache_dir = $cacheDir;
        }
    }

    /**
     * Sets how long the cache files should survive
     *
     * @param INT $seconds Number of seconds the cache should survive
     * @access public
     * @return void
     * @see setCacheDir, isCached, setCaching
     *
     */
    public function setCacheLifetime($seconds=0) {
        $this->cache_lifetime = is_numeric($seconds) ? $seconds : 0;
    }

    /**
     * Turn caching on or off
     *
     * @param bool $state Set TRUE turns caching on, FALSE turns caching off
     * @access public
     * @return void
     * @see setCacheLifetime, isCached, setCacheDir
     *
     */
    public function setCaching($state=false) {
        $this->caching = $state;
    }

    /**
     * Checks if the template in $template is cached
     *
     * @param string $file Filename of the template
     * @param string $id The cache identification number/string of the template you want to fetch
     * @access public
     * @return bool
     * @see setCacheLifetime, setCacheDir, setCaching
     *
     */
    public function isCached($file, $id = null) {
        $cacheId= $id ? md5($id . basename($file)) : md5(basename($file));

        $filename = $this->cache_dir .DIRECTORY_SEPARATOR. $cacheId .DIRECTORY_SEPARATOR. basename($file);
        if (is_file($filename)) {
            clearstatcache();
            if (filemtime($filename) > (time() - $this->cache_lifetime)) {
                $isCached = true;
            }
        }
        return isset($isCached) ? true : false;
    }

    /**
     * Makes a cache file. Internal method
     *
     * @param string $content The template output that will be saved in cache
     * @param string $file The filename of the template that is being cached
     * @param string $id The cache identification number/string of the template you want to fetch
     * @access private
     * @return void
     * @see getCache, clearCache
     *
     */
    private function addCache($content, $file, $id = null) {
        // create the cache id
        $cacheId = $id ? md5($id . basename($file)) : md5(basename($file));

        // set the cacheDir eg: /tmp/cache 
        // this will set $this->cache_dir
        $this->setCacheDir();

        // create the cache filename
        $filename = $this->cache_dir . DIRECTORY_SEPARATOR . $cacheId . DIRECTORY_SEPARATOR . basename($file);

        // create the directory name for the cache file
        $directory = $this->cache_dir . DIRECTORY_SEPARATOR . $cacheId;

        // create the cache directory
        if( !is_dir($directory)) {
            mkdir ($directory);
        }

        /*** write to the cache ***/
        if( file_put_contents($filename, $content ) == FALSE) {
            throw new \Exception("Unable to write to cache");
        }
    }


    /**
     * Returns the content of a cached file
     *
     * @param string $file The filename of the template you want to fetch
     * @param string $id The cache identification number/string of the template you want to fetch
     * @access private
     * @return string Cached content on success, FALSE on failure
     * @see addCache, clearCache
     *
     */
    private function getCache($file, $id = null) {

        $cacheId  = $id ? md5($id . basename($file)) : md5(basename($file));
        $filename = $this->cache_dir .DIRECTORY_SEPARATOR. $cacheId .DIRECTORY_SEPARATOR. basename($file);

        /*** read the cache file into a variable ***/
        $content  = file_get_contents($filename);
        return isset($content) ? $content : false;
    }

    /**
     *
     * Deletes all of the stored cache files
     *
     * @access public
     *
     * @return void
     *
     * @see addCache, getCache
     *
     */
    public function clearCache() {
         $cacheDir = realpath($this->cache_dir);
         $this->delDir($cacheDir);
    }

    /**
     * Remove files and folders recursively.
     * WARNING: It does not care what directory $dir is.
     *
     * @param string $dir directory to remove files and folders from
     *
     * @access private
     *
     * @return void
     *
     * @see clearCache
     *
     */	
    private function delDir($dir) {
        /*** perhaps a recursiveDirectoryIteratory here ***/
        $deleteDir = realpath($dir);

        if ($handle = opendir($deleteDir)) {
            while (false !== ($file = readdir($handle))) {
                if ($file != '.' && $file != '..') {
                    if (is_dir($deleteDir .DIRECTORY_SEPARATOR. $file)) {
                        $this->delDir($deleteDir .DIRECTORY_SEPARATOR. $file);

                        if(is_writable($deleteDir .DIRECTORY_SEPARATOR. $file)) {
                            rmdir($deleteDir .DIRECTORY_SEPARATOR. $file);
                        } else {
                            throw new \Exception("Unable to remove Directory");
                        }

                    } else if (is_file($deleteDir .DIRECTORY_SEPARATOR. $file)) {

                        if(is_writable($deleteDir .DIRECTORY_SEPARATOR. $file)) {
                            unlink($deleteDir .DIRECTORY_SEPARATOR. $file);
                        } else {
                            throw new \Exception("Unable to unlink $deleteDir".DIRECTORY_SEPARATOR."$file");
                        }

                    }
                }
            }
            closedir($handle);
        }
    }
}