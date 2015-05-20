<?php
/**
 *  
 * @author Deviate Ltd 2014-2015 http://www.deviate.net.nz
 * @package silverstripe-cachable
 * @todo have tests run in all possible cache-backends. Defaults to 'File' at
 * present.
 */
class CacheableNavigationServiceTest extends SapphireTest {
    
    /**
     * 
     * @var string
     */
    protected static $fixture_file = 'fixtures/CacheableNavigationServiceTest.yml';
    
    /**
     * Cleanup after ourselves
     */
    public function tearDown() {
        parent::tearDown();
        
        // Cleanup our test-only caches
        SS_Cache::factory(CACHEABLE_STORE_FOR)->clean(
            Zend_Cache::CLEANING_MODE_MATCHING_TAG, 
            array(CACHEABLE_STORE_TAG_DEFAULT_TEST)
        );
    }
    
    /**
     * 
     */
    public function testRefreshCachedPage() {
        $config = $this->objFromFixture('SiteConfig', 'default');
        $model = $this->objFromFixture('SiteTree', 'test-page-1');
        $service = new CacheableNavigationService('Live', $config, $model);
        
        // Cache should be empty
        $this->assertNull($service->getClassCacheForModel());
        
        // Populate the cache and re-test
        $this->assertTrue($service->refreshCachedPage());
        $this->assertNotNull($service->getClassCacheForModel());
        $this->assertInstanceOf('CachedNavigation', $service->getClassCacheForModel());
        
        $cachedObject = $service->getClassCacheForModel()->get_site_map();
        $this->assertCount(1, $cachedObject);
        // Why is $cachedObject not zero-indexed?
        $this->assertInstanceOf('CacheableSiteTree', $cachedObject[1]);
        $this->assertEquals('This is a simple page to be cached', $cachedObject[1]->Title);
    }
    
    /**
     * 
     */
    public function testRemoveCachedPage() {
        $model = $this->objFromFixture('SiteTree', 'test-page-1');
        $service = new CacheableNavigationService('Live', null, $model);
        
        // Entire cache should be empty
        $this->assertNull($service->getClassCacheForModel());
        
        // Populate the cache and re-test
        $this->assertTrue($service->refreshCachedPage());
        $this->assertNotNull($service->getClassCacheForModel());
        $this->assertInstanceOf('CachedNavigation', $service->getClassCacheForModel());
       
        $cachedObject = $service->getClassCacheForModel()->get_site_map();
        // Why is $cachedObject not zero-indexed?
        $this->assertCount(1, $cachedObject);
        $this->assertInstanceOf('CacheableSiteTree', $cachedObject[1]);
        
        // So far so good, now remove it:
        $this->assertTrue($service->removeCachedPage());
        
        // Cache should be devoid of SiteTree-esque objects
        $this->assertContainsOnlyInstancesOf('CacheableSiteConfig', $service->getClassCacheForModel());
    }
    
    /**
     * 
     */
    public function testRefreshCachedConfig() {
        $config = $this->objFromFixture('SiteConfig', 'default');
        $service = new CacheableNavigationService('Live', $config, null);
        
        // Cache should be empty
        $this->assertNull($service->getClassCacheForConfig());
        
        // Populate the cache and re-test
        $this->assertTrue($service->refreshCachedConfig());
        $this->assertNotNull($service->getClassCacheForConfig());
        $this->assertInstanceOf('CachedNavigation', $service->getClassCacheForConfig());
       
        $cachedObject = $service->getClassCacheForConfig()->get_site_config();
        $this->assertInstanceOf('CacheableSiteConfig', $cachedObject);
        $this->assertEquals('Default', $cachedObject->Title);
    }
    
    /**
     * 
     */
    public function testCompleteBuildModel() {
        $model = $this->objFromFixture('SiteTree', 'test-page-1');
        $service = new CacheableNavigationService('Live', null, $model);
        
        // Cache should be empty
        $cachable = $service->getCacheableFrontEnd()->load($service->getIdentifier());
        $this->assertFalse($cachable->get_completed());
        
        // Populate the cache with a page
        $service->refreshCachedPage();
        
        // Set it to complete and re-test
        $service->completeBuildModel();
        $cachable = $service->getCacheableFrontEnd()->load($service->getIdentifier());
        $this->assertTrue($cachable->get_completed());
    }
    
    /**
     * 
     */
    public function testCompleteBuildConfig() {
        $config = $this->objFromFixture('SiteConfig', 'default');
        $service = new CacheableNavigationService('Live', $config);
        
        // Cache should be empty
        $cachable = $service->getCacheableFrontEnd()->load($service->getIdentifier());
        $this->assertFalse($cachable->get_completed());
        
        // Populate the cache with some config
        $service->refreshCachedConfig();
        
        // Set it to complete and re-test
        $service->completeBuildConfig();
        $cachable = $service->getCacheableFrontEnd()->load($service->getIdentifier());
        $this->assertTrue($cachable->get_completed());
    }
    
}
