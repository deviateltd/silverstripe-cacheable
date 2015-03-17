<?php
/**
 * Created by PhpStorm.
 * User: normann.lou
 * Date: 15/03/2015
 * Time: 6:24 PM
 */

if(extension_loaded('memcached')){
    // Libmemcached is enabled.
    SS_Cache::add_backend(
        'cacheablestore',
        'Libmemcached',
        array(
            'servers' => array(
                'host' => 'localhost',
                'port' => 11211,
                'weight' => 1,
            ),
        )
    );
}else if(class_exists('Memcache')){
    // Memcached is enabled.
    SS_Cache::add_backend(
        'cacheablestore',
        'Memcached',
        array(
            'servers' => array(
                'host' => 'localhost',
                'port' => 11211,
                'persistent' => true,
                'weight' => 1,
                'timeout' => 5,
                'retry_interval' => 15,
                'status' => true,
                'failure_callback' => ''
            )
        )
    );
}else{
    $cacheable_store_dir = TEMP_FOLDER . DIRECTORY_SEPARATOR . 'cacheable-navigation';
    if (!is_dir($cacheable_store_dir)) mkdir($cacheable_store_dir);

    SS_Cache::add_backend('cacheablestore', 'File', array(
        'cache_dir' => $cacheable_store_dir,
    ));
}

SS_Cache::pick_backend('cacheablestore', 'Cached_Navigation', 15);