<?php
/**
 * 
 * @author Deviate Ltd 2014-2015 http://www.deviate.net.nz
 * @package silverstripe-cachable
 * 
 * Configure the module's storage:
 * 
 * The default is to use memcached for the cache store, but this can be overriden in 
 * project YML config. You can also optionally override the default "server" array 
 * normally passed to {@link SS_Cache} and {@link Zend_Cache}. See the README.
 */

// On CWP setups, allow the module as much RAM as it can offer
// See: https://www.cwp.govt.nz/guides/technical-faq/php-configuration/
if(defined(CWP_ENVIRONMENT)) {
    ini_set('memory_limit', '256M');
}

define('CACHEABLE_STORE_DIR', TEMP_FOLDER . DIRECTORY_SEPARATOR . 'cacheable');
define('CACHEABLE_STORE_DIR_TEST', TEMP_FOLDER . DIRECTORY_SEPARATOR . 'cacheable_tests');
define('CACHEABLE_STORE_NAME', 'cacheablestore');
define('CACHEABLE_STORE_FOR', 'Cacheable');
define('CACHEABLE_STORE_TAG_DEFAULT', 'cacheable_tag_nav'); // Default Zend tag name for this cache 
define('CACHEABLE_STORE_TAG_DEFAULT_TEST', 'cacheable_tag_nav_test');
define('CACHEABLE_STORE_WEIGHT', 1000);

CacheableConfig::configure();
SS_Cache::pick_backend(CACHEABLE_STORE_NAME, CACHEABLE_STORE_FOR, CACHEABLE_STORE_WEIGHT);
