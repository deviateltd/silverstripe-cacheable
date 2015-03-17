<?php
/**
 * Created by PhpStorm.
 * User: normann.lou
 * Date: 17/03/2015
 * Time: 11:18 AM
 */

class SiteConfigCacheable extends DataExtension {
    public function onAfterWrite(){
        $stage_mode_mapping = array(
            "Stage" => "stage",
            "Live"  => "live",
        );
        foreach($stage_mode_mapping as $stage => $mode){
            $service = new CacheableNavigationService($mode, $this->owner);
            $service->refreshCachedConfig();
        }
    }
}