<?php
/**
 * 
 * Gives {@link SiteConfig} objects caching abilities.
 * 
 * @author Deviate Ltd 2015 http://www.deviate.net.nz
 * @package silverstripe-cachable
 */
class SiteConfigCacheable extends DataExtension {
    
    public function onAfterWrite() {
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
