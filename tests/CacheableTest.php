<?php
/*
 *  
 * @author Deviate Ltd 2014-2015 http://www.deviate.net.nz
 * @package silverstripe-cachable
 */
class CacheableTest extends SapphireTest {
    
    public function testIsFlush() {
        $controller = new Controller();
        $req = new SS_HTTPRequest('GET', '/', array('flush' => 1));
        $controller->setRequest($req);
        
        $this->assertTrue(Cacheable::is_flush($controller));        
        
        $req = new SS_HTTPRequest('GET', '/', array('flush' => 'all'));
        $controller->setRequest($req);
        
        $this->assertTrue(Cacheable::is_flush($controller));
        
        $req = new SS_HTTPRequest('GET', '/', array('fluff' => 1));
        $controller->setRequest($req);
        
        $this->assertFalse(Cacheable::is_flush($controller));
    }
    
}

