<?php
/**
 * Created by PhpStorm.
 * User: normann.lou
 * Date: 15/03/2015
 * Time: 6:57 PM
 */

abstract class CacheableData extends ViewableData{
    private static $cacheable_fields = array(
        "ID",
        "Title"
    );

    private static $cacheable_functions = array();


    public function get_cacheable_fields(){
        return $this->config()->cacheable_fields;
    }

    public function get_cacheable_functions(){
        return $this->config()->cacheable_functions;
    }

    public function CachedNavigation(){
        return Config::inst()->get('Cacheable', '_cached_navigation');
    }

    abstract public function canView($member = null);

    /**
     * Returns true if this object "exists", i.e., has a sensible value.
     * The default behaviour for a DataObject is to return true if
     * the object exists in the database, you can override this in subclasses.
     *
     * @return boolean true if this object exists
     */
    public function exists() {
        return (isset($this->ID) && $this->ID > 0);
    }

    public function extendedCan($methodName, $member) {
        $results = $this->extend($methodName, $member);
        if($results && is_array($results)) {
            // Remove NULLs
            $results = array_filter($results, function($v) {return !is_null($v);});
            // If there are any non-NULL responses, then return the lowest one of them.
            // If any explicitly deny the permission, then we don't get access
            if($results) return min($results);
        }
        return null;
    }

    public function NonCachedData(){
        return DataObject::get_by_id($this->ClassName, $this->ID);
    }

    function debug() {
        $message = "<h3>cacheable data: ".get_class($this)."</h3>\n<ul>\n";
        $message .= "\t<li>Cached Fields:\n<ul>\n";
        foreach($this->get_cacheable_fields() as $field){
            $message .= "\t<li>$field: ". $this->$field . "</li>\n";
        }
        $message .= "</ul>\n". "</li>\n";

        $message .= "\t<li>Cached Functions:\n<ul>\n";
        foreach($this->get_cacheable_functions() as $function){
            $message .= "\t<li>$function: ". $this->$function . "</li>\n";
        }
        $message .= "</ul>\n". "</li>\n";
        $message .= "</ul>\n";

        return $message;
    }
}