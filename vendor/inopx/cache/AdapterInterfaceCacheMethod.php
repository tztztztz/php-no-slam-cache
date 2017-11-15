<?php
namespace inopx\cache;

/**
 * Description of AdapterInterfaceCacheMethod
 *
 * @author INOVUM Tomasz Zadora
 */
abstract class AdapterInterfaceCacheMethod implements \inopx\cache\InterfaceCacheMethod {
  
  /**
   * If true, it uses synchronization. Default true.
   * 
   * @var boolean 
   */
  protected $useCacheSynchronization = true;
  
  /**
   * Synchronisation timeout in seconds. Default 30 sec.
   * @var int 
   */
  protected $syncTimeoutSeconds = 1;
  
  /**
   * Input/output transformer.
   * 
   * @var \inopx\cache\InterfaceInputOutput 
   */
  protected $inputOutputTransformer;


  /**
   * Interface method for setting the resource with write lock synchronization.
   * 
   * @param type $group
   * @param type $key
   * @param type $value
   * @param type $lifetimeInSeconds
   */
  abstract public function set($group, $key, $value, $lifetimeInSeconds);
  
  /**
   * Interface method for destroying the resource using write lock synchronization.
   * 
   * @param string $group - grupa wartości
   * @param string $key   - klucz wartości
   */
  abstract public function destroy($group, $key);
  
  /**
   * Value getter without synchronization for child class - like from file, memcached, database etc.
   */
  abstract protected function getValueNoSynchro($group, $key, $lifetimeInSeconds);
  
  /**
   * Value creator and saver to the file, db, etc. without synchronization, for child class.
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
   * Read task performed in synchronized block
   * 
   * @param type $lockKey
   * @param type $lockTimeoutInSeconds
   * @param type $callback
   * @return type
   */
  protected function synchronizedReadCallback($lockKey, $lockTimeoutInSeconds, $callback) {
   
    return $this->synchronizedCallback(1, $lockKey, $lockTimeoutInSeconds, $callback);
    
  }
  
  /**
   * Write task performed in synchronized block
   * 
   * @param type $lockKey
   * @param type $lockTimeoutInSeconds
   * @param type $callback
   * @return type
   */
  protected function synchronizedWriteCallback($lockKey, $lockTimeoutInSeconds, $callback) {
    
    return $this->synchronizedCallback(2, $lockKey, $lockTimeoutInSeconds, $callback);
    
  }
  
  /**
   * Read/write task performed in synchronized block
   * 
   * @param int $lockType         : 1 - read lock, 2 - write lock
   * @param type $lockKey
   * @param type $lockTimeoutInSeconds
   * @param type $callback
   * @return type
   */
  protected function synchronizedCallback($lockType, $lockKey, $lockTimeoutInSeconds, $callback) {
    
    if ($this->useCacheSynchronization) {
      $synchro = new \inopx\cache\AdapterInterfaceSynchro($lockKey, $lockTimeoutInSeconds*1000);

      if ($lockType == 1) {
        $result = $synchro->readLock();
      }
      else {
        $result = $synchro->writeLock();
      }

      if (!$result) {
        \error_log('Cache lock failed at '.date('Y-m-d H:i:s').' for lock key: '.$lockKey);
        return null;
      }
    }
    
    $return = $callback();
    
    if ($this->useCacheSynchronization) {
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
   * Interface method for getting and eventually creating the resource with synchronization.
   * 
   * @param string $group             - resource group
   * @param string $key               - resource key
   * @param int $lifetimeInSeconds    - lifetime in seconds
   * @param callable $createCallback  - callback for creating the resource
   * @return mixed        - if it returns NULL it means error ocurred, any other value (including boolean false or 0) is a value fetched from cache or newly created
   */
  public function get($group, $key, $lifetimeInSeconds, callable $createCallback = null) {
    
    
    $cache = $this;
    
    $callbackRead = function() use($cache, $group, $key, $lifetimeInSeconds) { return $cache->getValueNoSynchro($group, $key, $lifetimeInSeconds); };
    $callbackCreate = function() use($cache, $group, $key, $lifetimeInSeconds, $createCallback) { return $cache->createAndSaveValue($group, $key, $lifetimeInSeconds, $createCallback); };
    
    /////////////////
    // Trying to read
    $value = $this->synchronizedReadCallback($group.$key, $this->syncTimeoutSeconds, $callbackRead);
    
    // not expired value found in the cache ?
    if ($value !== NULL) {
      return $value;
    }
    
    /////////////////
    // Trying to create and save
    if (!$this->useCacheSynchronization) {
      
      return $callbackCreate();
      
    }
    
    /////////////////
    // Trying to read again - with write lock
    $synchro = new \inopx\cache\AdapterInterfaceSynchro($group.$key, 5*1000);
      
    if (!$synchro->writeLock()) {
      
      echo 'Cache lock failed at '.date('Y-m-d H:i:s').' for group '.$group.' and key '.$key;

      \error_log('Cache lock failed at '.date('Y-m-d H:i:s').' for group '.$group.' and key '.$key);
      return $callbackCreate();
    }
    
    /////////////////
    // not expired value found in the cache ?
    $value2 = $callbackRead();
    
    if ($value2 !== NULL) {
      $synchro->writeUnlock();
      return $value2;
    }
    
    /////////////////
    // Creating and saving value
    $value3 = $callbackCreate();

    $synchro->writeUnlock();

    return $value3;
    
  }
  
  
  
  /**
   * To use synchronization or not.
   * 
   * @param boolean $decision
   */
  public function setUseCacheSynchronization($decision) {
    
    if (is_bool($decision)) {
      $this->useCacheSynchronization = $decision;
    }
    
  }
  
  /**
   * Gets use synchronization setting.
   * @return type
   */
  public function getUseCacheSynchronization() {
    
    return $this->useCacheSynchronization;
    
  }
  
  /**
   * Gets input-output controller.
   * 
   * @return \inopx\cache\InterfaceInputOutput
   */
  public function getInputOutput() {
    
    return $this->inputOutputTransformer;
    
  }
  
  /**
   * Sets input-output controller.
   * 
   * @param \inopx\cache\InterfaceInputOutput $inputOutput
   */
  public function setInputOutput(InterfaceInputOutput $inputOutput) {
    
    $this->inputOutputTransformer = $inputOutput;
    
  }
  
  
  
  /**
   * @deprecated since version 1.0.4
   * To use synchronisation or not to use, that is a question.
   * 
   * @param type $decision
   */
  public function setUseCacheSynchronisation($decision) {
    
    return $this->setUseCacheSynchronization($decision);
    
  }
  
  /**
   * Alias to getUseCacheSynchronization()
   * @deprecated since version 1.0.4
   * @return type
   */
  public function getUseCacheSynchronisation() {
    
    return $this->getUseCacheSynchronization();
    
  }

  
  
}
