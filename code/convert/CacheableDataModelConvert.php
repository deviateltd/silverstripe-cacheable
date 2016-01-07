<?php
/**
 * 
 * @author Deviate Ltd 2014-2015 http://www.deviate.net.nz
 * @package silverstripe-cachable
 */
class CacheableDataModelConvert extends Convert
{

    /**
     * 
     * Dynamically augments any $cacheableClass object with 
     * methods and properties of $model.
     * 
     * Warning: Uses PHP magic methods __get() and __set().
     * 
     * @param DataObject $model
     * @param string $cacheableClass
     * @return ViewableData $cacheable
     */
    public static function model2cacheable(DataObject $model, $cacheableClass = null)
    {
        if (!$cacheableClass) {
            $cacheableClass = "Cacheable" . $model->ClassName;
        }
        
        $cacheable = $cacheableClass::create();
        $cacheable_fields = $cacheable->get_cacheable_fields();
        foreach ($cacheable_fields as $field) {
            $cacheable->__set($field, $model->__get($field));
        }

        $cacheable_functions = $cacheable->get_cacheable_functions();
        foreach ($cacheable_functions as $function) {
            /*
             * Running tests inside a project with its own YML config for 
             * cacheable_fields and cacheable_functions will fail if we don't check first
             */
            if ($model->hasMethod($function)) {
                $cacheable->__set($function, $model->$function());
            }
        }

        return $cacheable;
    }
}
