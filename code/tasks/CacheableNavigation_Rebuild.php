<?php
/**
 * Created by PhpStorm.
 * User: normann.lou
 * Date: 15/03/2015
 * Time: 6:28 PM
 */

class CacheableNavigation_Rebuild extends BuildTask{
    protected $description = 'rebuild storage of the cacheable navigation';

    public function run($request) {
        $line_break = Director::is_cli()?"\n":"<br />";

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
            foreach($siteConfigs as $config){
                $service = new CacheableNavigationService($mode, $config);
                $service->refreshCachedConfig();
                if(class_exists('Subsite')){
                    $pages = DataObject::get("Page", "\"SubsiteID\" = '".$config->SubsiteID."'");
                }else{
                    $pages = DataObject::get("Page");
                }
                if($pages->exists()){
                    foreach($pages as $page){
                        $service->set_model($page);
                        $service->refreshCachedPage();
                    }
                }
                $service->completeBuild();
                echo $pages->count()." pages being cached in $stage mode for site ".$config->ID.$line_break;
            }
            if(class_exists('Subsite')){
                Subsite::disable_subsite_filter(false);
            }
        }

        Versioned::set_reading_mode($currentStage);
    }
}