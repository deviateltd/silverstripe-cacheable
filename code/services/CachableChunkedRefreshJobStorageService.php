<?php
/**
 * 
 * Basic storage-as-a-service class operated on by {@link Config} via {@link CachableChunkedRefreshJob}
 * for use with QueuedJobs to "persist" data when job is initialised in {@link QueuedJobService}.
 * 
 * This doesn't feel very DRY as we seem to be repeating the same class-properties
 * as found on the {@link CachableChunkedRefreshJob} itself.
 * 
 * @author Deviate Ltd 2014-2015 http://www.deviate.net.nz
 * @package silverstripe-cachable
 */
class CachableChunkedRefreshJobStorageService {

    /**
     * 
     * @var CacheableNavigationService
     */
    private static $service;
    
    /**
     * 
     * @var array
     */
    private static $chunk = array();
    
    /**
     * 
     * @return void
     */
    public function __construct() {
    }
    
}
