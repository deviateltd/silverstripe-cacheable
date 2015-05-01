<?php
/**
 * 
 * This BuildTask clears the f/s or in-memory cache for {@link SiteTree} and 
 * {@link SiteConfig} native SilverStripe objects. 
 * The BuildTsask should be run from the command-line as the webserver user 
 * e.g. www-data ala:
 * 
 * <code>
 *  #> sudo -u www-data ./framework/sake dev/tasks/CacheableNavigation_Clean
 * <code> 
 * 
 * @author Deviate Ltd 2015 http://www.deviate.net.nz
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
        SS_Cache::pick_backend(CACHEABLE_STORE_NAME, 'Cached_Navigation', 15);
        SS_Cache::factory(CACHEABLE_STORE_NAME)->clean('all');
        $line_break = Director::is_cli()?"\n":"<br />";
        echo $line_break . CACHEABLE_STORE_NAME . " cleaned".$line_break;
    }
}
