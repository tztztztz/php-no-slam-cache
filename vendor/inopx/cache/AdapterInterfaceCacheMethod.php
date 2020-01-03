<?php
namespace inopx\cache;

/**
 * Basic adapter of Cache Method Interface
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
   * Synchronization timeout in seconds. Default 30 sec.
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
   * Optional prefix added to key and group names.
   * 
   * @var string 
   */
  private $prefix;
  
  /**
   * Callback that will create new synchro object (that implements interface \inopx\cache\InterfaceSynchro) when necessary, its two argument callable function: 
   * 1st argument - lock key name
   * 2nd argument - locktimeout in milliseconds
   * 
   * @var callable
   */
  protected $synchroCallback;


  /**
   * Interface method for setting the resource with write lock synchronization. 
   * 
   * This adapter method sets the key using prefix, thats why child method should start with parent::set($group, $key, $value, $lifetimeInSeconds)
   * 
   * @param string $group           - cache group, like table name in SQL database
   * @param string $key             - resource id, like primary key value in database table
   * @param mixed $value            - value to set in cache
   * @param int $lifetimeInSeconds  - lifetime in seconds
   */
  public function set($group, $key, $value, $lifetimeInSeconds) {
    $key = $this->getCacheKeyPrefix().$key;
  }
  
  /**
   * Destroys the resource using write lock synchronization.
   * 
   * This adapter method sets the key using prefix, thats why child method should start with parent::destroy($group, $key)
   * 
   * @param string $group - cache group, like table name in SQL database
   * @param string $key   - resource id, like primary key value in database table
   */
  public function destroy($group, $key) {
    $key = $this->getCacheKeyPrefix().$key;
  }
  
  /**
   * Value getter without synchronization for child class - like from file, memcached, database etc.
   * 
   * This adapter method sets the key using prefix, thats why child method should start with parent::getValueNoSynchro($group, $key, $lifetimeInSeconds)
   * 
   * @param string $group - cache group, like table name in SQL database
   * @param string $key   - resource id, like primary key value in database table
   * @param type $lifetimeInSeconds - lifetime in seconds
   */
  protected function getValueNoSynchro($group, $key, $lifetimeInSeconds) {
    $key = $this->getCacheKeyPrefix().$key;
  }
  
  
  /**
   * Value creator and saver to the file, db, etc. without synchronization, for child class.
   * 
   * This adapter method sets the key using prefix, thats why child method should start with parent::createAndSaveValue($group, $key, $lifetimeInSeconds, $createCallback)
   * 
   * @param string $group - cache group, like table name in SQL database
   * @param string $key   - resource id, like primary key value in database table
   * @param type $lifetimeInSeconds - lifetime in seconds
   * @param callable $createCallback - create callbac that will return value for save
   */
  protected function createAndSaveValue($group, $key, $lifetimeInSeconds, callable $createCallback) {
    $key = $this->getCacheKeyPrefix().$key;
  }
  
  /**
   * Typical constructor.
   * 
   * @param int $syncTimeoutSeconds - timeout of waiting for synchronisation, in seconds
   * @param \inopx\cache\InterfaceInputOutput $inputOutputTransformer - input / output transformer
   * @param callable $synchroCallback - synchro callback to create synchro object, null = default synchro (PECL Sync)
   */
  public function __construct($syncTimeoutSeconds = 30, \inopx\cache\InterfaceInputOutput $inputOutputTransformer = null, $synchroCallback = null) {
    
    if (!$inputOutputTransformer) {
      $inputOutputTransformer = new \inopx\cache\AdapterInterfaceInputOutput();
    }
    
    $this->syncTimeoutSeconds = $syncTimeoutSeconds;
    $this->inputOutputTransformer = $inputOutputTransformer;
    
    // Default synchro if no synchro callback provided
    if (!$synchroCallback) {
      
      $synchroCallback = function ($lockKey, $lockTimeoutMilliseconds) {
        
        return new \inopx\cache\SynchroPECLSync($lockKey, $lockTimeoutMilliseconds);
        
      };
      
    }
    
    $this->synchroCallback = $synchroCallback;
    
  
  }
  
  /**
   * Read task performed in synchronized block
   * 
   * @param string $lockKey - lock key
   * @param int $lockTimeoutInSeconds - lock timeout in seconds
   * @param callable $callback  - read callback
   * @return mixed  - value
   */
  protected function synchronizedReadCallback($lockKey, $lockTimeoutInSeconds, $callback) {
   
    return $this->synchronizedCallback(1, $lockKey, $lockTimeoutInSeconds, $callback);
    
  }
  
  /**
   * Write task performed in synchronized block
   * 
   * @param string $lockKey - lock key
   * @param int $lockTimeoutInSeconds - lock timeout in seconds
   * @param callable $callback  - write callback
   * @return mixed  - value
   */
  protected function synchronizedWriteCallback($lockKey, $lockTimeoutInSeconds, $callback) {
    
    return $this->synchronizedCallback(2, $lockKey, $lockTimeoutInSeconds, $callback);
    
  }
  
  /**
   * Read/write task performed in synchronized block
   * 
   * @param int $lockType - Lock type: 1 - read lock, 2 - write lock
   * @param string $lockKey - lock key
   * @param int $lockTimeoutInSeconds - lock timeout in seconds
   * @param callable $callback  - read/write callback
   * @return type
   */
  protected function synchronizedCallback($lockType, $lockKey, $lockTimeoutInSeconds, $callback) {
    
    if ($this->useCacheSynchronization) {
      
      
      $synchro = $this->getNewSynchro($lockKey, $lockTimeoutInSeconds*1000);

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
        \error_log('Cache unlock failed at '.date('Y-m-d H:i:s').' for lock key: '.$lockKey);
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
  public function get($group, $key, $lifetimeInSeconds, callable $create = null) {
    
    $key = $this->getCacheKeyPrefix().$key;
    
    $cache = $this;
    
    $callbackRead = function() use($cache, $group, $key, $lifetimeInSeconds) { return $cache->getValueNoSynchro($group, $key, $lifetimeInSeconds); };
    $callbackCreate = function() use($cache, $group, $key, $lifetimeInSeconds, $create) { return $cache->createAndSaveValue($group, $key, $lifetimeInSeconds, $create); };
    
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
    $synchro = $this->getNewSynchro($group.$key, $this->syncTimeoutSeconds*1000);
      
    if (!$synchro->writeLock()) {
      
      \error_log('Cache lock failed at '.date('Y-m-d H:i:s').' for group '.$group.' and key '.$key);
      return $create();
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
   * 
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
  
  /**
   * Sets the key name prefix. 
   */
  public function setCacheKeyPrefix($prefix) {
    
    // Maximum of 12 chars
    if (mb_strlen($prefix) > 12) {
      
      return FALSE;
      
    }
    
    // Alphanumeric characters only
    if (!preg_match('/\w+/', $prefix)) {
      
      return FALSE;
      
    }
    
    $this->prefix = $prefix;
    
    return TRUE;
  }
  
  /**
   * Gets the key and group names prefix. 
   */
  public function getCacheKeyPrefix() {
    return $this->prefix;
  }
  
  /**
   * Sets callback that will create new synchro object (that implements interface \inopx\cache\InterfaceSynchro) when necessary, its two argument callable function: 
   * 1st argument - lock key name
   * 2nd argument - locktimeout in milliseconds
   * 
   * @param callable $callback
   */
  public function setNewSynchroCallback($callback) {
    $this->synchroCallback = $callback;
  }

  
  /**
   * Gets new synchro object using synchro callback set by setNewSynchroCallback method. By default it's new instance of \inopx\cache\SynchroPECLSync class
   * 
   * @param type $lockKey
   * @param type $lockTimeoutMiliseconds
   * @return \inopx\cache\InterfaceSynchro
   */
  public function getNewSynchro($lockKey, $lockTimeoutMiliseconds) {
    
    return \call_user_func($this->synchroCallback, $lockKey, $lockTimeoutMiliseconds);
    
  }
  
  
}
