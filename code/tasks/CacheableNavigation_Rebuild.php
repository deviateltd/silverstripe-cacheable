<?php
/**
 * 
 * This BuildTask pre-primes the filesystem or in-memory caches for {@link SiteTree} and 
 * {@link SiteConfig} native SilverStripe objects.
 * 
 * The BuildTask should be run from the command-line as the webserver user 
 * e.g. www-data otherwise while attempting to access the site from a browser, the 
 * webserver won't have permission to access the cache. E.g:
 * 
 * <code>
 *  #> sudo -u www-data ./framework/sake dev/tasks/CacheableNavigation_Rebuild
 * <code>
 * 
 * You may pass-in an optional "Stage" parameter, with a value of one of "Live" 
 * or "Stage" which helps when debugging or breaking-up the job to make it more
 * manageable in terms of system resources. It will restrict the cache-rebuild 
 * to objects in the given {@Link Versioned} stage. The default is to cache objects
 * in both "Stage" and "Live" modes which takes longer to run and uses more memory.
 * 
 * @author Deviate Ltd 2014-2015 http://www.deviate.net.nz
 * @package silverstripe-cachable
 * @see {@link CacheableNavigation_Clean}.
 * @todo Rename task to better suit the module's new name
 * @todo Cache filled using {@link Zend_Cache_Core::getFillingPercentage()}.
 */
class CacheableNavigation_Rebuild extends BuildTask {
    
    /**
     * 
     * A suitable number by which to break-up the total number of pages.
     * 
     * The idea is to keep this nunmber relatively low, to ensure each chunk as 
     * a QueuedJob is easily managed by PHP's CLI SAPI in terms of memory usage, 
     * especially as there may be 10s of these jobs to be queued.
     * 
     * @var number
     */
    public static $chunk_divisor = 100;
    
    /**
     *
     * @var string
     */
    protected $description = 'Rebuilds silverstripe-cacheable object cache.';
    
    /**
     * 
     * Physically runs the task which - dependent on QueuedJobs being installed and
     * not skipped via script params - will queue-up chunks of pages to be cached,
     * or just attempt to cache call objects at once.
     * 
     * @param SS_HTTPRequest $request
     * @return void
     */
    public function run($request) {
        $startTime = time();
        $skipQueue = $request->getVar('SkipQueue');
        $currentStage = Versioned::current_stage();
        
        /*
         * Restrict cache rebuild to the given stage - useful for debugging or
         * "Poor Man's" chunking.
         */
        if($paramStage = $request->getVar('Stage')) {
            $stage_mode_mapping = array(
                ucfirst($paramStage) => strtolower($paramStage)
            );
        // All stages
        } else {
            $stage_mode_mapping = array(
                "Stage" => "stage",
                "Live"  => "live",
            );
        }

        $canQueue = interface_exists('QueuedJob');
        $siteConfigs = DataObject::get('SiteConfig');
        foreach($stage_mode_mapping as $stage => $mode) {
            Versioned::set_reading_mode('Stage.' . $stage);
            if(class_exists('Subsite')) {
                Subsite::disable_subsite_filter(true);
                Config::inst()->update("CacheableSiteConfig", 'cacheable_fields', array('SubsiteID'));
                Config::inst()->update("CacheableSiteTree", 'cacheable_fields', array('SubsiteID'));
            }
            
            foreach($siteConfigs as $config) {
                $service = new CacheableNavigationService($mode, $config);
                $service->refreshCachedConfig();
                
                if(class_exists('Subsite')) {
                    $pages = DataObject::get("Page", "SubsiteID = '" . $config->SubsiteID . "'");
                } else {
                    $pages = DataObject::get("Page");
                }
                
                $pageCount = $pages->count();
                
                /*
                 * 
                 * Queueing should only occur if:
                 * - QueuedJob module is available
                 * - SkipQueue param is not set
                 * - Total no. pages is greater than the chunk divisor
                 */
                $lowPageCount = (self::$chunk_divisor > $pageCount);
                $doQueue = ($canQueue && !$skipQueue && !$lowPageCount);
                if($pageCount) {
                    $i = 0;
                    $chunkNum = 0;
                    $chunk = array();
                    foreach($pages as $page) {
                        $i++;
                        // If QueuedJobs exists and isn't user-disabled: Chunk
                        if($doQueue) {
                            // Start building a chunk of pages to be refreshed
                            $chunk[] = $page;
                            $chunkSize = count($chunk);
                            
                            /*
                             * Conditions of chunking:
                             * - Initial chunks are chunk-size == self::$chunk_divisor
                             * - Remaining object count equals no. objects in current $chunk
                             */
                            $doChunking = $this->chunkForQueue($pageCount, $chunkSize, $i);
                            if($doChunking) {
                                $chunkNum++;
                                $this->queue($service, $chunk, $stage, $config->SubsiteID);
                                echo "Queued chunk #" . $chunkNum . ' (' . $chunkSize . ' objects).' . self::new_line();
                                $chunk = array();
                            }
                        // Default to non-chunking if no queuedjobs or script instructed to skip queuing
                        } else {
                            $percentComplete = $this->percentageComplete($i, $pageCount);
                            echo 'Caching: ' . trim($page->Title) . ' (' . $percentComplete . ') ' . self::new_line();
                            $service->set_model($page);
                            $service->refreshCachedPage();
                        }
                    }
                }
                
                $service->completeBuild();
                
                // Completion message
                $msg = self::new_line() . $pageCount . ' ' . $stage . ' pages in subsite ' . $config->ID;
                if($doQueue) {
                    $msg .= ' queued for caching.' . self::new_line();
                } else {
                    $msg .= ' objects cached.' . self::new_line();
                }
                echo $msg . self::new_line();
            }
            
            if(class_exists('Subsite')){
                Subsite::disable_subsite_filter(false);
            }
        }

        Versioned::set_reading_mode($currentStage);
        
        $endTime = time();
        $totalTime = ($endTime - $startTime);
        
        $this->showConfig($totalTime, $request, $lowPageCount);
    }
    
    /**
     * 
     * Returning boolean true|false this method dictates what gets queued and when.
     * 
     * @param integer $pageCount
     * @param integer $chunkSize
     * @param integer $count
     * @return boolean
     */
    public function chunkForQueue($pageCount, $chunkSize, $count) {
        // The no. items-to-cache in full-chunks
        $totalFullChunkCount = ((int)floor(round($pageCount / self::$chunk_divisor, 1))) * self::$chunk_divisor;
        $queueFullChunk = ($chunkSize === self::$chunk_divisor);
        $queuePartChunk = ($count > $totalFullChunkCount && $chunkSize === ($pageCount % self::$chunk_divisor));
        
        return ($queueFullChunk || $queuePartChunk);
    }
        
    /**
     * 
     * Utility method: Generate a percentage of how complete the cache rebuild is, including
     * optional memory usage.
     * 
     * @param number $count
     * @param number $total
     * @return string
     */
    private function percentageComplete($count, $total) {
        $calc = (((int)$count / (int)$total) * 100);
        return round($calc, 1) . '%';
    }
    
    /**
     * 
     * Utility method: Current memory usage in Mb.
     * 
     * @return number
     */
    private function memory() {
        return memory_get_peak_usage(true) / 1024 / 1024;
    }
    
    /**
     * 
     * Utility method: Generate an O/S independent new-line, for as many times 
     * as is required.
     * 
     * @param number $mul
     * @return string
     */
    public static function new_line($mul = 1) {
        $newLine = Director::is_cli() ? PHP_EOL : "<br />";
        return str_repeat($newLine, $mul);
    }
    
    /**
     * 
     * Create a {@link ChunkedCachableRefreshJob} for each "chunk" of N pages
     * to refresh the caches of. Once run, $chunk is truncated and passed back its
     * original reference.
     * 
     * @param CachableNavigationService $service
     * @param array $chunk
     * @param string $stage
     * @param number $subsiteID
     * @return number $jobDescriptorID (Return value not used)
     */
    public function queue(CachableNavigationService $service, $chunk, $stage, $subsiteID) {
        $job = new CachableChunkedRefreshJob($service, $chunk, $stage, $subsiteID);
        $jobDescriptorID = singleton('QueuedJobService')->queueJob($job);
        
        return $jobDescriptorID;
    }
    
    /**
     * Summarise the task's configuration details at the end of a run.
     * 
     * @param string $totalTime
     * @param SS_HTTPRequest $request
     * @param boolean $lowCount         If the total no. pages is greater than 
     *                                  the chunk divisor, we don't attempt to chunk
     *                                  and let the user know        
     * @return void
     */
    public function showConfig($totalTime, $request, $lowCount = false) {
        $skipQueue = ($request->getVar('SkipQueue') && $request->getVar('SkipQueue') == 1);
        
        $queueOn = (interface_exists('QueuedJob') ? 'On' : 'Off');
        $queueSkipped = ($skipQueue ? ' (Skipped: User instruction)' : '');
        if($lowCount && !$skipQueue) {
            $queueSkipped = ' (Skipped: Low page count)';
        }
        
        echo 'Job Queue: ' . $queueOn . $queueSkipped . self::new_line();
        echo 'Cache backend: ' . CacheableConfig::current_cache_mode() . self::new_line();
        echo 'Peak memory: ' . $this->memory() . 'Mb' . self::new_line();
        echo 'Execution time: ' . $totalTime . 's' . self::new_line();
    }
}
