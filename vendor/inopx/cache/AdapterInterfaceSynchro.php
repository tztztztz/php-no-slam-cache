<?php
namespace inopx\cache;

/**
 * Non abstract adapter of the Synchro interface, with deafult synchronisation using PECL Sync library.
 *
 * @author INOVUM Tomasz Zadora
 */
class AdapterInterfaceSynchro implements \inopx\cache\InterfaceSynchro {
  
  /**
   * PECL Sync Class
   * 
   * @var \SyncReaderWriter 
   */
  protected $syncReaderWriter;
  
  /**
   * Timeout in milliseconds
   * 
   * @var int 
   */
  protected $timeout;

  /**
   * 
   * @param string $key   - synchro key
   * @param int $timeout  - timeout in milliseconds (1/1000 sec).
   */
  public function __construct($key, $timeout = 30000) {
    $this->syncReaderWriter = new \SyncReaderWriter($key);
    $this->timeout = $timeout;
  }
  
  public function readLock() {
    
    return $this->syncReaderWriter->readlock( $this->timeout );
  }

  public function readUnlock() {
    return $this->syncReaderWriter->readunlock();
  }

  public function writeLock() {
    return $this->syncReaderWriter->writelock( $this->timeout );
  }

  public function writeUnlock() {
    return $this->syncReaderWriter->writeunlock();
  }

  
}
