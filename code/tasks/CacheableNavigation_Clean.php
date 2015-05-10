<?php
/**
 * 
 * This BuildTask clears the f/s or in-memory cache for {@link SiteTree} and 
 * {@link SiteConfig} native SilverStripe objects. 
 * The BuildTask should be run from the command-line as the webserver user 
 * e.g. www-data ala:
 * 
 * <code>
 *  #> sudo -u www-data ./framework/sake dev/tasks/CacheableNavigation_Clean
 * <code> 
 * 
 * @author Deviate Ltd 2014-2015 http://www.deviate.net.nz
 * @package silverstripe-cachable
 * @see {@link CacheableNavigation_Rebuild}.
 * @todo Rename task to better suit the module's new name
 */
class CacheableNavigation_Clean extends BuildTask {
    
    /**
     *
     * @var string
     */
    protected $description = 'Clears silverstripe-cacheable object cache.';

    /**
     * 
     * @param SS_HTTPRequest $request
     */
    public function run($request) {
        $newLine = CacheableNavigation_Rebuild::new_line();
        
        SS_Cache::pick_backend(CACHEABLE_STORE_NAME, CACHEABLE_STORE_FOR, CACHEABLE_STORE_WEIGHT);
        SS_Cache::factory(CACHEABLE_STORE_FOR)->clean('all');
        
        echo 'Cleanup: ' . CACHEABLE_STORE_NAME . " done." . $newLine;
    }
}
