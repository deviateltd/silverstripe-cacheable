<?php
/**
 * 
 * @author Deviate Ltd 2015 http://www.deviate.net.nz
 * @package silverstripe-cachable
 * 
 * Configure the module's storage:
 * 
 * The default is to use memcached for the cache store, but this can be overriden in 
 * project YML config. You can also optionally override the default "server" array 
 * normally passed to {@link SS_Cache} and Zend_Cache. See the README.
 */

define('CACHEABLE_STORE_DIR', TEMP_FOLDER . DIRECTORY_SEPARATOR . 'cacheable-navigation');
define('CACHEABLE_STORE_NAME', 'cacheablestore');

CacheableConfig::configure();
SS_Cache::pick_backend(CACHEABLE_STORE_NAME, 'Cached_Navigation', 15);
