<?php
require __DIR__.\DIRECTORY_SEPARATOR.'classloader.php';

ini_set('display_errors', 1);

$showHelp = true;
$command = null;
$dsn = null;
$user = null;
$pass = null;
$sleep = 5;
$lockTimeout = 30;
$prefix = null;

$tests = [
  'memcached' => 'Memcached method test', 
  'file' => 'File method test', 
  'pdo-mysql-create-table' => 'PDO Mysql Test - create cache Table', 
  'pdo-pgsql-create-table' => 'PDO Postgresql Test - create cache Table',
  'pdo-mysql' => 'PDO Mysql Test', 
  'pdo-pgsql' => 'PDO Postgresql Test',
  'pdo-mysql-drop-table' => 'PDO Mysql Test - drop cache Table', 
  'pdo-pgsql-drop-table' => 'PDO Postgresql Test - drop cache Table'
];


if ($argc > 1) {
  
  $idx = 1;
  
  while (true) {
    
    if ($idx >= $argc) {
      break;
    }
    
    if ($argv[$idx] == '--test' || $argv[$idx] == '--dsn' || $argv[$idx] == '--user' || $argv[$idx] == '--pass' || $argv[$idx] == '--sleep' || $argv[$idx] == '--prefix') {
      
      $tmp = $idx;
      $idx++;
      
      if ($idx >= $argc) {
        break;
      }
      
      
      
      if (isset($argv[ $idx ])) {
        
        if ($argv[$tmp] == '--test') {
          $command = $argv[ $idx ];
        }
        else if ($argv[$tmp] == '--dsn') {
          $dsn = $argv[ $idx ];
        }
        if ($argv[$tmp] == '--user') {
          $user = $argv[ $idx ];
        }
        else if ($argv[$tmp] == '--pass') {
          $pass = $argv[ $idx ];
        }
        else if ($argv[$tmp] == '--sleep') {
          $sleep = $argv[ $idx ];
        }
        else if ($argv[$tmp] == '--prefix') {
          $prefix = $argv[ $idx ];
        }
        
      }
      
      
    }
    
    $idx++;
    
  }
  
}





if ($command) {
  
  $showHelp = FALSE;
  
  if (substr($command, 0, 4) == 'pdo-' && !$dsn) {
    
    echo "ERROR - DSN is required for PDO related command";
    exit;
  }
  
}

if ($showHelp) {
  
  echo 'Usage:'.\PHP_EOL;
  echo 'php cli-test-cache.php [COMMAND]'.\PHP_EOL.\PHP_EOL.'Where [COMMAND] is one or more than one of:'.\PHP_EOL.\PHP_EOL;
  echo '--dsn Connection string to database'.\PHP_EOL.\PHP_EOL;
  echo '--user Database user name'.\PHP_EOL.\PHP_EOL;
  echo '--pass Database password'.\PHP_EOL.\PHP_EOL;
  echo '--test [TEST]'.\PHP_EOL.\PHP_EOL;
  echo 'Where [TEST] is one of following tests:'.\PHP_EOL.\PHP_EOL;
  
  foreach ($tests as $code => $descpn) {
    echo $code.' - '.$descpn.\PHP_EOL;
  }
  
  echo \PHP_EOL;
  echo 'Examples:'.\PHP_EOL;
  echo 'php cli-test-cache.php --test memcached'.\PHP_EOL;
  echo 'php cli-test-cache.php --test file'.\PHP_EOL;
  echo 'php cli-test-cache.php --test pdo-mysql-create-table --dsn "mysql:host=localhost;dbname=mydbname" --user someuser --pass somepass'.\PHP_EOL;
  echo 'php cli-test-cache.php --test pdo-mysql --dsn "mysql:host=localhost;dbname=mydbname" --user someuser --pass somepass'.\PHP_EOL;
  
  exit;
}


echo "COMMAND = ".$command.\PHP_EOL;
echo "USER = ".$user.\PHP_EOL;
echo "PASS = ".$user.\PHP_EOL;
echo "DSN = ".$user.\PHP_EOL;
echo "SLEEP IN CREATE PROCESS = ".$sleep." sec.".\PHP_EOL.\PHP_EOL;
echo "LOCK TIMEOUT = ".$lockTimeout." sec.".\PHP_EOL.\PHP_EOL;




///////////////////
// Universal creator and conditions for the cached resource. In this case resource is a current date&time plus random name,
// kept in the cache under group 'products' with key '11500' and lifetime of 30 seconds
//
// There is a sleep applied (10 seconds) in creation process, to give you time to execute this script concurrently in another 
// window, and see if only one resource will be created thanks to locking mechanism.
$lifetimeInSeconds = 30;
$group = 'products';
$key = 11500;

$firstNames = ['Albert', 'Mark', 'Fiona', 'Helen', 'Alex', 'Robert', 'Rachel', 'Jeff'];
$lastNames = ['Smith', 'Washington', 'Jones', 'Jackson', 'Green', 'Harris', 'Walker', 'Hall', 'Turner'];

$createCallback = function() use($firstNames, $lastNames, $sleep) {
  
  // CODE COMMENTED BELOW IS AN EXAMPLE WHAT YOU SHOUD NOT DO: 
  // CREATE CACHE WITHIN CACHED RESOURCE CREATION PROCESS, BECAUSE IT MAY LEAD TO DEADLOCK
  // The same goes for any other locking mechanism - do not use any locks inside creation process
  // 
  //$cache = new \inopx\cache\CacheMethodFile(\INOPX_NOPUB_DIR.'/_inopx_cache');
  //$value2 = $cache->getCachedValue_('aaa', '10324', 30, function() { return uniqid(''); });
  
  // Creating resource
  $resource = date('Y-m-d H:i:s').' '.$firstNames[rand(0, count($firstNames)-1)]. ' ' .$lastNames[rand(0, count($lastNames)-1)].' ';
  
  // Sleep for $sleep seconds
  sleep($sleep);

  return $resource;

};


///////////////////
// Test memcached cache
if ($command == 'memcached') {
  
  $cache = new \inopx\cache\CacheMethodMemcached('127.0.0.1', 11211, $lockTimeout);
  
  // Setting optional prefix
  $cache->setCacheKeyPrefix($prefix);
  
  echo '[Memcached] Cached value = '.$cache->get($group, $key, $lifetimeInSeconds, $createCallback);
  
}


///////////////////
// Test PDO cache - MySQL
if ($command == 'pdo-mysql-create-table' || $command == 'pdo-mysql' || $command == 'pdo-mysql-drop-table' ) {
  
  $options = array(
      PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
  ); 

  $PDO = new \PDO($dsn, $user, $pass, $options);
  
  $cache = new \inopx\cache\CacheMethodPDO($PDO, \inopx\cache\CacheMethodPDO::SQL_DIALECT_MYSQL, $lockTimeout);
  
  // Setting optional prefix
  $cache->setCacheKeyPrefix($prefix);
  
  // Test table creation
  if ($command == 'pdo-mysql-create-table') {
    
    if ($cache->createSQLTable()) {

      echo 'Create table OK!';

    }
    else {

      echo 'Create table ERROR!';

    }
  }
  
  // Test table dropping
  if ($command == 'pdo-mysql-drop-table') {
    
    if ($cache->dropSQLTable()) {

      echo 'Drop table OK!';

    }
    else {

      echo 'Drop table ERROR!';

    }
  }
  
  if ($command == 'pdo-mysql') {
  
    echo '[PDO MySQL] Cached value = '.$cache->get($group, $key, $lifetimeInSeconds, $createCallback);
  
  }
  
}

///////////////////
// Test PDO cache - PostgreSQL
if ($command == 'pdo-pgsql-create-table' || $command == 'pdo-pgsql' || $command == 'pdo-pgsql-drop-table' ) {
  
  $PDO = new \PDO($dsn, $user, $pass);
  
  $cache = new \inopx\cache\CacheMethodPDO($PDO, \inopx\cache\CacheMethodPDO::SQL_DIALECT_POSTGRESQL, $lockTimeout);
  
  // Setting optional prefix
  $cache->setCacheKeyPrefix($prefix);
  
  
  if ($command == 'pdo-pgsql-create-table') {
    
    if ($cache->createSQLTable()) {

      echo 'Create table OK!';

    }
    else {

      echo 'Create table ERROR!';

    }
  }
  
  // Test table dropping
  if ($command == 'pdo-pgsql-drop-table') {
    
    if ($cache->dropSQLTable()) {

      echo 'Drop table OK!';

    }
    else {

      echo 'Drop table ERROR!';

    }
  }
  
  
  
  // Test 
  if ($command == 'pdo-pgsql') {
    
    echo '[PDO PostgreSQL] Cached value = '.$cache->get($group, $key, $lifetimeInSeconds, $createCallback);
    
  }
  
}


///////////////////
// Test file cache
if ($command == 'file') {
  
  
  
  $dir = __DIR__.'/inopx_cache';
  if (!file_exists($dir)) {
    mkdir($dir, 0775);
  }
  
  $cache = new \inopx\cache\CacheMethodFile($dir, $lockTimeout);
  
  // Setting optional prefix
  $cache->setCacheKeyPrefix($prefix);
  
  echo '[File] Cached value = '.$cache->get($group, $key, $lifetimeInSeconds, $createCallback);
  

}