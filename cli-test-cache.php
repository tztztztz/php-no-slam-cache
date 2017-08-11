<?php
require __DIR__.\DIRECTORY_SEPARATOR.'classloader.php';

////////////////////////////////////////////////////////////////////////////
//
//                    *****     HOW TO TEST     *****
// 
// 
// Replace FALSE with TRUE in desired cache method section.
// 
// Prepare two or more command line windows, with the same directory opened where this file is located.
// 
// Put execute code to every window: "php cli-test-cache.php"
// 
// Execute code in every window and check if resource was being created only once, or many times for synchronisation off.
// 
// For PDO caching method You need to create DB table first by changing FALSE with TRUE and then run this script one time.
// 
// Check the PDO method section code.
//
////////////////////////////////////////////////////////////////////////////




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

$createCallback = function() use($firstNames, $lastNames) {
  
  // CODE COMMENTED BELOW IS AN EXAMPLE WHAT YOU SHOUD NOT DO: 
  // CREATE CACHE WITHIN CACHED RESOURCE CREATION PROCESS, BECAUSE IT MAY LEAD TO DEADLOCK
  // The same goes for any other locking mechanism - do not use any locks inside creation process
  // 
  //$cache = new \inopx\cache\CacheMethodFile(\INOPX_NOPUB_DIR.'/_inopx_cache');
  //$value2 = $cache->getCachedValue_('aaa', '10324', 30, function() { return uniqid(''); });
  
  // Creating resource
  $resource = date('Y-m-d H:i:s').' '.$firstNames[rand(0, count($firstNames)-1)]. ' ' .$lastNames[rand(0, count($lastNames)-1)].' ';
  
  // Sleep for 10 seconds
  sleep(10);

  return $resource;

};


////////////////////////////////////////////////////////////////////////////
//
// Change FALSE to TRUE below in desired Section to perform Test
// 
////////////////////////////////////////////////////////////////////////////



///////////////////
// Test memcached cache
if (FALSE) {
  
  $cache = new \inopx\cache\CacheMethodMemcached('127.0.0.1', 11211);
  
  echo '[Memcached] Cached value = '.$cache->get($group, $key, $lifetimeInSeconds, $createCallback);
  
}


///////////////////
// Test PDO cache - MySQL
if (FALSE) {
  
  // Set proper DSN, user, and password
  $dsn = 'mysql:host=localhost;dbname=your_db_name';
  
  $username = 'bogus';
  $password = 'bogus';
  
  $options = array(
      PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
  ); 

  $PDO = new \PDO($dsn, $username, $password, $options);
  
  $cache = new \inopx\cache\CacheMethodPDO($PDO, \inopx\cache\CacheMethodPDO::SQL_DIALECT_MYSQL);
  
  // Test table creation
  if (FALSE) {
    
    if ($cache->createSQLTable()) {

      echo 'Create table OK!';

    }
    else {

      echo 'Create table ERROR!';

    }
  }
  
  // Test table dropping
  if (FALSE) {
    
    if ($cache->dropSQLTable()) {

      echo 'Drop table OK!';

    }
    else {

      echo 'Drop table ERROR!';

    }
  }
  
  echo '[PDO MySQL] Cached value = '.$cache->get($group, $key, $lifetimeInSeconds, $createCallback);
  
}

///////////////////
// Test PDO cache - PostgreSQL
if (FALSE) {
  
  $dsn = 'pgsql:host=localhost;port=5432;dbname=your_db_name';
  
  $username = 'bogus';
  $password = 'bogus';
  
  $PDO = new \PDO($dsn, $username, $password);
  
  $cache = new \inopx\cache\CacheMethodPDO($PDO, \inopx\cache\CacheMethodPDO::SQL_DIALECT_POSTGRESQL);
  
  if (FALSE) {
    
    if ($cache->createSQLTable()) {

      echo 'Create table OK!';

    }
    else {

      echo 'Create table ERROR!';

    }
  }
  
  // Test table dropping
  if (FALSE) {
    
    if ($cache->dropSQLTable()) {

      echo 'Drop table OK!';

    }
    else {

      echo 'Drop table ERROR!';

    }
  }
  
  // Test 
  echo '[PDO PostgreSQL] Cached value = '.$cache->get($group, $key, $lifetimeInSeconds, $createCallback);
  
}


///////////////////
// Test file cache
if (FALSE) {

  $dir = __DIR__.'/inopx_cache';
  if (!file_exists($dir)) {
    mkdir($dir, 0775);
  }
  
  $cache = new \inopx\cache\CacheMethodFile($dir);
  
  echo '[File] Cached value = '.$cache->get($group, $key, $lifetimeInSeconds, $createCallback);
  

}