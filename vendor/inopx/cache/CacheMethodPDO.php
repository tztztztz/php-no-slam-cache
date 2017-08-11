<?php
namespace inopx\cache;

/**
 * Description of CacheMethodPDO
 *
 * @author INOVUM Tomasz Zadora
 */
class CacheMethodPDO extends \inopx\cache\AdapterInterfaceCacheMethod {
  
  /**
   * SQL Table name
   * @var string 
   */
  public $SQLTableName = 'inopx_cache';
  
  /**
   * SQL Column name for the group column
   * @var string 
   */
  public $SQLColumnGroupName = 'r_group';
  
  /**
   * SQL Column name for the key column
   * @var string 
   */
  public $SQLColumnKeyName = 'r_key';
  
  /**
   * SQL Column name for the value column
   * @var string 
   */
  public $SQLColumnValueName = 'r_value';
  
  /**
   * SQL Column name for the creation time column
   * @var string 
   */
  public $SQLColumnCreationTimeName = 'r_creation_time';
  
  /**
   * SQL Column name for the deadline column
   * @var string 
   */
  public $SQLColumnDeadlineTimeName = 'r_deadline';
  
  /**
   * Połączenie do bazy danych
   * @var \PDO 
   */
  protected $PDOConnection;
  
  
  /**
   * SQL Dialect
   * @var int 
   */
  protected $SQLDialect;

  /**
   * SQL Dialect MySQL
   */
  const SQL_DIALECT_MYSQL = 1;
  
  /**
   * SQL Dialect PostgreSQL
   */
  const SQL_DIALECT_POSTGRESQL = 2;
  
  /**
   * 
   * @param \PDO $PDOConnection       - PDO Connection to the DB
   * @param type $sqlDialect          - SQL Dialect to use, leave NULL for default MySQL
   * @param type $syncTimeoutSeconds  - sync timeout default 30 sec
   * @param \inopx\cache\InterfaceInputOutput $inputOutputTransformer - input / output transformer, leave null for default adapter
   */
  public function __construct(\PDO $PDOConnection, $sqlDialect = null, $syncTimeoutSeconds = 30, \inopx\cache\InterfaceInputOutput $inputOutputTransformer = null) {
    
    parent::__construct($syncTimeoutSeconds, $inputOutputTransformer);
    
    $this->PDOConnection = $PDOConnection;
    
    if (!$sqlDialect) {
      $this->SQLDialect = self::SQL_DIALECT_MYSQL;
    }
    else {
      $this->SQLDialect = $sqlDialect;
    }
    
  }
  
  
  
  /**
   * Getting the value from the DB - without synchro
   * 
   * @param type $group
   * @param type $key
   * @param type $lifetimeInSeconds
   */
  protected function getValueNoSynchro($group, $key, $lifetimeInSeconds) {
    
    
    // Preparing static database statement for later use
    static $preparedStmt;
    
    
    
    if ($preparedStmt == null) {
      
      if ($this->SQLDialect == self::SQL_DIALECT_MYSQL) {

        //$sql = 'SELECT `'.$this->SQLColumnDeadlineTimeName.'`, `'.$this->SQLColumnValueName.'` FROM `'.$this->SQLTableName.'` WHERE `'.$this->SQLColumnGroupName.'` = ? AND `'.$this->SQLColumnKeyName.'` = ? AND `'.$this->SQLColumnDeadlineTimeName.'` > NOW()';
        
        $sql = 'SELECT `'.$this->SQLColumnDeadlineTimeName.'`, `'.$this->SQLColumnValueName.'` FROM `'.$this->SQLTableName.'` WHERE `'.$this->SQLColumnGroupName.'` = ? AND `'.$this->SQLColumnKeyName.'` = ?';
        
        $preparedStmt = $this->PDOConnection->prepare($sql);

      }
      else if ($this->SQLDialect == self::SQL_DIALECT_POSTGRESQL) {
        
        
        $sql = 'SELECT "'.$this->SQLColumnDeadlineTimeName.'", "'.$this->SQLColumnValueName.'" FROM "'.$this->SQLTableName.'" WHERE "'.$this->SQLColumnGroupName.'" = ? AND "'.$this->SQLColumnKeyName.'" = ?';
        
        $preparedStmt = $this->PDOConnection->prepare($sql);

      }
      // Bad dialect?
      else {
        return NULL;
      }
      
    }
    
    if ($preparedStmt->execute([$group,$key]) === FALSE) {
      return NULL;
    }
    
    $result = $preparedStmt->fetch(\PDO::FETCH_NUM);
    
    if ($result === FALSE || !isset($result[1])) {      
      return NULL;
    }
    
    
    
    if (strtotime($result[0]) < time()) {
      
      $decision = $this->getUseCacheSynchronisation();
      
      $this->setUseCacheSynchronisation(false);
      $this->destroy($group, $key);
      $this->setUseCacheSynchronisation($decision);
      
      return NULL;
      
    }
    
    //return \inopx\io\IOTool::dataFromBase64( $result[1] );
    
    return $this->inputOutputTransformer->output( $result[1] );
    
    
  }
  
  /**
   * Create the Value by Callback / Closure and then save it to the DB
   * 
   * @param type $group
   * @param type $key
   * @param type $lifetimeInSeconds
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
    
    // Preparing static database statement for later use
    static $preparedStmt;
    
    if ($preparedStmt == null) {
    
      if ($this->SQLDialect == self::SQL_DIALECT_MYSQL) {

        $sql = 'INSERT INTO `'.$this->SQLTableName.'` (`'.$this->SQLColumnCreationTimeName.'`, `'.$this->SQLColumnDeadlineTimeName.'`, `'.$this->SQLColumnGroupName.'`, `'.$this->SQLColumnKeyName.'`, `'.$this->SQLColumnValueName.'`) VALUES (?,?,?,?,?) ';
        
        $preparedStmt = $this->PDOConnection->prepare($sql);
        
        //echo $sql.'<br />';

      }
      else if ($this->SQLDialect == self::SQL_DIALECT_POSTGRESQL) {

        $sql = 'INSERT INTO "'.$this->SQLTableName.'" ("'.$this->SQLColumnCreationTimeName.'", "'.$this->SQLColumnDeadlineTimeName.'", "'.$this->SQLColumnGroupName.'", "'.$this->SQLColumnKeyName.'", "'.$this->SQLColumnValueName.'") VALUES (?,?,?,?,?) ';
        
        $preparedStmt = $this->PDOConnection->prepare($sql);

      }
      // Bad dialect?
      else {
        return NULL;
      }
      
    }
    
    $ct = time();
    $de = $ct+$lifetimeInSeconds;
    
    
    // Saving value
    //if ($preparedStmt->execute([date('Y-m-d H:i:s',$ct), date('Y-m-d H:i:s',$de), $group,$key, \inopx\io\IOTool::dataToBase64($value)]) === false) {
    
    if ($preparedStmt->execute([date('Y-m-d H:i:s',$ct), date('Y-m-d H:i:s',$de), $group,$key, $this->inputOutputTransformer->input($value)]) === false) {
    
    
      
      $e = $this->PDOConnection->errorInfo();
      $err = 'Saving resource group ['.$group.'], key ['.$key.'] has failed';
      isset($e[2]) ? $err .= ', DB ERROR: '.$e[2] : NULL;    
      throw new \Exception( $err );
      
    }
    
    // Returning value
    return $value;
    
  }
  
  /**
   * Method for setting the resource.
   * 
   * @param type $group
   * @param type $key
   * @param type $value
   * @param type $lifetimeInSeconds
   */
  public function set($group, $key, $value, $lifetimeInSeconds) {
    
    // Preparing static database statement for later use
    static $preparedStmt;
    
    if ($preparedStmt == null) {
    
      if ($this->SQLDialect == self::SQL_DIALECT_MYSQL) {

        $sql = 'INSERT INTO `'.$this->SQLTableName.'` (`'.$this->SQLColumnCreationTimeName.'`, `'.$this->SQLColumnDeadlineTimeName.'`, `'.$this->SQLColumnGroupName.'`, `'.$this->SQLColumnKeyName.'`, `'.$this->SQLColumnValueName.'`) VALUES (?,?,?,?,?) ';
        
        $preparedStmt = $this->PDOConnection->prepare($sql);
        
        //echo $sql.'<br />';

      }
      else if ($this->SQLDialect == self::SQL_DIALECT_POSTGRESQL) {

        $sql = 'INSERT INTO "'.$this->SQLTableName.'" ("'.$this->SQLColumnCreationTimeName.'", "'.$this->SQLColumnDeadlineTimeName.'", "'.$this->SQLColumnGroupName.'", "'.$this->SQLColumnKeyName.'", "'.$this->SQLColumnValueName.'") VALUES (?,?,?,?,?) ';
        
        $preparedStmt = $this->PDOConnection->prepare($sql);

      }
      // Bad dialect?
      else {
        return NULL;
      }
      
    }
    
    $ct = time();
    $de = $ct+$lifetimeInSeconds;
    
    $cache = $this;
    
    $callback = function() use($cache, $preparedStmt, $group, $key, $value, $ct, $de) {
      
      //if (!$preparedStmt->execute([date('Y-m-d H:i:s',$ct), date('Y-m-d H:i:s',$de), $group, $key, \inopx\io\IOTool::dataToBase64($value)])) {
      if (!$preparedStmt->execute([date('Y-m-d H:i:s',$ct), date('Y-m-d H:i:s',$de), $group, $key, $this->inputOutputTransformer->input($value)])) {
      
      
        $e = $cache->PDOConnection->errorInfo();
        $err = 'Saving resource group ['.$group.'], key ['.$key.'] has failed';
        isset($e[2]) ? $err .= ', DB ERROR: '.$e[2] : NULL;    
        throw new \Exception( $err );

      }
      
      return TRUE;
      
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
      
      if ($cache->SQLDialect == self::SQL_DIALECT_MYSQL) {
        
        $sql = 'DELETE FROM `'.$cache->SQLTableName.'` WHERE `'.$cache->SQLColumnGroupName.'` = ? AND `'.$cache->SQLColumnKeyName.'` = ?';
        
      }
      else if ($cache->SQLDialect == self::SQL_DIALECT_POSTGRESQL) {
        
        $sql = 'DELETE FROM "'.$cache->SQLTableName.'" WHERE "'.$cache->SQLColumnGroupName.'" = ? AND "'.$cache->SQLColumnKeyName.'" = ?';
        
      }
      else {
        return FALSE;
      }
      
      
      return $cache->PDOConnection->prepare($sql)->execute([$group, $key]);
      
    };
    
    return $this->synchronisedWriteCallback($group.$key, $this->syncTimeoutSeconds, $callback);
    
    
  }
  
  
  
  /**
   * Tworzy tabelę SQL do przechowywania, o nazwie zawartej w $this->tableName
   */
  public function createSQLTable($extraOptions = null) {
    
    $sqlList = [];
    $colDef = '';
    
    ///////////////////
    // Dialect MySQL
    if ($this->SQLDialect == self::SQL_DIALECT_MYSQL) {
      
      $colDef .= '`'.$this->SQLColumnCreationTimeName.'` DATETIME NOT NULL DEFAULT now(),'.\PHP_EOL;
      $colDef .= '`'.$this->SQLColumnDeadlineTimeName.'` DATETIME NOT NULL,'.\PHP_EOL;
      $colDef .= '`'.$this->SQLColumnGroupName.'` VARCHAR(128) NOT NULL,'.\PHP_EOL;
      $colDef .= '`'.$this->SQLColumnKeyName.'` VARCHAR(128) NOT NULL,'.\PHP_EOL;
      $colDef .= '`'.$this->SQLColumnValueName.'` MEDIUMTEXT NOT NULL'.\PHP_EOL;
      
      (!is_array($extraOptions) || !isset($extraOptions['engine'])) ? $extraOptions['engine'] = 'InnoDB' : NULL;
      
      $sqlList[] = 'CREATE TABLE `'.$this->SQLTableName.'` ('.$colDef.', PRIMARY KEY (`'.$this->SQLColumnGroupName.'`,`'.$this->SQLColumnKeyName.'`)) ENGINE='.$extraOptions['engine'].';';
      
    }
    ///////////////////
    // Dialect PostgreSQL
    else if ($this->SQLDialect == self::SQL_DIALECT_POSTGRESQL) {
      
      if (!is_array($extraOptions) || !isset($extraOptions['schema'])) {
        
        $extraOptions['schema'] = 'public';
        
      }
      
      if (!is_array($extraOptions) || !isset($extraOptions['tablespace'])) {
        
        $extraOptions['tablespace'] = 'pg_default';
        
      }
      
      
      $colDef .= '"'.$this->SQLColumnCreationTimeName.'" timestamp with time zone NOT NULL DEFAULT now(),'.\PHP_EOL;
      $colDef .= '"'.$this->SQLColumnDeadlineTimeName.'" timestamp with time zone NOT NULL,'.\PHP_EOL;
      $colDef .= '"'.$this->SQLColumnGroupName.'" character varying(128) COLLATE pg_catalog."default" NOT NULL,'.\PHP_EOL;
      $colDef .= '"'.$this->SQLColumnKeyName.'" character varying(128) COLLATE pg_catalog."default" NOT NULL,'.\PHP_EOL;
      $colDef .= '"'.$this->SQLColumnValueName.'" text COLLATE pg_catalog."default" NOT NULL'.\PHP_EOL;
      
      
      
      $sqlList[] = 'CREATE TABLE '.$extraOptions['schema'].'."'.$this->SQLTableName.'" ('.$colDef.') WITH ( OIDS = FALSE ) TABLESPACE '.$extraOptions['tablespace'].';';
      $sqlList[] = 'ALTER TABLE '.$extraOptions['schema'].'."'.$this->SQLTableName.'" ADD PRIMARY KEY ("'.$this->SQLColumnGroupName.'", "'.$this->SQLColumnKeyName.'");';
      
    }
    else {
      
      return FALSE;
      
    }
    
    $ok = true;
    foreach ($sqlList as $sql) {
      
      if ($this->PDOConnection->exec($sql) !== false) {
        continue;
      }
      
      $ok = false;
      break;
      
    }
    
    if ($ok) {
      return true;
    }
    
    $e = $this->PDOConnection->errorInfo();
    isset($e[2]) ? $err = $e[2] : $err = 'Create table ERROR';    
    throw new \Exception( $err );
    
  }
  
  /**
   * Drops DB Cache table
   * 
   * @param array $extraOptions
   * @return boolean
   */
  public function dropSQLTable($extraOptions = null) {
    
    $sql = FALSE;
    
    ///////////////////
    // Dialect MySQL
    if ($this->SQLDialect == self::SQL_DIALECT_MYSQL) {
      
      $sql = 'DROP TABLE `'.$this->SQLTableName.'`;';
      
    }
    ///////////////////
    // Dialect PostgreSQL
    else if ($this->SQLDialect == self::SQL_DIALECT_POSTGRESQL) {
      
      if (!is_array($extraOptions) || !isset($extraOptions['schema'])) {
        $extraOptions['schema'] = 'public';
      }
      
      $sql = 'DROP TABLE '.$extraOptions['schema'].'."'.$this->SQLTableName.'";';
      
    }
    else {
      
      return FALSE;
      
    }
    
    if ($this->PDOConnection->exec($sql) !== false) {
      return TRUE;
    }
    
    $e = $this->PDOConnection->errorInfo();
    isset($e[2]) ? $err = $e[2] : $err = 'Drop table ERROR';    
    throw new \Exception( $err );
    
  }
  
}
