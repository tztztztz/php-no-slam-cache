<?php
namespace inopx\cache;

/**
 * Description of CacheMethodMemcached
 *
 * @author INOVUM Tomasz Zadora
 */
class CacheMethodMemcached extends \inopx\cache\AdapterInterfaceCacheMethod {
  
  protected $memcached;
  
  protected $memcachedHost;
  
  protected $memcachedPort;

  public function __construct($memcachedHost = '127.0.0.1', $memcachedPort = 11211) {
    
    $this->memcachedHost = $memcachedHost;
    $this->memcachedPort = $memcachedPort;
    
    $this->memcached = new \Memcached();
    $this->memcached->addServer($this->memcachedHost, $this->memcachedPort);
    
  }
  
  /**
   * Creates the value by callback / closure and saves it to the file. No synchro inside.
   * 
   * @param type $group
   * @param type $key
   * @param type $lifetimeInSeconds
   * @param \inopx\cache\callable $createCallback
   * @return type
   */
  protected function createAndSaveValue($group, $key, $lifetimeInSeconds, callable $createCallback) {
    
    $value = $createCallback();

    if ($value === NULL) {
      return NULL;
    }

    $this->memcached->set($group.$key, \inopx\io\IOTool::dataToBase64($value), $lifetimeInSeconds);

    return $value;
  }

  /**
   * Destroys the resource
   * 
   * @param type $group
   * @param type $key
   * @return type
   */
  public function destroy($group, $key) {
    
    $cache = $this;
    
    $callback = function() use($cache, $group, $key) {
      
      return $this->memcached->delete($group.$key);
      
    };
    
    return $this->synchronisedWriteCallback($group.$key, $this->syncTimeoutSeconds, $callback);
    
  }

  /**
   * Get value withour synchronisation
   * 
   * @param type $group
   * @param type $key
   * @param type $lifetimeInSeconds
   * @return type
   */
  protected function getValueNoSynchro($group, $key, $lifetimeInSeconds) {
    
    if ( ($value = $this->memcached->get($group.$key)) === FALSE) {
      return NULL;
    }
    
    return \inopx\io\IOTool::dataFromBase64($value);
    
  }
  
  /**
   * Set value with synchro
   * 
   * @param type $group
   * @param type $key
   * @param type $value
   * @param type $lifetimeInSeconds
   * @return boolean
   */
  public function set($group, $key, $value, $lifetimeInSeconds) {
    
    $cache = $this;
    
    $callback = function() use($cache, $group, $key, $value, $lifetimeInSeconds) {
      
      return $cache->memcached->set($group.$key, \inopx\io\IOTool::dataToBase64($value), $lifetimeInSeconds);
      
    };
    
    return $this->synchronisedWriteCallback($group.$key, $this->syncTimeoutSeconds, $callback);
    
    
  }

  
}
