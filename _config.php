<?php
/**
 * 
 * @author Deviate Ltd 2014-2015 http://www.deviate.net.nz
 * @package silverstripe-cachable
 * 
 * Configure the module's storage:
 * 
 * The default is to use "file" for the cache store via {@link Zend_Cache_Backend_File}, 
 * but this can be overriden in YML config. See the README for more options.
 */

// On smaller CWP setups, allow the module as much RAM as it can offer
// See: https://www.cwp.govt.nz/guides/technical-faq/php-configuration/ while logged-in
if(defined('CWP_ENVIRONMENT') && intval(ini_get('memory_limit')) < 256) {
    ini_set('memory_limit', '256M'); // upper limit of CWP "small" instances
}

define('CACHEABLE_STORE_DIR_NAME', 'cacheable');
define('CACHEABLE_STORE_DIR_TEST', TEMP_FOLDER . DIRECTORY_SEPARATOR . 'cacheable_tests');
define('CACHEABLE_STORE_NAME', 'cacheablestore');
define('CACHEABLE_STORE_FOR', 'Cacheable');
define('CACHEABLE_STORE_TAG_DEFAULT', 'cacheable_tag_nav'); // Default Zend tag name for this cache 
define('CACHEABLE_STORE_TAG_DEFAULT_TEST', 'cacheable_tag_nav_test');
define('CACHEABLE_STORE_WEIGHT', 1000);

// If project YML specifies an alternate cache folder, deal with it.
if($altCacheDir = Config::inst()->get('CacheableConfig', 'alt_cache_dir')) {
    $cacheDir = rtrim($altCacheDir, '/') 
            . DIRECTORY_SEPARATOR 
            . CACHEABLE_STORE_DIR_NAME 
            . DIRECTORY_SEPARATOR
            . getTempFolderUsername(); 
} else {
    $cacheDir = TEMP_FOLDER
            . DIRECTORY_SEPARATOR
            . CACHEABLE_STORE_DIR_NAME;
}

define('CACHEABLE_STORE_DIR', $cacheDir);

CacheableConfig::configure();
SS_Cache::pick_backend(CACHEABLE_STORE_NAME, CACHEABLE_STORE_FOR, CACHEABLE_STORE_WEIGHT);
