<?php
/**
 * Created by PhpStorm.
 * User: normann.lou
 * Date: 15/03/2015
 * Time: 6:38 PM
 */

class CacheableNavigationService{
    protected $config;
    protected $model;
    protected $mode;

    function CacheableNavigationService($mode=null, $config=null, $model=null){
        if($mode) $this->mode = $mode;
        if($config) $this->config = $config;
        if($model) $this->model = $model;
    }

    function set_mode($mode){
        $this->mode = $mode;
    }

    function get_mode(){
        return $this->mode;
    }

    function set_config(SiteConfig $config){
        $this->config = $config;
    }

    function get_config(){
        return $this->config;
    }

    function set_model(DataObject $model){
        $this->model = $model;
    }

    function get_model(){
        return $this->model;
    }

    public function getIdentifier(){
        return $this->get_mode()."Site".$this->get_config()->ID;
    }

    private $_cacheable_frontend;
    public function getCacheableFrontEnd(){
        if(!$this->_cacheable_frontend){
            $for = "CacheableNavigation";
            $cache = SS_Cache::factory($for, 'Class',
                array(
                    'lifetime'=>null,
                    'cached_entity'=>'CachedNavigation',
                    'automatic_serialization'=>true,
                )
            );
            $id = $this->getIdentifier();
            if(!$cached = $cache->load($id)){
                $entity = new CachedNavigation();
                $this->_cacheable_frontend = SS_Cache::factory($for, 'Class',
                    array(
                        'lifetime'=>null,
                        'cached_entity'=>$entity,
                        'automatic_serialization'=>true,
                    )
                );
                $this->_cacheable_frontend->save($entity, $id);
            }else{
                $this->_cacheable_frontend = SS_Cache::factory($for, 'Class',
                    array(
                        'lifetime'=>null,
                        'cached_entity'=>$cached,
                        'automatic_serialization'=>true,
                    )
                );
            }
        }
        return $this->_cacheable_frontend;
    }

    public function refreshCachedConfig(){
        $cacheable = CacheableDataModelConvert::model2cacheable($this->get_config());
        // manipulating the CachedNavigation for its cached SiteConfig
        $cache_frontend = $this->getCacheableFrontEnd();
        $id = $this->getIdentifier();
        $cached = $cache_frontend->load($id);
        $cached->set_site_config($cacheable);
        $cache_frontend->remove($id);
        $cache_frontend->save($cached, $id);
    }

    public function removeCachedPage(){
        $cache_frontend = $this->getCacheableFrontEnd();
        $id = $this->getIdentifier();
        $cached = $cache_frontend->load($id);
        $site_map = $cached->get_site_map();
        $root_elements = $cached->get_root_elements();
        $model = $this->get_model();
        if(isset($root_elements[$model->ID])) {
            unset($root_elements[$model->ID]);
            $cached->set_root_elements($root_elements);
        }
        $parentCached = $site_map[$model->ID]->getParent();
        if($parentCached && $parentCached->ID && isset($site_map[$parentCached->ID])){
            $site_map[$parentCached->ID]->removeChild($cacheable->ID);
        }
        if($model->ParentID)
            if(isset($site_map[$model->ParentID])){
                $site_map[$model->ParentID]->removeChild($model->ID);
            }
        }
        if(isset($site_map[$model->ID])){
            unset($site_map[$model->ID]);
        }
        $cached->set_site_map($site_map);
        $cache_frontend->remove($id);
        $cache_frontend->save($cached, $id);
    }

    public function refreshCachedPage(){
        $model = $this->get_model();

        $cacheableClass = 'CacheableSiteTree';
        $classes = array_reverse(ClassInfo::ancestry(get_class($model)));
        foreach($classes as $class){
            if(class_exists($cachedDataClass = 'Cacheable'.$class)){
                $cacheableClass = $cachedDataClass;
                break;
            }
        }

        $cacheable = CacheableDataModelConvert::model2cacheable($model, $cacheableClass);
        $cache_frontend = $this->getCacheableFrontEnd();
        $id = $this->getIdentifier();
        $cached = $cache_frontend->load($id);
        $site_map = $cached->get_site_map();
        if(isset($site_map[$cacheable->ID])){
            $parentCached = $site_map[$cacheable->ID]->getParent();
            if($parentCached && $parentCached->ID && isset($site_map[$parentCached->ID])){
                $site_map[$parentCached->ID]->removeChild($cacheable->ID);
            }
            $children = $site_map[$cacheable->ID]->getAllChildren();
            if(count($children)){
                foreach($children as $child){
                    $cacheable->addChild($child);
                    $child->setParent($cacheable);
                }
            }
            unset($site_map[$cacheable->ID]);
        }

        $root_elements = $cached->get_root_elements();
        if($cacheable->ParentID){
            if(!isset($site_map[$cacheable->ParentID])){
                $parent = new CacheableSiteTree();
                $parent->ID = $cacheable->ParentID;
                $parent->addChild($cacheable);
                $cacheable->setParent($parent);
                $site_map[$cacheable->ParentID] = $parent;

            }else{
                $site_map[$cacheable->ParentID] -> addChild($cacheable);
                $cacheable->setParent($site_map[$cacheable->ParentID]);
            }
            if(isset($root_elements[$cacheable->ID])) unset($root_elements[$cacheable->ID]);
        }else{
            $root_elements[$cacheable->ID] = $cacheable;
        }
        $site_map[$cacheable->ID] = $cacheable;
        $cached->set_site_map($site_map);
        $cached->set_root_elements($root_elements);
        $cache_frontend->remove($id);
        $cache_frontend->save($cached, $id);
    }
}