<?php
/**
 * @author Deviate Ltd 2014-2015 http://www.deviate.net.nz
 * @package silverstripe-cachable
 */
class CacheableNavigation_RebuildTest extends SapphireTest {
    
    /**
     * Ensure the correct no. chunks and chunk-contents is built each time
     */
    public function testChunkForQueue() {
        $task = singleton('CacheableNavigation_Rebuild');
        
        $this->assertTrue($task->chunkForQueue(1167, 100, 1));
        $this->assertTrue($task->chunkForQueue(1167, 100, 100));
        $this->assertFalse($task->chunkForQueue(1167, 67, 100));
        $this->assertTrue($task->chunkForQueue(1167, 67, 1101));
    }
    
}
