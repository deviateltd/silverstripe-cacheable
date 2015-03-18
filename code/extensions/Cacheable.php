<?php
/**
 * Created by PhpStorm.
 * User: normann.lou
 * Date: 16/03/2015
 * Time: 2:22 AM
 */

class Cacheable extends SiteTreeExtension{
    public static $_cached_navigation;

    public function contentControllerInit($controller){
        $service = new CacheableNavigationService();
        $currentStage = Versioned::current_stage();
        $stage_mode_mapping = array(
            "Stage" => "stage",
            "Live"  => "live",
        );
        $service->set_mode($stage_mode_mapping[$currentStage]);
        $siteConfig = SiteConfig::current_site_config();
        if(!$siteConfig->exists()) {
            $siteConfig = $this->owner->getSiteConfig();
        }
        $service->set_config($siteConfig);
        if($_cached_navigation = $service->getCacheableFrontEnd()->load($service->getIdentifier())){
            Config::inst()->update('Cacheable', '_cached_navigation', $_cached_navigation);
        }
    }

    public function onAfterWrite() {
        $this->refreshPageCache(array(
            'Stage' => 'stage',
        ));
    }

    public function onAfterPublish(&$original) {
        $this->refreshPageCache(array(
            'Live' => 'live',
        ));
    }

    public function onAfterUnpublish() {
        $this->removePageCache(array(
            'Live' => 'live',
        ));
    }

    public function onAfterDelete() {
        $this->removePageCache(array(
            'Stage' => 'stage',
            'Live' => 'live',
        ));
        $this->refreshPageCache(array(
            'Stage' => 'stage',
            'Live' => 'live',
        ));
    }

    public function refreshPageCache($modes){
        //get the unlocked cached Navigation first
        $siteConfig = $this->owner->getSiteConfig();
        if(!$siteConfig->exists()) {
            $siteConfig = SiteConfig::current_site_config();
        }
        foreach($modes as $stage => $mode){
            $service = new CacheableNavigationService($mode, $siteConfig);
            $cache_frontend = $service->getCacheableFrontEnd();
            $id = $service->getIdentifier();
            $cached = $cache_frontend->load($id);
            if($cached){
                $cached_site_config = $cached->get_site_config();
                if(!$cached_site_config) {
                    $service->refreshCachedConfig();
                }
                $versioned = Versioned::get_one_by_stage(get_class($this->owner), $stage, "\"SiteTree\".\"ID\" = '".$this->owner->ID."'");
                if($versioned){
                    $service->set_model($versioned);
                    $service->refreshCachedPage();
                }
            }
        }
    }

    public function removePageCache($modes){
        //get the unlocked cached Navigation first
        $siteConfig = $this->owner->getSiteConfig();
        if(!$siteConfig->exists()) {
            $siteConfig = SiteConfig::current_site_config();
        }
        foreach($modes as $stage => $mode){
            $service = new CacheableNavigationService($mode, $siteConfig, $this->owner);
            $cache_frontend = $service->getCacheableFrontEnd();
            $id = $service->getIdentifier();
            $cached = $cache_frontend->load($id);
            if($cached){
                $cached_site_config = $cached->get_site_config();
                if(!$cached_site_config) {
                    $service->refreshCachedConfig();
                }
                $service->removeCachedPage();
            }
        }
    }

    public function CachedNavigation(){
        if($this->owner->exists()) {
            if ($cachedNavigiation = Config::inst()->get('Cacheable', '_cached_navigation')) {
                if ($cachedNavigiation->isUnlocked() && $cachedNavigiation->get_site_config()) {
                    return $cachedNavigiation;
                }

            }
        }
        return new ContentController($this->owner);
    }

    public function CachedData(){
        $cachednavoff = isset($_REQUEST['cachednav'])&& $_REQUEST['cachednav']=='off'&&Director::isDev();

        if(!$cachednavoff && $this->owner->exists()){
            if ($cachedNavigiation = Config::inst()->get('Cacheable', '_cached_navigation')) {
                if($cachedNavigiation->isUnlocked() && $cachedNavigiation->get_site_config()){
                    $site_map = $cachedNavigiation->get_site_map();
                    return $site_map[$this->owner->ID];
                }
            }
        }

        return new ContentController($this->owner);
    }


    public $start_time;
    function StartTime(){
        $this->start_time = time();
        return '<br />starting at '.$this->start_time."<br />";
    }

    public $end_time;
    function EndTime() {
        $this->end_time = time();
        return '<br />ending at '.$this->end_time."<br />";
    }

    function TimeConsumed(){
        return '<br />time consumed: '.((int)$this->end_time-(int)$this->start_time)."<br />";
    }
}