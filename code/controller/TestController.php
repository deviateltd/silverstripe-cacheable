<?php
/**
 * Created by PhpStorm.
 * User: normann.lou
 * Date: 17/03/2015
 * Time: 8:31 PM
 */

class TestController extends Controller{
    function index(){
        $currentStage = Versioned::current_stage();
        $stage_mode_mapping = array(
            //"Stage" => "stage",
            "Live"  => "live",
        );
        $siteConfig = SiteConfig::current_site_config();

        foreach($stage_mode_mapping as $stage => $mode) {
            Versioned::set_reading_mode('Stage.'.$stage);

            $service = new CacheableNavigationService($mode, $siteConfig);
            $cache = $service->getCacheableFrontEnd();
            $id = $service->getIdentifier();
            $cached = $cache->load($id);
            Debug::show($cached);
        }
        echo 'done';
        Versioned::set_reading_mode($currentStage);
        exit;
    }
}