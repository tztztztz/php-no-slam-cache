<?php
namespace inopx\cache;

/**
 * Description of AdapterInterfaceCacheMethod
 *
 * @author INOVUM Tomasz Zadora
 */
abstract class AdapterInterfaceCacheMethod implements \inopx\cache\InterfaceCacheMethod {
  
  /**
   * If true, it uses synchronisation. Default true.
   * 
   * @var boolean 
   */
  protected $useCacheSynchronisation = true;
  
  /**
   * Synchronisation timeout in seconds. Default 10 sec.
   * @var int 
   */
  protected $syncTimeoutSeconds = 30;
  
  /**
   * Input/output transformer.
   * 
   * @var \inopx\cache\InterfaceInputOutput 
   */
  protected $inputOutputTransformer;


  /**
   * Interface method for setting the resource with write lock synchronisation.
   * 
   * @param type $group
   * @param type $key
   * @param type $value
   * @param type $lifetimeInSeconds
   */
  abstract public function set($group, $key, $value, $lifetimeInSeconds);
  
  /**
   * Interface method for destroying the resource using write lock synchronisation.
   * 
   * @param string $group - grupa wartości
   * @param string $key   - klucz wartości
   */
  abstract public function destroy($group, $key);
  
  /**
   * Value getter without synchronisation for child class - like from file, memcached, database etc.
   */
  abstract protected function getValueNoSynchro($group, $key, $lifetimeInSeconds);
  
  /**
   * Value creator and saver to the file, db, etc. without synchronisation, for child class.
   */
  abstract protected function createAndSaveValue($group, $key, $lifetimeInSeconds, callable $createCallback);
  
  
  /**
   * Typical constructor
   * @param int $syncTimeoutSeconds - timeout of waiting for synchronisation, in seconds
   * @param \inopx\cache\InterfaceInputOutput $inputOutputTransformer - input / output transformer
   */
  public function __construct($syncTimeoutSeconds = 30, \inopx\cache\InterfaceInputOutput $inputOutputTransformer = null) {
    
    if (!$inputOutputTransformer) {
      $inputOutputTransformer = new \inopx\cache\AdapterInterfaceInputOutput();
    }
    
    $this->syncTimeoutSeconds = $syncTimeoutSeconds;
    $this->inputOutputTransformer = $inputOutputTransformer;
  
  }
  
  /**
   * Read task performed in synchronised block
   * 
   * @param type $lockKey
   * @param type $lockTimeoutInSeconds
   * @param type $callback
   * @return type
   */
  protected function synchronisedReadCallback($lockKey, $lockTimeoutInSeconds, $callback) {
   
    return $this->synchronisedCallback(1, $lockKey, $lockTimeoutInSeconds, $callback);
    
  }
  
  /**
   * Write task performed in synchronised block
   * 
   * @param type $lockKey
   * @param type $lockTimeoutInSeconds
   * @param type $callback
   * @return type
   */
  protected function synchronisedWriteCallback($lockKey, $lockTimeoutInSeconds, $callback) {
    
    return $this->synchronisedCallback(2, $lockKey, $lockTimeoutInSeconds, $callback);
    
  }
  
  /**
   * Read/write task performed in synchronised block
   * 
   * @param int $lockType         : 1 - read lock, 2 - write lock
   * @param type $lockKey
   * @param type $lockTimeoutInSeconds
   * @param type $callback
   * @return type
   */
  protected function synchronisedCallback($lockType, $lockKey, $lockTimeoutInSeconds, $callback) {
    
    if ($this->useCacheSynchronisation) {
      $synchro = new \inopx\cache\AdapterInterfaceSynchro($lockKey, $lockTimeoutInSeconds*1000);

      if ($lockType == 1) {
        $result = $synchro->readLock();
      }
      else {
        $result = $synchro->writeLock();
      }

      if (!$result) {
        \error_log('Cache lock failed at '.date('Y-m-d H:i:s').' for group '.$group.' and key '.$key);
        return null;
      }
    }
    
    $return = $callback();
    
    if ($this->useCacheSynchronisation) {
      if ($lockType == 1) {
        $result = $synchro->readUnlock();
      }
      else {
        $result = $synchro->writeUnlock();
      }

      if (!$result) {
        \error_log('Cache unlock failed at '.date('Y-m-d H:i:s').' for group '.$group.' and key '.$key);
      }
    }
    
    return $return;
    
  }

  
  
  
  
  /**
   * Interface method for getting and eventually creating the resource with synchronisation.
   * 
   * @param string $group             - resource group
   * @param string $key               - resource key
   * @param int $lifetimeInSeconds    - lifetime in seconds
   * @param callable $createCallback  - callback for creating the resource
   * @return mixed        - if it returns NULL it means error ocurred, any other value (including boolean false or 0) is a cached value.
   */
  public function get($group, $key, $lifetimeInSeconds, callable $createCallback = null) {
    
    /////////////////
    // Trying to read
    $cache = $this;
    
    $callback = function() use($cache, $group, $key, $lifetimeInSeconds) { return $cache->getValueNoSynchro($group, $key, $lifetimeInSeconds); };
    
    $value = $this->synchronisedReadCallback($group.$key, $this->syncTimeoutSeconds, $callback);
    
    // not expired value found in the cache ?
    if ($value !== NULL) {
      return $value;
    }
    
    
    /////////////////
    // Trying to create and save
    $callback = function() use($cache, $group, $key, $lifetimeInSeconds, $createCallback) { return $cache->createAndSaveValue($group, $key, $lifetimeInSeconds, $createCallback); };
    
    return $this->synchronisedWriteCallback($group.$key, $this->syncTimeoutSeconds, $callback);
  }
  
  /**
   * To use synchronisation or not to use, that is a question.
   * 
   * @param type $decision
   */
  public function setUseCacheSynchronisation($decision) {
    
    if (is_bool($decision)) {
      $this->useCacheSynchronisation = $decision;
    }
    
  }

  
  public function getUseCacheSynchronisation() {
    
    return $this->useCacheSynchronisation;
    
  }
  
  
  public function getInputOutput() {
    
    return $this->inputOutputTransformer;
    
  }

  public function setInputOutput(InterfaceInputOutput $inputOutput) {
    
    $this->inputOutputTransformer = $inputOutput;
    
  }

  
  
}
