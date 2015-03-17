<?php
/**
 * Created by PhpStorm.
 * User: normann.lou
 * Date: 15/03/2015
 * Time: 6:28 PM
 */

class CacheableNavigation_Clean extends BuildTask
{
    protected $description = 'clean storage completely, which is labeled as "cacheablestore"';

    public function run($request)
    {
        SS_Cache::pick_backend('cacheablestore', 'Cached_Navigation', 15);
        SS_Cache::factory('cacheablestore')->clean('all');
        $line_break = Director::is_cli()?"\n":"<br />";
        echo $line_break."cacheablestore cleaned".$line_break;
    }
}