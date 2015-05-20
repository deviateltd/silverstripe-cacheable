<?php
/**
 * 
 * This service provides all the reload, refresh and remove features needed to 
 * ensure page and siteconfig caches are well-maintained. 
 * 
 * @author Deviate Ltd 2014-2015 http://www.deviate.net.nz
 * @package silverstripe-cachable
 * @see CacheableNavigationService::callback_spl()
 * @todo Rename class and lose all notion of "Navigation" from this module.
 * @todo Convert underscored method/property names to use SS coding standards.
 */
spl_autoload_register('CacheableNavigationService::callback_spl');

class CacheableNavigationService {
    
    /**
     *
     * @var SiteConfig
     */
    protected $config;
    
    /**
     *
     * @var DataObject
     */
    protected $model;
    
    /**
     *
     * @var string
     */
    protected $mode;
    
    /**
     *
     * Internal class-cache for the Model-cache. This is for performance
     * so we needn't re-call Zend_Cache_Core::load() and suffer exponential increases
     * in peak memory.
     * 
     * @var Zend_Cache_Frontend_Class
     */
    protected $_cachedModel;
    
    /**
     *
     * Internal class-cache for the SiteConfig-cache. This is for performance
     * so we needn't re-call Zend_Cache_Core::load() and suffer exponential increases
     * in peak memory.
     * 
     * @var Zend_Cache_Frontend_Class
     */
    protected $_cachedConfig;

    /**
     *
     * @var Zend_Cache_Frontend_Class
     */
    private $_cacheable_frontend;

    /**
     * 
     * @param string $mode e.g. 'live'
     * @param SiteConfig $config
     * @param DataObject $model
     */
	public function __construct($mode = null, $config = null, $model = null) {
        if($mode) {
            $this->mode = $mode;
        }
        if($config) {
            $this->config = $config;
        }
        if($model) {
            $this->model = $model;
        }
    }
    
    /**
     * This callback is run before PHP invokes its class-level unserialize()
     * function and for reasons beyond the realms of human understanding (B.R.O.H.A), the
     * module is unable to do anything without this static unless you want
     * {@link Zend_Cache_Frontend_File} to be returned as an instance of _PHP_Incomplete_Class_ 
     * instead.
     * 
     * @see // See: http://zend-framework-community.634137.n4.nabble.com/Zend-Cache-dosent-seem-to-be-returning-the-object-I-saved-td646246.html
     * @return void
     */
    public static function callback_spl() {
        require_once dirname(__FILE__) . '/../../../' . THIRDPARTY_DIR . '/Zend/Cache/Frontend/Class.php';
        if(CacheableConfig::current_cache_mode() === 'memcached') {
            require_once dirname(__FILE__) . '/../../../' . THIRDPARTY_DIR . '/Zend/Cache/Backend/Libmemcached.php';
        }
        if(CacheableConfig::current_cache_mode() === 'apc') {
            require_once dirname(__FILE__) . '/../../../' . THIRDPARTY_DIR . '/Zend/Cache/Backend/Apc.php';
        }
    }

	public function set_mode($mode) {
        $this->mode = $mode;
    }

	public function get_mode() {
        return $this->mode;
    }

	public function set_config(SiteConfig $config) {
        $this->config = $config;
    }

	public function get_config() {
        return $this->config;
    }

	public function set_model(DataObject $model) {
        $this->model = $model;
    }

	public function get_model() {
        return $this->model;
    }
    
    /**
     * 
     * Simple getter to fetch the class-cache of the cached model.
     * 
     * @return type
     */
    public function getClassCacheForModel() {
        return $this->_cachedModel;
    }
    
    /**
     * 
     * Simple getter to fetch the class-cache of the cached model.
     * 
     * @return type
     */
    public function getClassCacheForConfig() {
        return $this->_cachedConfig;
    }

    /**
     * Generates a string-identifier for loading a cache.
     * 
     * @return string
     */
    public function getIdentifier() {
        $configID = $this->get_config() ? $this->get_config()->ID : 1;
        return ucfirst($this->get_mode()) . "Site" . $configID;
    }
    
    /**
     * 
     * Creates and returns an instance of {@link Zend_Cache_Frontend_Class} a proxy
     * to the picked backend cache-class and  saves a data-structure to it which 
     * _is_ the cached object.
     * 
     * @return CachedNavigation
     */
    public function getCacheableFrontEnd() {
        if(!$this->_cacheable_frontend) {
            $for = CACHEABLE_STORE_FOR;
            $id = $this->getIdentifier();
            $cache = SS_Cache::factory($for, 'Class', array(
                'lifetime'=>null,
                'cached_entity'=>'CachedNavigation',
                'automatic_serialization'=>true
            ));
            
            if(!$cached = $cache->load($id)) {
                $entity = new CachedNavigation();
                $this->_cacheable_frontend = SS_Cache::factory($for, 'Class', array(
                    'lifetime'=>null,
                    'cached_entity'=>$entity,
                    'automatic_serialization'=>true
                ));
                $this->_cacheable_frontend->save($entity, $id, array(self::get_default_cache_tag()));
            } else {
                $this->_cacheable_frontend = SS_Cache::factory($for, 'Class', array(
                    'lifetime'=>null,
                    'cached_entity'=>$cached,
                    'automatic_serialization'=>true
                ));
            }
       }
        
        return $this->_cacheable_frontend;
    }

    /**
     * 
     * "Refreshes" a cache-entry for a {@link SiteConfig} object.
     * 
     * @return boolean  false if the underlying calls to {@link Zend_Cache_Core::load()}
     *                  or {@link Zend_Cache_Core::save()} fail for any reason.
     */
    public function refreshCachedConfig() {
        $cacheable = CacheableDataModelConvert::model2cacheable($this->get_config());
        // manipulating the CachedNavigation for its cached SiteConfig
        $frontend = $this->getCacheableFrontEnd();
        $id = $this->getIdentifier();
        if(!$this->getClassCacheForConfig()) {
            if(!$cached = $frontend->load($id)) {
                return false;
            }
            $this->_cachedConfig = $cached;
        }
        
        $this->getClassCacheForConfig()->set_site_config($cacheable);
        $frontend->remove($id);
        
        return $frontend->save($this->getClassCacheForConfig(), $id, array(self::get_default_cache_tag()));
    }

    /**
     * 
     * "Removes"  a cache-entry for the object (page) given in $this->get_model().
     * 
     * @return boolean  false if the underlying calls to {@link Zend_Cache_Core::load()}
     *                  or {@link Zend_Cache_Core::save()} fail for any reason.
     */
    public function removeCachedPage() {
        $frontend = $this->getCacheableFrontEnd();
        $id = $this->getIdentifier();
        if(!$this->getClassCacheForModel()) {
            if(!$cached = $frontend->load($id)) {
                return false;
            }
            $this->_cachedModel = $cached;
        }
        
        $site_map = $this->getClassCacheForModel()->get_site_map();
        $root_elements = $this->getClassCacheForModel()->get_root_elements();
        $model = $this->get_model();
        if(isset($root_elements[$model->ID])) {
            // Remove the object from the sitemap
            unset($root_elements[$model->ID]);
            $this->getClassCacheForModel()->set_root_elements($root_elements);
        }
        
        if(isset($site_map[$model->ID])) {
            $parentCached = $site_map[$model->ID]->getParent();
            if($parentCached && $parentCached->ID && isset($site_map[$parentCached->ID])) {
                $site_map[$parentCached->ID]->removeChild($model->ID);
            }
        }
        
        if($model->ParentID) {
            if(isset($site_map[$model->ParentID])) {
                $site_map[$model->ParentID]->removeChild($model->ID);
            }
        }
        
        if(isset($site_map[$model->ID])) {
            unset($site_map[$model->ID]);
        }
        
        $this->getClassCacheForModel()->set_site_map($site_map);
        $frontend->remove($id);
        
        return $frontend->save(
                    $this->getClassCacheForModel(), 
                    $id, 
                    array(self::get_default_cache_tag())
                );
    }

    /**
     * 
     * "Refreshes" (Removes and builds) a cache-entry for the object (page) given
     * in $this->get_model().
     * 
     * Note: If a cache entry already exists for a given object ID, it is removed 
     * and replaced.
     * 
     * @return boolean  false if the underlying calls to {@link Zend_Cache_Core::load()}
     *                  or {@link Zend_Cache_Core::save()} fail for any reason.
     */
    public function refreshCachedPage() {
        $model = $this->get_model();
        $cacheableClass = 'CacheableSiteTree';
        $classes = array_reverse(ClassInfo::ancestry(get_class($model)));
        foreach($classes as $class) {
            if(class_exists($cachedDataClass = 'Cacheable' . $class)) {
                $cacheableClass = $cachedDataClass;
                break;
            }
        }

        $cacheable = CacheableDataModelConvert::model2cacheable($model, $cacheableClass);
        $frontend = $this->getCacheableFrontEnd();
        $id = $this->getIdentifier();
        
        if(!$this->getClassCacheForModel()) {
            if(!$cached = $frontend->load($id)) {
                return false;
            }
            $this->_cachedModel = $cached;
        }

        $site_map = $this->getClassCacheForModel()->get_site_map();
        if(isset($site_map[$cacheable->ID])) {
            $parentCached = $site_map[$cacheable->ID]->getParent();
            if($parentCached && $parentCached->ID && isset($site_map[$parentCached->ID])) {
                $site_map[$parentCached->ID]->removeChild($cacheable->ID);
            }
            
            $children = $site_map[$cacheable->ID]->getAllChildren();
            if(count($children)) {
                foreach($children as $child) {
                    $cacheable->addChild($child);
                    $child->setParent($cacheable);
                }
            }
            
            unset($site_map[$cacheable->ID]);
        }
        
        $root_elements = $this->getClassCacheForModel()->get_root_elements();
        if($cacheable->ParentID) {
            if(!isset($site_map[$cacheable->ParentID])) {
                $parent = new CacheableSiteTree();
                $parent->ID = $cacheable->ParentID;
                $parent->addChild($cacheable);
                $cacheable->setParent($parent);
                $site_map[$cacheable->ParentID] = $parent;

            } else {
                $site_map[$cacheable->ParentID] -> addChild($cacheable);
                $cacheable->setParent($site_map[$cacheable->ParentID]);
            }
            
            if(isset($root_elements[$cacheable->ID])) {
                unset($root_elements[$cacheable->ID]);
            }
        } else {
            $root_elements[$cacheable->ID] = $cacheable;
        }
        
        $site_map[$cacheable->ID] = $cacheable;
        $this->getClassCacheForModel()->set_site_map($site_map);
        $this->getClassCacheForModel()->set_root_elements($root_elements);
        $frontend->remove($id);
        
        return $frontend->save(
                    $this->getClassCacheForModel(), 
                    $id, 
                    array(self::get_default_cache_tag())
                );
    }

    /**
     * 
     * Tell the cache frontend that construction of the cache for the given identifier, 
     * and model is complete, and save this status back to the cache.
     * 
     * @return boolean
     */
    public function completeBuildModel() {
        $frontend = $this->getCacheableFrontEnd();
        $id = $this->getIdentifier();
        if(!$this->getClassCacheForModel()) {
            if(!$cached = $frontend->load($id)) {
                return false;
            }
            $this->_cachedModel = $cached;
        }
        
        $this->getClassCacheForModel()->set_completed(true);
        
        return $frontend->save(
                    $this->getClassCacheForModel(), 
                    $id, 
                    array(self::get_default_cache_tag())
                );
    }
    
    /**
     * 
     * Tell the cache frontend that construction of the cache for the given identifier, 
     * and model is complete, and save this status back to the cache.
     * 
     * @return boolean
     */
    public function completeBuildConfig() {
        $frontend = $this->getCacheableFrontEnd();
        $id = $this->getIdentifier();
        if(!$this->getClassCacheForConfig()) {
            if(!$cached = $frontend->load($id)) {
                return false;
            }
            $this->_cachedConfig = $cached;
        }
        
        $this->getClassCacheForConfig()->set_completed(true);
        
        return $frontend->save(
                    $this->getClassCacheForConfig(), 
                    $id, 
                    array(self::get_default_cache_tag())
                );
    }
    
    /**
     * Allows us to clear a specific cache (particulalrly test-caches) based on 
     * Zend_Cache's tagging system.
     * 
     * @return string
     */
    private static function get_default_cache_tag() {
        return CacheableConfig::is_running_test() ? CACHEABLE_STORE_TAG_DEFAULT_TEST : CACHEABLE_STORE_TAG_DEFAULT;
    }
}
