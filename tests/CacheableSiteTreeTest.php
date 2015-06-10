<?php
/*
 *  
 * @author Deviate Ltd 2014-2015 http://www.deviate.net.nz
 * @package silverstripe-cachable
 */
class CacheableSiteTreeTest extends SapphireTest {
    
    /**
     * 
     * @var string
     */
    protected static $fixture_file = 'fixtures/CacheableSiteTreeTest.yml';
    
    /**
     * Cleanup after ourselves
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
     * Issue #24 described a scenario where updated SiteTree objects
     * would become appended to the cached list and not updated in-place as expected.
     * 
     * Note: This is _not_ a proper _unit_ test, it's more akin to a functional test:
     *  - It doesn't directly exercise {@link CacheableSiteTree::removeChild()}.
     *  - removeChild() is called while populating/updating the object-cache by {@link CacheableNavigationService::refreshCachedPage()}
     */
    public function testRemoveChild() {
        $config = $this->objFromFixture('SiteConfig', 'default');
        $parent = $this->objFromFixture('SiteTree', 'sitetreetest-page-1');
        $children = $parent->Children()->toArray();
        $models = array_merge(array($parent), $children);
        
        // Fake a rebuild task - Populate the object-cache with our fixture data
        $i = 0;
        foreach($models as $model) {
            $i++;
            $varKey = "page" . $i;
            $$varKey = $model;
            $service = new CacheableNavigationService('Live', $config, $model);
            $service->refreshCachedPage();
        }
        
        // Fetch the cache
        $objCache = $service->getObjectCache();
        $siteMap = $objCache->get_site_map();
        $childrenArr = $siteMap[1]->getAllChildren();
        
        // Array position check #1
        $firstItem = reset($childrenArr);
        $this->assertCount(2, $childrenArr);
        $this->assertEquals('This is a child page 1', $firstItem->Title);
        
        // Update only the first child with some new data so we can later check it stays in the same position
        $page2->ShowInMenu = 0;
        $page2->write();
        $page2->publish('Stage', 'Live');
        
        // Update the object-cache with "CMS updated" data
        $service = new CacheableNavigationService('Live', $config, $page2);
        $service->refreshCachedPage(false); // Don't unset child items. Re-use array key.
        
        // Re-fetch the cache
        $objCache = $service->getObjectCache();
        $siteMap = $objCache->get_site_map();
        $childrenArr = $siteMap[1]->getAllChildren();

        // Array position check #2 - ensure that the recently updated item hasn't been
        // appended, but is still in the correct (same) position within the Children array
        $firstItem = reset($childrenArr);
        $this->assertCount(2, $childrenArr);
        $this->assertEquals('This is a child page 1', $firstItem->Title);
    }
    
}

