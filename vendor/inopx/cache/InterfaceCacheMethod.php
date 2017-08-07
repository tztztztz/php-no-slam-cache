<?php
namespace inopx\cache;

/**
 * @author INOVUM Tomasz Zadora
 */
interface InterfaceCacheMethod {
  
  /**
   * Main method for getting and eventually creating the resource with read/write lock synchronisation.
   * 
   * @param string $group               - the resource group, like name of sql table
   * @param string $key                 - resource unique key, like id in the db table
   * @param int $lifetimeInSeconds      - lifetime in seconds
   * @param callable $createCallback    - callback, a empty arguments function that will return resource, leave null fore read only operation
   */
  public function get($group, $key, $lifetimeInSeconds, callable $createCallback = null);
  
  /**
   * Method for setting only value in the cache with write-lock synchronisation. It can be used for example in the background cron process to push values into the cache at regular intervals.
   * 
   * @param string $group               - the resource group, like name of sql table
   * @param string $key                 - resource unique key, like id in the db table
   * @param mixed $value                - the resource/value to be stored in cache
   * @param int $lifetimeInSeconds      - lifetime in seconds
   * 
   */
  public function set($group, $key, $value, $lifetimeInSeconds);
  
  /**
   * Destroys value in the cache with write-lock synchronisation
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
  public function setUseCacheSynchronisation($decision);
  
  /**
   * Gets use synchronisation setting.
   */
  public function getUseCacheSynchronisation();
  
  
}
