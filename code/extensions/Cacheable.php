<?php
/**
 * 
 * Gives {@link SiteTree} objects caching abilities.
 * 
 * @author Deviate Ltd 2015 http://www.deviate.net.nz
 * @package silverstripe-cachable
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
     * used by front-end calling logic e.g. via $CachedData blocks in .ss templates.
     * 
     * Called using SilverStripe's extend() method in {@link ContentController}.
     * 
     * @param Controller $controller
     * @return void
     * @see {@link CacheableNavigation_Rebuild}.
     * @see {@link CacheableNavigation_Clean}.
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
                }else{
                    $pages = DataObject::get("Page");
                }
                if($pages->exists()) {
                    foreach($pages as $page) {
                        $service->set_model($page);
                        $service->refreshCachedPage();
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
        ));
    }

    public function onAfterPublish(&$original) {
        $this->refreshPageCache(array(
            'Live' => 'live',
        ));
    }

    public function onAfterUnpublish() {
        $this->removePageCache(array(
            'Live' => 'live',
        ));
    }

    public function onAfterDelete() {
        $this->removePageCache(array(
            'Stage' => 'stage',
            'Live' => 'live',
        ));
        $this->refreshPageCache(array(
            'Stage' => 'stage',
            'Live' => 'live',
        ));
    }

    /**
     * 
     * @param array $modes
     */
    public function refreshPageCache($modes) {
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
                    $service->refreshCachedPage();
                }
            }
        }
    }

    public function removePageCache($modes) {
        //get the unlocked cached Navigation first
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
                $service->removeCachedPage();
            }
        }
    }

    public function CachedNavigation() {
        $cachednavoff = isset($_REQUEST['cachednav'])&& $_REQUEST['cachednav']=='off'&&Director::isDev();

        if(!$cachednavoff && $this->owner->exists()) {
            if ($cachedNavigiation = Config::inst()->get('Cacheable', '_cached_navigation')) {
                if ($cachedNavigiation->isUnlocked() && $cachedNavigiation->get_completed()) {
                    return $cachedNavigiation;
                }

            }
        }
        return new ContentController($this->owner);
    }

    public function CachedData() {
        $cachednavoff = isset($_REQUEST['cachednav'])&& $_REQUEST['cachednav']=='off'&&Director::isDev();

        if(!$cachednavoff && $this->owner->exists()) {
            if ($cachedNavigiation = Config::inst()->get('Cacheable', '_cached_navigation')) {
                if($cachedNavigiation->isUnlocked() && $cachedNavigiation->get_completed()) {
                    $site_map = $cachedNavigiation->get_site_map();
                    return $site_map[$this->owner->ID];
                }
            }
        }

        return new ContentController($this->owner);
    }


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
}

/**
 * 
 * @author Deviate Ltd 2015 http://www.deviate.net.nz
 * @package silverstripe-cachable
 * @todo Add unit tests to ensure exceptions are thrown in correct circumstances
 * @todo Ditto to ensure YML config overrides work A-OK for cache_mode and server_opts
 */
class CacheableConfig {
    
    /**
     * 
     * @var string
     */
    private static $default_mode = 'memcached';
    
    /**
     * 
     * Get us the current caching mode. Useful for debugging
     * 
     * @var string
     */
    protected static $current_mode = '';
   
    /**
     * 
     * @return boolean True if Memcached extension is loaded
     */
    public static function configure_memcached() {
        $defaultOpts = array(
            'host' => 'localhost',
            'port' => 11211,
            'weight' => 1,
        );
        
        if(extension_loaded('memcached')) {
            // Use project-specific overridden opts, or the defaults
            $projectOpts = Config::inst()->get('CacheableConfig', 'server_opts');
            $serverOpts = ($projectOpts && !empty($projectOpts['memcached'])) ? $projectOpts['memcached'] : $defaultOpts;
            
            // Libmemcached is enabled.
            SS_Cache::add_backend(
                CACHEABLE_STORE_NAME,
                'Libmemcached',
                array('servers' => $serverOpts)
            );
            
            self::$current_mode = 'memcached';
            
            return true;
        }
        
        return false;
    }
    
    /**
     * 
     * @return boolean if True Memcache extension is loaded
     */
    public static function configure_memcache() {
        $defaultOpts = array(
            'host' => 'localhost',
            'port' => 11211,
            'persistent' => true,
            'weight' => 1,
            'timeout' => 5,
            'retry_interval' => 15,
            'status' => true,
            'failure_callback' => ''
        );
        
        if(class_exists('Memcache')) {
            // Use project-specific overridden opts, or the defaults
            $projectOpts = Config::inst()->get('CacheableConfig', 'server_opts');
            $serverOpts = ($projectOpts && !empty($projectOpts['memcache'])) ? $projectOpts['memcache'] : $defaultOpts;
            
            // Memcached is enabled.
            SS_Cache::add_backend(
                CACHEABLE_STORE_NAME,
                'Memcached',
                array('servers' => $serverOpts)
            );
            
            self::$current_mode = 'memcache';
            
            return true;
        }
        
        return false;
    }
    
    /**
     * 
     * @return boolean True if the filesystem is available to be used for caching.
     * @todo verify the cache store is actually cleared/built on the F/S
     */
    public static function configure_fs() {
        $cacheable_store_dir = CACHEABLE_STORE_DIR;
        if(!is_dir($cacheable_store_dir)) {
            mkdir($cacheable_store_dir);
        }
        
        $storeIsOk = (
            file_exists($cacheable_store_dir) &&
            is_writable($cacheable_store_dir)
        );
        if(!$storeIsOk) {
            return false;
        }

        SS_Cache::add_backend(CACHEABLE_STORE_NAME, 'File', array(
            'cache_dir' => $cacheable_store_dir,
        ));
        
        self::$current_mode = 'fs';
        
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
        
        // Default to F/S if one of the modes isn't playing ball
        if(!self::$confMethodName()) {
            if(!self::configure_fs()) {
                throw new CacheableException('Unable to select a cache-mode. Giving up.');
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
