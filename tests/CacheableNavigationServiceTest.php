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
     * Cleanup
     */
    public function setUp() {
        parent::setUp();
        
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
        $model = $this->objFromFixture('SiteTree', 'servicetest-page-1');
        $service = new CacheableNavigationService('Live', $config, $model);
        
        // Cache should be empty
        $this->assertNull($service->_cached);
        
        // Populate the cache and re-test
        $this->assertTrue($service->refreshCachedPage());
        $this->assertNotNull($service->_cached);
        $this->assertInstanceOf('CachedNavigation', $service->_cached);
        
        $cachedObject = $service->_cached->get_site_map();
        $this->assertCount(1, $cachedObject);
        // $cachedObject not zero-indexed as array keys are taken for SS-generated ID's from each fixture
        $this->assertInstanceOf('CacheableSiteTree', $cachedObject[1]);
        $this->assertEquals('This is a simple page to be cached', $cachedObject[1]->Title);
    }
    
    /**
     * 
     */
    public function testRemoveCachedPage() {
        $model = $this->objFromFixture('SiteTree', 'servicetest-page-1');
        $service = new CacheableNavigationService('Live', null, $model);
        
        // Entire cache should be empty
        $this->assertNull($service->_cached);
        
        // Populate the cache and re-test
        $this->assertTrue($service->refreshCachedPage());
        $this->assertNotNull($service->_cached);
        $this->assertInstanceOf('CachedNavigation', $service->_cached);
       
        $cachedObject = $service->_cached->get_site_map();
        // $cachedObject not zero-indexed as array keys are taken for SS-generated ID's from each fixture
        $this->assertCount(1, $cachedObject);
        $this->assertInstanceOf('CacheableSiteTree', $cachedObject[1]);
        
        // So far so good, now remove it:
        $this->assertTrue($service->removeCachedPage());
        
        // Cache should be devoid of SiteTree-esque objects
        $this->assertContainsOnlyInstancesOf('CacheableSiteConfig', $service->_cached);
    }
    
    /**
     * 
     */
    public function testRefreshCachedConfig() {
        $config = $this->objFromFixture('SiteConfig', 'default');
        $service = new CacheableNavigationService('Live', $config, null);
        
        // Cache should be empty
        $this->assertNull($service->_cached);
        
        // Populate the cache and re-test
        $this->assertTrue($service->refreshCachedConfig());
        $this->assertNotNull($service->_cached);
        $this->assertInstanceOf('CachedNavigation', $service->_cached);
       
        $cachedObject = $service->_cached->get_site_config();
        $this->assertInstanceOf('CacheableSiteConfig', $cachedObject);
        $this->assertEquals('Default', $cachedObject->Title);
    }
    
    /**
     * 
     */
    public function testCompleteBuild() {
        $model = $this->objFromFixture('SiteTree', 'servicetest-page-1');
        $service = new CacheableNavigationService('Live', null, $model);
        
        // Cache should be empty
        $cachable = $service->getCacheableFrontEnd()->load($service->getIdentifier());
        $this->assertFalse($cachable->get_completed());
        
        // Populate the cache with a page
        $service->refreshCachedPage();
        
        // Set it to complete and re-test
        $service->completeBuild();
        $cachable = $service->getCacheableFrontEnd()->load($service->getIdentifier());
        $this->assertTrue($cachable->get_completed());
    }
    
}
