<?php
/**
 * Created by PhpStorm.
 * User: normann.lou
 * Date: 15/03/2015
 * Time: 6:49 PM
 */

class CacheableDataModelConvert extends Convert{

    static function model2cacheable($model, $cacheableClass=null){
        if(!$cacheableClass) $cacheableClass = "Cacheable".$model->ClassName;
        $cacheable = $cacheableClass::create();
        $cacheable_fields = $cacheable->get_cacheable_fields();
        foreach($cacheable_fields as $field){
            $cacheable->__set($field, $model->__get($field));
        }

//        foreach($cacheable_fields as $field){
//            debug::show($field. '=>'.$cacheable->$field);
//        }

        $cacheable_functions = $cacheable->get_cacheable_functions();
        foreach($cacheable_functions as $function){
            $cacheable->__set($function, $model->$function());
        }

//        foreach($cacheable_functions as $function){
//            debug::show($function . " -> ".$cacheable->$function);
//        }

        return $cacheable;
    }
}