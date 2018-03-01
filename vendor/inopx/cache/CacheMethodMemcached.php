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
  
  /**
   * 
   * @param string $memcachedHost     - memcached host, default '127.0.0.1'
   * @param int $memcachedPort        - memcached port, default 11211
   * @param int $syncTimeoutSeconds   - lock timeout, default 30
   * @param \inopx\cache\InterfaceInputOutput $inputOutputTransformer - input / output transformer, leave null for default adapter
   */
  public function __construct($memcachedHost = '127.0.0.1', $memcachedPort = 11211, $syncTimeoutSeconds = 30, \inopx\cache\InterfaceInputOutput $inputOutputTransformer = null) {
    
    parent::__construct($syncTimeoutSeconds, $inputOutputTransformer);
    
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
    
    parent::createAndSaveValue($group, $key, $lifetimeInSeconds, $createCallback);
    
    $value = $createCallback();

    if ($value === NULL) {
      return NULL;
    }

    //$this->memcached->set($group.$key, \inopx\io\IOTool::dataToBase64($value), $lifetimeInSeconds);
    
    $this->memcached->set($group.$key, $this->inputOutputTransformer->input($value), $lifetimeInSeconds);
    
    

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
    
    parent::destroy($group, $key);
    
    $cache = $this;
    
    $callback = function() use($cache, $group, $key) {
      
      return $this->memcached->delete($group.$key);
      
    };
    
    return $this->synchronizedWriteCallback($group.$key, $this->syncTimeoutSeconds, $callback);
    
  }

  /**
   * Get value withour synchronization
   * 
   * @param type $group
   * @param type $key
   * @param type $lifetimeInSeconds
   * @return type
   */
  protected function getValueNoSynchro($group, $key, $lifetimeInSeconds) {
    
    parent::getValueNoSynchro($group, $key, $lifetimeInSeconds);
    
    if ( ($value = $this->memcached->get($group.$key)) === FALSE) {
      return NULL;
    }
    
    //return \inopx\io\IOTool::dataFromBase64($value);
    
    return $this->inputOutputTransformer->output($value);
    
    
    
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
    
    parent::set($group, $key, $value, $lifetimeInSeconds);
    
    $cache = $this;
    
    $callback = function() use($cache, $group, $key, $value, $lifetimeInSeconds) {
      
      //return $cache->memcached->set($group.$key, \inopx\io\IOTool::dataToBase64($value), $lifetimeInSeconds);
      
      return $cache->memcached->set($group.$key, $this->inputOutputTransformer->input($value), $lifetimeInSeconds);
      
      
    };
    
    return $this->synchronizedWriteCallback($group.$key, $this->syncTimeoutSeconds, $callback);
    
    
  }

  
}
