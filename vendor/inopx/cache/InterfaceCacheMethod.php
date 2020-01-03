<?php
namespace inopx\cache;

/**
 * @author INOVUM Tomasz Zadora
 */
interface InterfaceCacheMethod {
  
  /**
   * Main method for getting and eventually creating the resource with read/write lock synchronization.
   * 
   * @param string $group               - the resource group, like name of sql table
   * @param string $key                 - resource unique key, like id in the db table
   * @param int $lifetimeInSeconds      - lifetime in seconds
   * @param callable $createCallback    - callback, a empty arguments function that will return resource, leave null fore read only operation
   */
  public function get($group, $key, $lifetimeInSeconds, callable $createCallback = null);
  
  /**
   * Method for setting only value in the cache with write-lock synchronization. It can be used for example in the background cron process to push values into the cache at regular intervals.
   * 
   * @param string $group               - the resource group, like name of sql table
   * @param string $key                 - resource unique key, like id in the db table
   * @param mixed $value                - the resource/value to be stored in cache
   * @param int $lifetimeInSeconds      - lifetime in seconds
   * 
   */
  public function set($group, $key, $value, $lifetimeInSeconds);
  
  /**
   * Destroys value in the cache with write-lock synchronization
   * 
   * @param string $group
   * @param string $key
   */
  public function destroy($group, $key);
  
  /**
   * Sets whether to use locking or not.
   * 
   * @param boolean $decision
   */
  public function setUseCacheSynchronization($decision);
  
  /**
   * Gets use synchronization setting.
   */
  public function getUseCacheSynchronization();
  
  /**
   * Sets input/output adapter
   * 
   * @param \inopx\cache\InterfaceInputOutput $inputOutput
   */
  public function setInputOutput(InterfaceInputOutput $inputOutput);
  
  /**
   * Gets input/output adapter
   */
  public function getInputOutput();
  
  /**
   * Alias to setUseCacheSynchronization(), method will become depreciate in the future, sets whether to use locking or not.
   * 
   * @param boolean $decision
   */
  public function setUseCacheSynchronisation($decision); 
  
  
  /**
   * Alias to getUseCacheSynchronization(), method will become depreciate in the future. Gets use synchronization setting. It will become depreciate in the future. 
   */
  public function getUseCacheSynchronisation();
  
  /**
   * Sets the key name prefix.
   * 
   * @param string $prefix  - prefix to use for keys
   */
  public function setCacheKeyPrefix($prefix);
  
  /**
   * Gets the key name prefix. 
   */
  public function getCacheKeyPrefix();
  
  /**
   * Sets callback that will create new synchro object (that implements interface \inopx\cache\InterfaceSynchro) when necessary, its two argument callable function: 
   * 1st argument - lock key name
   * 2nd argument - locktimeout in milliseconds
   * 
   * @param callable $callback
   */
  public function setNewSynchroCallback( $callback );
  
  /**
   * Gets new synchro object using synchro callback set by setNewSynchroCallback method
   * 
   * @param string $lockKey               - synchronization key
   * @param int $lockTimeoutMiliseconds   - lock timeout in milliseconds (1/1000 of a second)
   */
  public function getNewSynchro($lockKey, $lockTimeoutMiliseconds);
  
  
}
