<?php
/*
 *  
 * @author Deviate Ltd 2014-2015 http://www.deviate.net.nz
 * @package silverstripe-cachable
 */
class CacheableConfigTest extends SapphireTest {
    
    public function testCacheDirName() {
        // Default:
        Config::inst()->remove('CacheableConfig', 'alt_cache_dir');
        $this->assertEquals(TEMP_FOLDER . '/cacheable', CacheableConfig::cache_dir_name());
        
        // Userland - no hierarchy
        Config::inst()->update('CacheableConfig', 'alt_cache_dir', 'cacheable');
        $this->assertEquals(ASSETS_PATH . '/_cacheable', CacheableConfig::cache_dir_name());
        Config::inst()->update('CacheableConfig', 'alt_cache_dir', ' cacheable');
        $this->assertEquals(ASSETS_PATH . '/_cacheable', CacheableConfig::cache_dir_name());
        Config::inst()->update('CacheableConfig', 'alt_cache_dir', ' cacheable/');
        $this->assertEquals(ASSETS_PATH . '/_cacheable', CacheableConfig::cache_dir_name());
        Config::inst()->update('CacheableConfig', 'alt_cache_dir', '/cacheable/');
        $this->assertEquals(ASSETS_PATH . '/_cacheable', CacheableConfig::cache_dir_name());
        
        // Userland - yes hierarchy, variations
        Config::inst()->update('CacheableConfig', 'alt_cache_dir', '/foo/bar');
        $this->assertEquals(ASSETS_PATH . '/_foo/bar/cacheable', CacheableConfig::cache_dir_name());
        Config::inst()->update('CacheableConfig', 'alt_cache_dir', '/foo/bar/');
        $this->assertEquals(ASSETS_PATH . '/_foo/bar/cacheable', CacheableConfig::cache_dir_name());
        Config::inst()->update('CacheableConfig', 'alt_cache_dir', 'foo/bar/');
        $this->assertEquals(ASSETS_PATH . '/_foo/bar/cacheable', CacheableConfig::cache_dir_name());
    }
    
}

