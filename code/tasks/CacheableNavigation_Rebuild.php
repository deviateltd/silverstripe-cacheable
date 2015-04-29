<?php
/**
 * 
 * This BuildTask pre-primes the object cache.
 * 
 * @author Deviate Ltd 2015 http://www.deviate.net.nz
 * @package silverstripe-cachable
 */
class CacheableNavigation_Rebuild extends BuildTask {
    
    /**
     *
     * @var string
     */
    protected $description = 'rebuild storage of the cacheable navigation';

    /**
     * 
     * @param SS_HTTPRequest $request
     * @return void
     */
    public function run($request) {
        $line_break = Director::is_cli() ? PHP_EOL : "<br />";

        $currentStage = Versioned::current_stage();
        $stage_mode_mapping = array(
            "Stage" => "stage",
            "Live"  => "live",
        );

        foreach($stage_mode_mapping as $stage => $mode){
            Versioned::set_reading_mode('Stage.'.$stage);
            if(class_exists('Subsite')){
                Subsite::disable_subsite_filter(true);
                Config::inst()->update("CacheableSiteConfig", 'cacheable_fields', array('SubsiteID'));
                Config::inst()->update("CacheableSiteTree", 'cacheable_fields', array('SubsiteID'));
            }
            
            $siteConfigs = DataObject::get('SiteConfig');
            foreach($siteConfigs as $config) {                
                $service = new CacheableNavigationService($mode, $config);
                $service->refreshCachedConfig();
                
                if(class_exists('Subsite')){
                    $pages = DataObject::get("Page", "\"SubsiteID\" = '".$config->SubsiteID."'");
                }else{
                    $pages = DataObject::get("Page");
                }
                
                if($pages->exists()) {
                    $count = 0;
                    foreach($pages as $page){
                        $count++;
                        $service->set_model($page);
                        $percent = $this->percentageComplete($count, $pages->count());
                        echo 'Caching: ' . $page->Title . ' (' . $percent . ')' . $line_break;
                        $service->refreshCachedPage();
                    }
                }
                
                $service->completeBuild();
                echo $pages->count()." pages cached in $stage mode for subsite " . $config->ID . $line_break;
            }
            
            if(class_exists('Subsite')){
                Subsite::disable_subsite_filter(false);
            }
        }

        Versioned::set_reading_mode($currentStage);
    }
        
    /**
     * 
     * Generate a percentage of how complete the cache rebuild is.
     * 
     * @param number $count
     * @param number $total
     * @return string
     */
    public function percentageComplete($count, $total) {
        $calc = (((int)$count / (int)$total) * 100);
        return round($calc, 1) . '%';
    }
}
