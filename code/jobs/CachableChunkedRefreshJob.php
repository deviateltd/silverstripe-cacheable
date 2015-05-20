<?php
/**
 * 
 * Looking at the refresh BuildTask: We break the entire iteration over the $pages 
 * result-set into chunks depending on peak memory usage which is checked at each
 * iteration. If meory exceeeds a preset limit, we pass processing of the cache-refresh
 * onto a job queue. 
 * 
 * The idea is that each chunk should be managable enough in terms of memory and 
 * execution time to run even on the smallest of host setups, rather than iteratively 
 * refreshing all objects in the same chunk of N 100s of pages, and consuming a ton 
 * of system resources.
 * 
 * Rough testing has yielded ~300Mb peak memory use on a 4GB RAM, non-SSD machine
 * with 500Mb allocated to PHP, on a v3.1 site with 700 identical pages, _without_
 * chunking, while _with_ chunking the entire task takes ~5s and registers ~75Mb
 * peak memory usage on the same system.
 * 
 * @author Deviate Ltd 2015 http://www.deviate.net.nz
 * @package silverstripe-cachable
 * @see {@link CacheableNavigation_Rebuild}.
 * todo The "Clean" ob may be clearing things it shouldn't be
 */
class CachableChunkedRefreshJob extends AbstractQueuedJob implements QueuedJob {
    
    /**
     * 
     * @var CacheableNavigationService
     */
    protected $service;
    
    /**
     * 
     * @var array
     */
    protected $chunk = array();
    
    /**
     * 
     * @var string
     */
    protected $stage = '';
    
    /**
     * 
     * @var number
     */
    protected $subsiteID = 0;
    
    /**
     * 
     * On each N iterations of the for-loop in $this->process(), we check to see if
     * current peak memory exceeds buffer subtracted from memory_limit. If it does, 
     * we throw an exception which is caught by {@link QueuedJobService} to mark the job 
     * as broken.
     * 
     * @var number
     */
    public static $critical_memory_buffer = 2097152; // 2Mb
    
    /**
     * 
     * Sets internal variables and persistent data for when job is created without
     * constructor params, and process() is called in {@link QueuedJobService}.
     * 
     * @param CacheableNavigationService $service
     * @param array $chunk                          An array of objects to cache
     * @param string $stage                         "Live" or "Stage"
     * @param number $subsiteID
     * @return void
     */
	public function __construct(CacheableNavigationService $service, $chunk, $stage, $subsiteID) {
        // Setters required for internal methods except $this->process()
        $this->setService($service);
        $this->setChunk($chunk);
        $this->setStage($stage);
        $this->setSubsiteID($subsiteID);
        
        // Persist structured "metadata" about the job using {@link CachableChunkedRefreshJobStorageService}.
        $jobConfig = array(
            'CachableChunkedRefreshJobStorageService' => array(
                'service'   => $this->getService(),
                'chunk'     => $this->getChunk()
                )
            );
        $this->setCustomConfig($jobConfig);
        
        $this->totalSteps = 1;
	}
    
    /**
     * 
     * @param CacheableNavigationService $service
     */
    public function setService(CacheableNavigationService $service) {
        $this->service = $service;
    }
    
    /**
     * 
     * @param array $chunk
     */
    public function setChunk($chunk) {
        $this->chunk = $chunk;
    }
    
    /**
     * 
     * @param string $stage
     */
    public function setStage($stage) {
        $this->stage = $stage;
    }
    
    /**
     * 
     * @param number $subsiteID
     */
    public function setSubsiteID($subsiteID) {
        $this->subsiteID = $subsiteID;
    }
    
    /**
     * 
     * @return CacheableNavigationService
     */
    public function getService() {
        return $this->service;
    }
    
    /**
     * 
     * @return array
     */
    public function getChunk() {
        return $this->chunk;
    }
    
    /**
     * 
     * @return string
     */
    public function getStage() {
        return $this->stage;
    }
    
    /**
     * 
     * @return number
     */
    public function getSubsiteID() {
        return $this->subsiteID;
    }
    
    /**
     * 
     * Pack all relevant info into the job's so that it's viewable in the
     *  "queuedjobs" CMS section. The title data in the title will appear 
     * inaccuarate when run via the main ProcessJobQueueTask.
     * 
     * @return string
     */
    public function getTitle() {
        $title = 'Cacheable refresh'
                . ' ' . $this->chunkSize() . ' objects.'
                . ($this->getSubsiteID() ? ' (SubsiteID ' . $this->getSubsiteID() . ')' : '')
                . ' ' . $this->getStage();
        
        return $title;
    }
    
    /**
     * 
     * @return boolean
     */
    public function jobFinished() {
        parent::jobFinished();
        
        return $this->isComplete === true;
    }
    
    /**
     * 
     * @return number
     */
    public function chunkSize() {
        return count($this->getChunk());
    }
    
    /**
     * 
     * The body of the job: Runs the memory-intensive refreshXX() method on each page
     * of the passed $chunk, using the passed $service.
     * 
     * Sets an error message viewable in the CMS' "Jobs" section, if an entry 
     * was not able to be saved to the cache.
     * 
     * @throws CacheableException
     * @return void
     */
    public function process() {
        $jobConfig = $this->getCustomConfig();
        $service = $jobConfig['CachableChunkedRefreshJobStorageService']['service'];
        $chunk = $jobConfig['CachableChunkedRefreshJobStorageService']['chunk'];
        
        foreach($chunk as $object) {
            $service->set_model($object);
            
            // Check memory on each iteration. Throw exception at a predefined upper limit
            $memThreshold = ((ini_get('memory_limit') * 1024 * 1024) - self::$critical_memory_buffer);
            $memPeak = memory_get_peak_usage(true);
            if($memPeak >= $memThreshold) {
                throw new CacheableException('Critical memory threshold reached in ' . __CLASS__ . '::process()');
            }
            
            // Only if refreshCachedPage() signals it completed A-OK and saved its payload
            // to the cachestore, do we then update the job status to 'complete'.
            if(!$service->refreshCachedPage()) {
                $errorMsg = 'Unable to cache object#' . $object->ID;
                $this->addMessage($errorMsg);
            }
        }
        
        $this->currentStep = 1;
        $this->isComplete = true;
    }
    
	/**
     * 
     * Uses the QUEUED type, to ensure we make as efficient use of system resources 
     * as possible.
     * 
	 * @return number
	 */
	public function getJobType() {
		return QueuedJob::QUEUED;
	}
    
    /**
     * 
     * By default {@link AbstractQueuedJob} will only queue 1 "identical" job at 
     * a time, so an implementation of this method is necessary becuase we need 
     * to fire-off multiple jobs for processing a different chunk of objects.
     * 
     * See the QueuedJobs' wiki for more info:
     * https://github.com/silverstripe-australia/silverstripe-queuedjobs/wiki/Defining-queued-jobs
     * 
     * @return string
     */
    public function getSignature() {
        parent::getSignature();
        return $this->randomSignature();
    }
    
}
