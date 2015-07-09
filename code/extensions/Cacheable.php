<?php
/**
 * 
 * Gives {@link SiteTree} objects caching abilities.
 * 
 * @author Deviate Ltd 2014-2015 http://www.deviate.net.nz
 * @package silverstripe-cachable
 * @todo Remove capitalised template-methods
 */
class Cacheable extends SiteTreeExtension {
    
    /**
     *
     * @var mixed
     */
    public static $_cached_navigation;

    /**
     * 
     * Initialises a pre-built cache (via {@link CacheableNavigation_Rebuild})
     * used by front-end calling logic e.g. via $CachedData blocks in .ss templates
     * unless build_on_reload is set to false in YML config.
     * 
     * Called using SilverStripe's extend() method in {@link ContentController}.
     * 
     * @param Controller $controller
     * @return void
     * @see {@link CacheableNavigation_Rebuild}.
     * @see {@link CacheableNavigation_Clean}.
     * @todo add queuedjob chunking ala BuildTask to this csche-rebuild logic.
     * At the moment we attempt to skip it if build_cache_onload is set to false 
     * in YML Config
     */
    public function contentControllerInit($controller) {
        // Skip if flushing or the project instructs us to do so
        $skip = (self::is_flush($controller) || !self::build_cache_onload());
        $service = new CacheableNavigationService();
        $currentStage = Versioned::current_stage();
        $stage_mode_mapping = array(
            "Stage" => "stage",
            "Live"  => "live",
        );
        
        $service->set_mode($stage_mode_mapping[$currentStage]);
        $siteConfig = SiteConfig::current_site_config();
        if(!$siteConfig->exists()) {
            $siteConfig = $this->owner->getSiteConfig();
        }
        
        $service->set_config($siteConfig);

        if($_cached_navigation = $service->getCacheableFrontEnd()->load($service->getIdentifier())) {
            if(!$skip && !$_cached_navigation->get_completed()) {
                $service->refreshCachedConfig();
                if(class_exists('Subsite')) {
                    $pages = DataObject::get("Page", "\"SubsiteID\" = '".$siteConfig->SubsiteID."'");
                } else {
                    $pages = DataObject::get("Page");
                }
                
                if($pages->exists()) {
                    foreach($pages as $page) {
                        $service->set_model($page);
                        $service->refreshCachedPage(true);
                    }
                }
                $service->completeBuild();
                $_cached_navigation = $service->getCacheableFrontEnd()->load($service->getIdentifier());

            }
            
            Config::inst()->update('Cacheable', '_cached_navigation', $_cached_navigation);
        }
    }

    public function onAfterWrite() {
        $this->refreshPageCache(array(
            'Stage' => 'stage',
        ), false);
    }

    public function onAfterPublish(&$original) {
        $this->refreshPageCache(array(
            'Live' => 'live',
        ), false);
    }

    public function onAfterUnpublish() {
        $this->removePageCache(array(
            'Live' => 'live',
        ), false);
    }

    public function onAfterDelete() {
        $this->removePageCache(array(
            'Stage' => 'stage',
            'Live' => 'live',
        ), false);
        
        $this->refreshPageCache(array(
            'Stage' => 'stage',
            'Live' => 'live',
        ), false);
    }

    /**
     * 
     * @param array $modes
     * @param boolean $forceRemoval Whether to unset() children in {@link CacheableSiteTree::removeChild()}.
     * @return void
     */
    public function refreshPageCache($modes, $forceRemoval = false) {
        //get the unlocked cached Navigation first
        $siteConfig = $this->owner->getSiteConfig();
        if(!$siteConfig->exists()) {
            $siteConfig = SiteConfig::current_site_config();
        }
        
        foreach($modes as $stage => $mode) {
            $service = new CacheableNavigationService($mode, $siteConfig);
            $cache_frontend = $service->getCacheableFrontEnd();
            $id = $service->getIdentifier();
            $cached = $cache_frontend->load($id);
            if($cached) {
                $cached_site_config = $cached->get_site_config();
                if(!$cached_site_config) {
                    $service->refreshCachedConfig();
                }
                
                $versioned = Versioned::get_one_by_stage(get_class($this->owner), $stage, "\"SiteTree\".\"ID\" = '".$this->owner->ID."'");
                if($versioned) {
                    $service->set_model($versioned);
                    $service->refreshCachedPage($forceRemoval);
                }
            }
        }
    }

    /**
     * 
     * @param array $modes
     * @param boolean $forceRemoval Whether to unset() children in {@link CacheableSiteTree::removeChild()}.
     * @return void
     */
    public function removePageCache($modes, $forceRemoval = true) {
        $siteConfig = $this->owner->getSiteConfig();
        if(!$siteConfig->exists()) {
            $siteConfig = SiteConfig::current_site_config();
        }
        
        foreach($modes as $stage => $mode) {
            $service = new CacheableNavigationService($mode, $siteConfig, $this->owner);
            $cache_frontend = $service->getCacheableFrontEnd();
            $id = $service->getIdentifier();
            $cached = $cache_frontend->load($id);
            if($cached) {
                $cached_site_config = $cached->get_site_config();
                if(!$cached_site_config) {
                    $service->refreshCachedConfig();
                }
                $service->removeCachedPage($forceRemoval);
            }
        }
    }

    /**
     * 
     * @return mixed ContentController | array
     */
    public function CachedNavigation() {
        if($this->owner->exists()) {
            if($cachedNavigiation = Config::inst()->get('Cacheable', '_cached_navigation')) {
                if($cachedNavigiation->isUnlocked() && $cachedNavigiation->get_completed()) {
                    return $cachedNavigiation;
                }
            }
        }
        
        return new ContentController($this->owner);
    }

    /**
     * 
     * Usually used in template logic inside <% with %> blocks.
     * 
     * @see README.md
     * @return mixed ContentController | array
     * @todo What to do with controller URLs other than returning the homepage's cache?
     */
    public function CachedData() {
        if($cachedNavigiation = Config::inst()->get('Cacheable', '_cached_navigation')) {
            if($cachedNavigiation->isUnlocked() && $cachedNavigiation->get_completed()) {
                $site_map = $cachedNavigiation->get_site_map();
                if(!empty($site_map[$this->owner->ID])) {
                    return $site_map[$this->owner->ID];
                }
                
                /*
                 * Prevent errors and go-slows for controller URLs e.g. /admin 
                 * and return the homepage's cache as a 'sensible' default
                 */
                return $site_map[1];
            }
        }

        return new ContentController($this->owner);
    }
    
    /**
     * 
     * Detect if a flush operation is happening.
     * 
     * @param Controller $controller
     * @return boolean
     * @todo add tests
     */
    public static function is_flush(Controller $controller) {
        $getVars = $controller->getRequest()->getVars();
        return (stristr(implode(',', array_keys($getVars)), 'flush') !== false);
    }
    
    /**
     * 
     * Current module default is to build the cache if it's not present via
     * a browser request after the "first user pays" pattern. This may not be 
     * desirable on sites with 1000s of page objects.
     * 
     * @return boolean
     * @todo Add tests
     */
    public static function build_cache_onload() {
        return (bool) Config::inst()->get('CacheableConfig', 'build_cache_onload');
    }
    
    // TODO: Remove

    public $start_time;
	public function StartTime() {
        $this->start_time = time();
        return '<br />starting at '.$this->start_time."<br />";
    }

    public $end_time;
	public function EndTime() {
        $this->end_time = time();
        return '<br />ending at '.$this->end_time."<br />";
    }

	public function TimeConsumed() {
        return '<br />time consumed: '.((int)$this->end_time-(int)$this->start_time)."<br />";
    }
}

/**
 * 
 * @author Deviate Ltd 2014-2015 http://www.deviate.net.nz
 * @package silverstripe-cachable
 * @todo Add unit tests to ensure exceptions are thrown in correct circumstances
 * @todo Move this into own class file
 */
class CacheableConfig {
    
    /**
     * 
     * @var string
     */
    private static $default_mode = 'file';
    
    /**
     * 
     * Get us the current caching mode. Useful for debugging
     * 
     * @var string
     */
    protected static $current_mode = '';
    
    /**
     * 
     * Used for the new memory_limit value for display in arbitrary calling logic.
     * 
     * @var int
     */
    public static $ini_modified_memory_limit = 0;
    
    /**
     * 
     * On smaller setups, CWP being one; allow the module as much RAM as it can offer.
     * See the URL below for why we cannot go above this in CWP, read: Suhosin.
     * 
     * @see https://www.cwp.govt.nz/guides/technical-faq/php-configuration/.
     * @todo Make generic: Check for Suhosin memory limit. Increase memory if current allocation < Suhosin allows
     * @return void
     */
    public static function configure_memory_limit() {
        // In testing, with sites in excess of 1000 pages, we've not seen anything greater
        // than 170Mb per queued chunk-set
        $newLimit = 256; // upper limit of CWP "small" instances becuase of Suhosin
        if(defined('CWP_ENVIRONMENT') && intval(ini_get('memory_limit')) < $newLimit) {
            ini_set('memory_limit', $newLimit . 'M');
            self::$ini_modified_memory_limit = $newLimit;
        }
    }
   
    /**
     * 
     * @return boolean True if "Memcached" extension is loaded
     */
    public static function configure_memcached() {
        if(extension_loaded('memcached')) {
            $defaultOpts = array(
                'servers' => array(
                    'host' => '127.0.0.1',
                    'port' => 11211,
                    'weight' => 1
                ),
                'client' => array(
                    Memcached::OPT_DISTRIBUTION         => Memcached::DISTRIBUTION_CONSISTENT,
                    Memcached::OPT_HASH                 => Memcached::HASH_MD5,
                    Memcached::OPT_LIBKETAMA_COMPATIBLE => true,
                )
            );
            
            // Use project-specific overridden opts, or the defaults
            $projectOpts = Config::inst()->get('CacheableConfig', 'opts');
            $serverOpts = ($projectOpts && !empty($projectOpts['memcached'])) ? $projectOpts['memcached']['servers'] : $defaultOpts['servers'];
            $clientOpts = ($projectOpts && !empty($projectOpts['memcached'])) ? $projectOpts['memcached']['client'] : $defaultOpts['client'];
            
            // Libmemcached is enabled.
            SS_Cache::add_backend(CACHEABLE_STORE_NAME, 'Libmemcached', array(
                'servers' => array($serverOpts),
                'client' => $clientOpts
                )
            );
            
            self::$current_mode = 'memcached';
            
            return true;
        }
        
        return false;
    }
    
    /**
     * 
     * @return boolean True if "Memcache" extension is loaded
     */
    public static function configure_memcache() {
        $defaultOpts = array(
            'servers' => array(
                'host' => 'localhost',
                'port' => 11211,
                'persistent' => true,
                'weight' => 1,
                'timeout' => 5,
                'retry_interval' => 15,
                'status' => true,
                'failure_callback' => ''
                )
            );
        
        if(class_exists('Memcache')) {
            // Use project-specific overridden opts, or the defaults
            $projectOpts = Config::inst()->get('CacheableConfig', 'opts');
            $serverOpts = ($projectOpts && !empty($projectOpts['memcache']['servers'])) ? $projectOpts['memcache']['servers'] : $defaultOpts['servers'];
            
            // Memcached is enabled.
            SS_Cache::add_backend(
                CACHEABLE_STORE_NAME, 'Memcache', array(
                    'servers' => $serverOpts['servers']
                )
            );
            
            self::$current_mode = 'memcache';
            
            return true;
        }
        
        return false;
    }
    
    /**
     * 
     * @return boolean True if the filesystem is available to be used for caching.
     */
    public static function configure_file() {     
        $cacheable_store_dir = self::is_running_test() ? CACHEABLE_STORE_DIR_TEST : CACHEABLE_STORE_DIR;
        if(!is_dir($cacheable_store_dir)) {
            mkdir($cacheable_store_dir, Config::inst()->get('Filesystem', 'folder_create_mask'));
        }
        
        $storeIsOk = (
            file_exists($cacheable_store_dir) &&
            is_writable($cacheable_store_dir)
        );
        if(!$storeIsOk) {
            return false;
        }
        
        // Write server config files to cache-dir if it should exist relative to "assets"
        self::protect_cache_dir();

        // Update SilverStripe so it leaves cache-dirs under "assets" alone when attempting to sync assets
        self::prevent_cache_dir_sync();

        SS_Cache::add_backend(CACHEABLE_STORE_NAME, 'File', array(
            'cache_dir' => $cacheable_store_dir,
            'read_control' => false, // If true, fails to load cache when Queueing enabled.
            'file_name_prefix' => 'cacheable_cache',
            'file_locking' => true
        ));
        
        self::$current_mode = 'file';
        
        return true;
    }
    
    /**
     * 
     * @return boolean True if APCu is installed as an extension and is enabled in php.ini
     */
    public static function configure_apc() {
        $isApcEnabled = (extension_loaded('apc') && ini_get('apc.enabled') == 1);
        if(!$isApcEnabled) {
            return false;
        }

        SS_Cache::add_backend(CACHEABLE_STORE_NAME, 'Apc');
        
        self::$current_mode = 'apc';
        
        return true;
    }
    
    /**
     * 
     * @throws CacheableException
     * @return void
     */
    public static function configure() {
        // Project-specific YML config trumps anything in module's _config.php
        if(!$mode = Config::inst()->get('CacheableConfig', 'cache_mode')) {
            $mode = self::$default_mode;
        }
        
        $confMethodName = 'configure_' . $mode;
        if(!method_exists(get_class(), $confMethodName)) {
            throw new CacheableException('The configured cache mode: "$mode" doesn\'t exist.');
        }
        
        // Default to "File" backend if one of the modes isn't playing ball
        if(!self::$confMethodName()) {
            if(!self::configure_file()) {
                throw new CacheableException('Unable to select a cache backend. Giving up.');
            }
        }
    }
    
    /**
     * 
     * Returns the current cache mode.
     * 
     * @return string
     */
    public static function current_cache_mode() {
        return self::$current_mode;
    }
    
    /**
     * SapphireTest::is_running_test() returns false at this point, and there is 
     * no Controller available either so we need an alternate way of detecting if 
     * tests are running.
     * 
     * Ideally we'd be using mocking, so this hack wouldn't be necessary.
     * 
     * @return boolean
     */
    public static function is_running_test() {
        if(isset($_REQUEST['url'])) {
            return stristr($_REQUEST['url'], 'dev/tests') !== false;
        }
        
        return false;
    }
    
    /**
     * 
     * Deal with userland alternate cache location for the "File" backend. This should 
     * always be relative to the assets dir for portability.
     * 
     * The default if no such setting is present, is to place the cache directory
     * beneath SilverStripe's tmp dir.
     * 
     * @return string
     */
    public static function cache_dir_path() {
        $altCacheDir = Config::inst()->get('CacheableConfig', 'alt_cache_dir');
        $charMask = " \t\n\r\0\x0B/";
        if($altCacheDir) {
            $altDir = trim($altCacheDir, $charMask);
            // If alt_cache_dir matches "cacheable", just use that
            if($altDir === CACHEABLE_STORE_DIR_NAME) {
                $cacheDir = '_' . CACHEABLE_STORE_DIR_NAME;
            } else {
                $cacheDir = '_' . $altDir . DIRECTORY_SEPARATOR . CACHEABLE_STORE_DIR_NAME;
            }
            $cacheDir = ASSETS_PATH . DIRECTORY_SEPARATOR . $cacheDir;
        } else {
            $cacheDir = TEMP_FOLDER . DIRECTORY_SEPARATOR . CACHEABLE_STORE_DIR_NAME;
        }
        
        return $cacheDir;
    }
    
    /**
     * 
     * Return the URI relative-to, and including the "assets" folder.
     * 
     * @param boolean $withAssets
     * @return boolean|string
     */
    public static function cache_dir_location($withAssets = true) {
        $cacheDirPath = self::cache_dir_path();
        $isCacheDirRelative = stristr($cacheDirPath, ASSETS_DIR) !== false;
        if($isCacheDirRelative) {
            $uriTruncated = substr($cacheDirPath, 0, strpos($cacheDirPath, ASSETS_DIR));
            $result = str_replace($uriTruncated, '', $cacheDirPath);
            if($withAssets) {
                return '/' . $result;
            }
            return str_replace(ASSETS_DIR, '', $result);
        }
        
        return false;
    }
    
    /**
     * 
     * Simply return the name of the bottom-level dir in which cache files will be stored.
     * Takes into account userland config viz `alt_cache_dir`.
     * 
     * Examples:
     * 
     * - alt_cache_dir: 'foo/bar' --> "bar"
     * - alt_cache_dir: 'cacheable' --> "cacheable"
     * 
     * @param string $cacheDir
     * @return string
     */
    public static function cache_dir_name($cacheDir) {
        return pathinfo($cacheDir, PATHINFO_FILENAME);
    }
    
    /**
     * 
     * Generate files appropriate to the host webserver for protecting access to the
     * cache dir TTW. Only in use when using "File" backend via {@link self::configure_file()}
     * and the userland `alt_cache_dir` config is present.
     * 
     * @return void
     */
    private static function protect_cache_dir() {
        $altCacheDir = Config::inst()->get('CacheableConfig', 'alt_cache_dir');
        $isHttpdConf = file_exists(self::cache_dir_path() . DIRECTORY_SEPARATOR . '.htaccess');
        if($altCacheDir && !$isHttpdConf) {
            // Source SS template files for copying into final config 
            $resourceDir = CACHEABLE_MODULE_DIR . DIRECTORY_SEPARATOR . 'templates/resources';
            $httpdConf =  file_get_contents($resourceDir . DIRECTORY_SEPARATOR . 'htaccess.ss');
            $iisConf = file_get_contents($resourceDir . DIRECTORY_SEPARATOR . 'webconfig.ss');

            file_put_contents(self::cache_dir_path() . DIRECTORY_SEPARATOR . '.htaccess', $httpdConf);
            file_put_contents(self::cache_dir_path() . DIRECTORY_SEPARATOR . 'web.config', $iisConf);
        }
    }
    
    /**
     * 
     * Update SilverStripe's understanding of the full range of "unsyncable" asset
     * folder sub-dirs. Only called in the context of the "File" backend and if userland
     * config setting exists for `alt_cache_dir`.
     * 
     * Examples:
     * 
     * - alt_cache_dir: '/foo/bar' --> "^/_foo$/i" is added to Filesystem::sync_blacklisted_patterns.
     * - alt_cache_dir: '/cacheable' --> "^/_cacheable$/i" is added to Filesystem::sync_blacklisted_patterns.
     * 
     * @see Unit tests for {@link self::cache_dir_path()} for more context.
     * @return void
     */
    public static function prevent_cache_dir_sync() {
        $altCacheDir = Config::inst()->get('CacheableConfig', 'alt_cache_dir');
        if($altCacheDir) {
            $cachePathLocation = self::cache_dir_location(false);
            $cachePathParts = explode('/', ltrim($cachePathLocation, '/'));
            $pattern = "/^" . $cachePathParts[0] . "$/i";
            Config::inst()->update('Filesystem', 'sync_blacklisted_patterns', array($pattern));
        }
    }
}

/**
 * 
 * Custom exceptions allow module-specific exceptions to be easily tracked 
 * when such tracking/alerting systems are utilised.
 * 
 * @author Deviate Ltd 2015 http://www.deviate.net.nz
 * @package silverstripe-cachable
 */
class CacheableException extends Exception {
    
}
