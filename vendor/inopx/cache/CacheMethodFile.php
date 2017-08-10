<?php
namespace inopx\cache;

/**
 * Description of CacheMethodFile
 *
 * @author INOVUM Tomasz Zadora
 */
class CacheMethodFile extends \inopx\cache\AdapterInterfaceCacheMethod {
  
  /**
   * Base directory for files without / at end
   * @var string 
   */
  protected $baseDir;  
  
  const KEY_VALUE = 0;
  
  const KEY_CREATION_TIME = 1;
  
  /**
   * 
   * @param string $baseDir           - base cache dir without directory separator at the end
   * @param int $syncTimeoutSeconds   - sync timeout default 30 sec
   * @param \inopx\cache\InterfaceInputOutput $inputOutputTransforemer - input / output transformer, leave null for default adapter
   */
  public function __construct($baseDir = 'inopx_cache', $syncTimeoutSeconds = 30, \inopx\cache\InterfaceInputOutput $inputOutputTransforemer = null) {
    
    parent::__construct($syncTimeoutSeconds, $inputOutputTransforemer);
    
    $this->baseDir = $baseDir;
    
  }
  
  /**
   * Deep / clustered directory structure protecting filesystem from too many files in one directory. 
   * 
   * Important for huge number of entries in the cache.
   * 
   * @param type $id
   */
  public static function getDeepFileDir($id, $baseDir) {
    
    return \inopx\io\IOTool::getClusteredDir($id, true, $baseDir);
    
  }
  
  /**
   * Getting filename for the group and key
   * 
   * @param type $group
   * @param type $key
   * @return type
   */
  protected function getFilename($group, $key) {
    
    
    $tmpGroup = \inopx\io\IOTool::sanitizeFilename($group);
    $tmpFile = \inopx\io\IOTool::sanitizeFilename($key);
    
    return $this->baseDir.'/'.$tmpGroup.'/'.$this->getDeepFileDir($key, $this->baseDir.'/'.$tmpGroup.'/').$tmpFile.'.obj';
    
  }
  
  /**
   * Getting the value from file - without synchro
   * 
   * @param type $group
   * @param type $key
   * @param type $lifetimeInSeconds
   * @return mixed|null
   */
  protected function getValueNoSynchro($group, $key, $lifetimeInSeconds) {
    
    $filename = $this->getFilename($group, $key);    
    
    // File not found
    if (!file_exists($filename)) {
      return null;
    }
    
    $saved = include $filename;
    
    // Expired?
    if ($saved[self::KEY_CREATION_TIME]+$lifetimeInSeconds < time()) {
      return null;
    }
    
    return $saved[self::KEY_VALUE];
    
  }
  
  /**
   * Creates the value by callback / closure and saves it to the file. No synchro inside.
   * 
   * @param type $group
   * @param type $key
   * @param \inopx\cache\callable $createCallback
   * @return type
   */
  protected function createAndSaveValue($group, $key, $lifetimeInSeconds, callable $createCallback) {
    
    // No callable method
    if (!is_callable($createCallback)) {
      return NULL;
    }
    
    // Creating value
    $value = $createCallback();
    
    // Value is NULL?
    if ($value === NULL) {
      return NULL;
    }
    
    // Saving value
    $save = [self::KEY_VALUE => $value, self::KEY_CREATION_TIME => time()];
    
    $filename = $this->getFilename($group, $key);
    
    //\file_put_contents($filename, '<?php return \inopx\io\IOTool::dataFromBase64(\''.\inopx\io\IOTool::dataToBase64($save).'\');');
    
    \file_put_contents($filename, '<?php return $this->inputOutputTransforemer->output(\''.$this->inputOutputTransforemer->input($save).'\');');
    
    
    
    return $value;
  }

  
  /**
   * Sets cached value, with write lock synchronisation
   * 
   * @param type $group
   * @param type $key
   * @param type $value
   * @param type $lifetimeInSeconds
   * @return boolean
   */
  public function set($group, $key, $value, $lifetimeInSeconds) {
    
    $cache = $this;
    
    $callback = function() use($cache, $group, $key, $value) {
      
      $save = [self::KEY_VALUE => $value, self::KEY_CREATION_TIME => time()];
    
      $filename = $cache->getFilename($group, $key);

      //return \file_put_contents($filename, '<?php return \inopx\io\IOTool::dataFromBase64(\''.\inopx\io\IOTool::dataToBase64($save).'\');');
      
      \file_put_contents($filename, '<?php return $this->inputOutputTransforemer->output(\''.$this->inputOutputTransforemer->input($save).'\');');
      
    };
    
    return $this->synchronisedWriteCallback($group.$key, $this->syncTimeoutSeconds, $callback);
    
  }
  
  /**
   * Destroys the cached value with write lock synchronisation
   * 
   * @param type $group
   * @param type $key
   * @return boolean
   */
  public function destroy($group, $key) {
    
    $cache = $this;
    
    $callback = function() use($cache, $group, $key) {
      
      $filename = $cache->getFilename($group, $key);
    
      if (\file_exists($filename)) {
        return unlink($filename);
      }
      
      return true;
      
    };
    
    return $this->synchronisedWriteCallback($group.$key, $this->syncTimeoutSeconds, $callback);
    
    
  }
  
  
}
