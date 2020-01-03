<?php
namespace inopx\cache;

/**
 * Synchronization through Postgresql row locking.
 * 
 * Use "SELECT * FROM pg_stat_activity" SQL command to see all locks.
 * Use "select pg_terminate_backend(16339);" SQL command to kill SQL lock query if necessary.
 *
 * @author INOVUM Tomasz Zadora
 */
class SynchroPostgresql extends \inopx\cache\AdapterInterfaceSynchro {
  
  /**
   * Postgresql database name that hods tabel designated for synchronization
   * @var string 
   */
  public $dbName = 'lockdb';
  
  /**
   * Database user 
   * @var string
   */
  public $dbUser = null;
  
  /**
   * Database user password
   * 
   * @var string 
   */
  public $dbPass = null;
  
  /**
   * Lock table name
   * 
   * @var string 
   */
  public $lockTableName = 'inopx_locks';
  
  /**
   * Lock column name.
   * 
   * @var string 
   */
  public $lockColumnName = 'name';
  
  /**
   * Connection to database
   * 
   * @var \PDO 
   */
  public $conn;
  
  /**
   * 
   * Number of milliseconds for statement timeout, which is lock timeout. Default 10 seconds (10 000 milliseconds)
   * 
   * @var int 
   */
  public $statementTimeout = 10000;
  
  /**
   * Lock key name
   * 
   * @var string 
   */
  public $lockKeyName;


  public function __construct($lockKeyName, $statementTimeout = null) {
    
    $this->lockKeyName = $lockKeyName;
    
    if (is_numeric($statementTimeout)) {
      $this->statementTimeout = $statementTimeout;
    }
    
  }
  
  /**
   * Gets PDO connection
   * 
   * @staticvar \PDO $conn
   * @return \PDO
   */
  public function getPGConn( ) {
    
    if (!$this->conn) {
      
      $DSN = 'pgsql:host=localhost;port=5432;dbname='.$this->dbName.';user='.$this->dbUser.';password='.$this->dbPass;
      $this->conn = new \PDO($DSN);
      
      if (is_numeric($this->statementTimeout)) {
        $sql = "set statement_timeout TO ".$this->statementTimeout.";";
      }
      
      $this->conn->exec($sql);
    }
    
    return $this->conn;
    
  }
  
  /**
   * Creates table used for sunchronization in PostgreSQL database.
   */
  public function createSyncTable($schemeName = 'public') {
    
    $conn = $this->getPGConn();
    
    $sql = "CREATE TABLE \"".$schemeName."\".\"".$this->lockTableName."\" ( \"".$this->lockColumnName."\" character varying(255) NOT NULL, PRIMARY KEY (\"".$this->lockColumnName."\") ) WITH ( OIDS = FALSE );";
    $conn->exec($sql);
    
    $sql = "ALTER TABLE \"".$schemeName."\".\"".$this->lockTableName."\" OWNER to \"".$this->dbUser."\";";
    $conn->exec($sql);
    
  }
  
  /**
   * Check if lock key name exists in $this->lockTableName table, if it's not, create it.
   * 
   * @param string $name  - lock key name
   */
  public function checkForLockCreate( $name ) {
    
    $conn = $this->getPGConn();
    
    $sql = "SELECT * FROM ".$this->lockTableName." WHERE ".$this->lockColumnName." = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$name]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    
    if (!$row) {
      $sql = "INSERT INTO ".$this->lockTableName." (".$this->lockColumnName.") VALUES (?)";
      $stmt = $conn->prepare($sql);
      $stmt->execute([$name]);
    }
    
  }
  
  public function closeConnection() {
    $this->conn = null;
  }

  
  /**
   * Starts transaction and selects record in $this->lockTableName table with FOR SHARE clause. If record with name $this->lockKeyName does not exists in the $this->lockTableName table, creates it first.
   * 
   * @return array  - locked record from database
   */
  public function readLock() {
    
    $this->checkForLockCreate( $this->lockKeyName );
    $conn = $this->getPGConn();
    $conn->beginTransaction();
    
    $sql = "SELECT * FROM ".$this->lockTableName." WHERE ".$this->lockColumnName." = ? FOR SHARE";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([ $this->lockKeyName ]);
    
    return $stmt->fetch(\PDO::FETCH_ASSOC);
    
  }
  
  /**
   * Unlocks by commiting transation.
   */
  public function readUnlock() {
    return $this->conn->commit();
  }
  
  /**
   * Starts transaction and selects record in $this->lockTableName table with FOR UPDATE clause. If record with name $this->lockKeyName does not exists in the $this->lockTableName table, creates it first.
   * 
   * @return array  - locked record from database
   */
  public function writeLock() {
    
    $this->checkForLockCreate($this->lockKeyName);
    $conn = $this->getPGConn();
    $conn->beginTransaction();
    
    $sql = "SELECT * FROM ".$this->lockTableName." WHERE ".$this->lockColumnName." = ? FOR UPDATE";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([ $this->lockKeyName ]);
    
    return $stmt->fetch(\PDO::FETCH_ASSOC);
  }
  
  /**
   * Unlocks by commiting transation.
   */
  public function writeUnlock() {
    return $this->conn->commit();
  }

  
  
}
