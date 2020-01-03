<?php
namespace inopx\cache;

/**
 * Dummy Cache method for simulating cache.
 *
 * @author INOVUM Tomasz Zadora
 */
class CacheMethodDummy implements \inopx\cache\InterfaceCacheMethod {

  
  public function destroy($group, $key) {
    return TRUE;
  }

  public function get($group, $key, $lifetimeInSeconds, callable $createCallback = null) {
    return $createCallback();
  }

  public function getInputOutput() {
    return NULL;
  }

  public function getUseCacheSynchronisation() {
    return FALSE;
  }

  public function getUseCacheSynchronization() {
    return FALSE;
  }

  public function set($group, $key, $value, $lifetimeInSeconds) {
    return TRUE;
  }

  public function setInputOutput(InterfaceInputOutput $inputOutput) {
    return TRUE;
  }

  public function setUseCacheSynchronisation($decision) {
    return TRUE;
  }

  public function setUseCacheSynchronization($decision) {
    return TRUE;
  }

  
  public function getCacheKeyPrefix() {
    return FALSE;
  }

  public function setCacheKeyPrefix($prefix) {
    return FALSE;
  }
  
  public function getNewSynchro($lockKey, $lockTimeoutMiliseconds) {
    
  }

  public function setNewSynchroCallback($callback) {
    
  }


  
}
