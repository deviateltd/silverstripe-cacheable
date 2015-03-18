<?php
/**
 * Created by PhpStorm.
 * User: normann.lou
 * Date: 18/03/2015
 * Time: 2:08 PM
 */

class MySQLDebuggableDatabase extends MySQLDatabase{
    public function query($sql, $errorLevel = E_USER_ERROR) {
        $query = parent::query($sql, $errorLevel);
        if(isset($_REQUEST['showqueries']) && Director::isDev(true)) {
            $count = 1+(int)Config::inst()->get('MySQLDebuggableDatabase', 'queries_count');
            Config::inst()->update('MySQLDebuggableDatabase', 'queries_count', $count);
            Debug::message("\nQuery Counts: $count\n", false);
        }

        return $query;
    }

}