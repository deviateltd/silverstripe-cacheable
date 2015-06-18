<?php
/**
 * 
 * Allows qualified (Admin) users a view into the state of the object cache.
 * Very useful for diagnosing problems when suspecting object(s) haven't been cached,
 * or you're experiencing other cache-related problems.
 * 
 * At time of writing, only detailed information on the "file" baxckend is available.
 * With any other backend, all you'll see is the Cache / DB comparison.
 * 
 * @author Deviate Ltd 2015 http://www.deviate.net.nz
 * @package silverstripe-cachable
 * @todo Add status for non-file backends
 */
class CacheableCacheInspectorController extends Controller {
    
    /**
     * 
     * @var array
     */
    private static $allowed_actions = array(
        'cacheable/cacheinspector'
    );
    
    /**
     * 
     * @var DataList
     */
    protected $_dbObjects = array();
    
    /**
     * 
     * @return void
     */
    public function init() {
        parent::init();
        
        if(!Permission::check('ADMIN')) {
            return $this->redirect('/', 403);
        }
    }
    
    /**
     * 
     * @param SS_HTTPRequest $request
     * @param string $action
     * @return HTMLText
     */
    public function handleAction($request, $action) {
        parent::handleAction($request, $action);
        
        return $this->inspect();
    }
    
    /**
     * 
     * Generate key information about the object cache.
     * 
     * @return HTMLText
     */
    public function inspect() {
        $backend = ucfirst(CacheableConfig::current_cache_mode());
        
        switch($backend) {            
            // File backend
            case 'File':
                $fileList = ArrayList::create($this->getCacheFiles());
                $backendData['FileTotal'] = $fileList->count();
                $backendData['FileList'] = $fileList;
                $backendData['FileCacheDir'] = CACHEABLE_STORE_DIR;
                $backendData['FileSizeOnDisk'] = (round($this->getCacheSize() / 1024, 2)) . 'Kb';
                $backendData['CacheLastEdited'] = $this->getCacheLastUpdated();
            break;
            // Memcached backend
            // TODO
            case 'memcached':
            // Memcache backend
            // TODO
            case 'memcache':
            // APCu backend
            // TODO
            case 'apc':
                break;
        }
        
        $comparisonData = array('StatusList' => ArrayList::create(array(
            $this->cacheToORMCompareDataObject('Stage', 'SiteTree'),
            $this->cacheToORMCompareDataObject('Live', 'SiteTree')
        )));
        $templateLocal = 'BackendData_' . $backend;
        $templateAbsolute = __DIR__ . '/../../templates/includes/' . $templateLocal . '.ss';
        
        $bData = '';
        if(file_exists($templateAbsolute)) {
            $bData = $this->renderWith($templateLocal, $backendData);
        }
        
        $backendData = array_merge(
            array('BackEndMode' => $backend),
            array('BackEndDataList' => $bData)
        );
        $viewable = array_merge(
            $comparisonData,
            $backendData
        );
        
        return $this->renderWith('Inspector', $viewable);
    }
    
    /**
     * 
     * Get the total no. objects in the cache. Due to the way {@link CacheableNavigation} 
     * works, this is restricted to "SiteTree-ish" objects only at this point.
     * 
     * @param string $stage
     * @param string $className
     * @return int $total
     */
    private function getTotalCachedObjects($stage, $className = 'CacheableSiteTree') {
        $conf = SiteConfig::current_site_config();
        $service = new CacheableNavigationService($stage, $conf);
        $cache = $service->getObjectCache();
        if($cache && $siteMap = $cache->get_site_map()) {
            $cachedSiteTree = ArrayList::create($siteMap);
            return $cachedSiteTree->count();
        }
        
        return 0;
    }
    
    /**
     * 
     * Get the total no. objects in the DB. Due to the way {@link CacheableNavigation} 
     * works, this is restricted to "SiteTree-ish" objects only at this point.
     * 
     * @param string $stage
     * @param string $className
     * @return int
     */
    private function getTotalDBObjects($stage, $className = 'SiteTree') {
        $mode = 'Stage.' . $stage;
        Versioned::set_reading_mode($mode);
        if(!$this->_dbObjects || !isset($this->_dbObjects[$stage])) {
            if($list = DataObject::get($className)) {
                $this->_dbObjects[$stage] = $list;
            }
        }
        
        return $this->_dbObjects && !empty($this->_dbObjects[$stage]) ? $this->_dbObjects[$stage]->count() : 0;
    }
    
    /**
     * 
     * Build an array of object-cache files from the filesystem.
     * 
     * @return array
     */
    private function getCacheFiles() {
        $files = array();
        foreach(scandir(CACHEABLE_STORE_DIR) as $file) {
            // Ignore hidden files
            if(strstr($file, '.', true) !== '') {
                $name = CACHEABLE_STORE_DIR . DIRECTORY_SEPARATOR . $file;
                if(file_exists($name)) {
                    $size = filesize($name);
                    $date = date('Y-m-d H:i:s', filemtime($name));
                    $files[$name] = ArrayData::create(array(
                        'Line' => $size . "\t" . $date . "\t" . $file,
                        'Size' => $size,
                        'Date' => $date
                    ));
                }
            }
        }
        
        return $files;
    }
    
    /**
     * 
     * Generate output that indicates the health of the cache.
     * 
     * @param string $stage
     * @param string $className
     * @return ArrayData
     */
    private function cacheToORMCompareDataObject($stage, $className) {
        $cacheTotal = $this->getTotalCachedObjects($stage, $className);
        $ormTotal = $this->getTotalDBObjects($stage, $className);
        $isOk = $this->printStatus('OK');
        $isFail = $this->printStatus('FAIL');
        if($cacheTotal && $ormTotal && ($cacheTotal + $ormTotal) >0) {
            $status = ($ormTotal - $cacheTotal) === 0 ? $isOk : $isFail;
            $comparison = $status . ' ' . $cacheTotal . ' / ' . $ormTotal;
        } else {
            $comparison = $isFail . ' ' . $cacheTotal . ' / ' . $ormTotal;
        }
        
        $comparison .= ' ' . $className . ' (' . $stage . ')';
        
        return ArrayData::create(array('Status' => $comparison));
    }
    
    /**
     * 
     * Get the last update time of the cache. 
     * 
     * N.b Only works for "file" backend.
     * 
     * @param string $backend
     * @return string
     */
    private function getCacheLastUpdated($backend = 'file') {
        if($backend === 'file') {
            if($files = $this->getCacheFiles()) {
                $list = ArrayList::create($files)->sort('Date', 'DESC');
                return $list->first()->Date;
            }
        }
        
        return 'Not available.';
    }
    
    /**
     * 
     * Get the size of the object-cache in bytes.
     * 
     * @param string $backend
     * @return int
     */
    private function getCacheSize($backend = 'file') {
        $size = 0;
        if($backend === 'file') {
            if($files = $this->getCacheFiles()) {
                foreach($files as $file) {
                    $size += $file->Size;
                }
            }
            
            return $size;
        }
        
        return 'Not available.';
    }
    
    /**
     * 
     * Used with {@link $this->cacheToORMCompareDataObject} to highlight OK/FAILed
     * items in the UI.
     * 
     * @param string $status
     * @return string
     */
    private function printStatus($status) {
        $txtCss = strtolower($status);
        $txtMsg = strtoupper($status);
        return '<span class="is-' . $txtCss . '">[' . $txtMsg . ']</span>';
    }
}
