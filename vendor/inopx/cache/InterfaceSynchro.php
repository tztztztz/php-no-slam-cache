<?php
namespace inopx\cache;

/**
 *
 * @author INOVUM Tomasz Zadora
 */
interface InterfaceSynchro {
  
  public function readLock();
  
  public function readUnlock();
  
  public function writeLock(); 
  
  public function writeUnlock();
  
}
