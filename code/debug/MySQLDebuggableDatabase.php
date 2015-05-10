<?php
/**
 * @author Deviate Ltd 2014-2015 http://www.deviate.net.nz
 * @package silverstripe-cachable
 * 
 * Just allows us to visualize the reduction in SQL queries when module is
 * enabled and cache is primed.
 */
class MySQLDebuggableDatabase extends MySQLDatabase {
    
    /**
     * 
     * @param string $sql
     * @param integer $errorLevel
     * @return SS_Query
     */
    public function query($sql, $errorLevel = E_USER_ERROR) {
        $query = parent::query($sql, $errorLevel);
        if(isset($_REQUEST['showqueries']) && Director::isDev()) {
            $count = 1+(int)Config::inst()->get('MySQLDebuggableDatabase', 'queries_count');
            Config::inst()->update('MySQLDebuggableDatabase', 'queries_count', $count);
            Debug::message(PHP_EOL . 'Query Counts: ' . $count . PHP_EOL , false);
        }

        return $query;
    }

}
