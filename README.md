# PHP Cache Slamming problem
Cache slamming is an issue people often doesn't know about, but it's making most of the caching systems pretty ineffective, regardless of caching storage method: files, memcached, database etc.

It's because problem lies in lack of process synchronisation not the storage method.

An example of thread racing and cache slamming is shown below:

Let's say we want to cache very resource consuming work. Let's say it involves several DB calls, and overally it takes few seconds to complete, which is very long on busy sytems.

On busy system there can be few or more HTTP requests per sec. requiring such resource from cache, and here is what happens when resource is not cached, or it's expired:

1. First process fails to read resource from the cache, then begins to create resource, which will take few seconds and a lot of server power: processor/memory/io.

1. In the meantime, when first process is creating the resource and consuming server resources, other precesses are trying to read cache, fails, and doing the same work what process nr 1 is doing.

1. Performance downspike happens, everything is slowed down, magnified by number of concurrent threads, and load the job is creating. It continues to the moment when last of the processes will put the resource in the cache, and in extreme situations can freeze the website.

**This is called cache slamming and it's wrong!**

> There should be only one process creating the resource at the time, while others should yeld, wait and sleep until first proces will finish the job. After that the sleeping processes should be woken up and read newly created resource from cache.

# The Solution to Slamming and basic No Slam Cache usage


No Slam Cache Package is a solution to Cache Slamming Problem, providing process synchronisation using PECL Sync package and SyncReaderWriter Class: http://php.net/manual/en/class.syncreaderwriter.php. 

It is many readers, one writer at once synchronisation model.

Using No Slam Cache requires different than usual approach to creating the resource, NOT like this:

`if (!resource in cache) {`

`1. create resource`

`2. put in cache`

`}`

It must be done in particular manner, casting checking if resource exists and it's not expired on cache manager:

`$cache = new CacheMethod();`

`$cache->get($group, $key, $lifetimeInSeconds, $createCallback);`

Where **$group** is a cache group - think of it like name of SQL table, and **$key** is a cache key, think of it like unique ID of row in the table. 

Pair **$group** and **$key** must be unique, but **$key** value can be repeated in different Groups.

**$lifetimeInSeconds** - self explanatory

**$createCallback** - it's no arguments callback function which will create the resource, when it is expired or not exist in the Cache.

Whole process of reading/writing to the Cache is synchronised, that is: **only one process will write to the cache while others will wait and then get recently created resource, instead of slamming the cache**. 

While resource exists in the Cache and it's not expired, it can be read concurrently by many PHP processes at once.

Real example with cache method file:

`$group = 'products';`

`$key = 150;`

`$lifetimeInSeconds = 30;`

`$createCallback = function() use($group, $key) { return 'I was created at '.date('Y-m-d H:i:s').' for group '.$group.' and key '.$key; }`

`$dir = __DIR__.'/inopx_cache';`

`if (!file_exists($dir)) {`

`mkdir($dir, 0775);`

`}`

`$cache = new \inopx\cache\CacheMethodFile($dir);`

`echo 'Cached value = '.$cache->get($group, $key, $lifetimeInSeconds, $createCallback);`

# Requirements

You need to install PECL Sync extension in order to use No Slam Cache: https://pecl.php.net/package/sync

# Startup / boostrap

Include **classloader.php** into your bootstrap file/procedure to load package classes.

# Cache Methods commons

Every Cache Method implements interface **\inopx\cache\InterfaceCacheMethod** and comes with constructor containing **$syncTimeoutSeconds** variable with default value of 30.

Interface **\inopx\cache\InterfaceCacheMethod** consists of **main get method** described earlier, and few others, look at API Documentation (compressed in doc-api.zip) for more details.

**$syncTimeoutSeconds** is a value of lock timeout, that is, if process waits longer than **$syncTimeoutSeconds** seconds, it fails to acquire lock and then, instead of throwing error, creates resource using callback and writes to the cache.

Because of that, it is important to override this value in case when the work creating resource may take longer time to complete.


# Cache Method Memcached

Class **\inopx\cache\CacheMethodMemcached** is a Memcached Storage Method Class with constructor:

`__construct( $memcachedHost = '127.0.0.1', $memcachedPort = 11211, $syncTimeoutSeconds = 30 )`

Where constructor arguments are pretty much self-explanatory.

# Cache Method PDO

Class **\inopx\cache\CacheMethodPDO** is a Database storage Method Class with constructor:

`__construct( PDO $PDOConnection, integer $sqlDialect = null, integer $syncTimeoutSeconds = 30 )`

Where **$PDOConnection** is a established connection to database (PDO Class), and **$sqlDialect** is one of the two dialects supported by this class: **\inopx\cache\CacheMethodPDO::SQL_DIALECT_MYSQL** or **\inopx\cache\CacheMethodPDO::SQL_DIALECT_POSTGRESQL**.

Before you may use this cache method, you must create database table by executing method **createSQLTable**.

Name of the Table and names of the Columns can be configured by altering class variables like: **$SQLTableName**, **$SQLColumnGroupName**, **$SQLColumnKeyName** and so on - look at API Documentation for more.

# Cache Method File

Class **\inopx\cache\CacheMethodFile** is a File Storage Method Class with constructor:

`__construct( string $baseDir = 'inopx_cache', integer $syncTimeoutSeconds = 30 )`

Where **$baseDir** is a base directory for cache files, without trailing separator. The base directory must exist and be writable for PHP.

For every group there will be spearated subdirectory in the base dir named after the group, but sanitised first for proper filesystem directory name.

Every key will be converted to number by **crc32** function if its not numeric, based on that number, the special subdirectory structure will be created if number exceeds 100. 

That is to ensure that no more than 100 files and 10 subdirectories are in every subdirectory of the group directory. 

Look at **\inopx\io\IOTool** Class and Method **getClusteredDir**

On some filesystems large number of files and/or subdirectories in one directory may leed to long disk seek times, and slow down IO. "Directory clustering" is preventing this problem from happening.

There is still potential problem of huge number of groups and therefore, huge number of subdirectories in the base directory.

**Beware of special characters in groups and keys** when using this cache method, as group and key will be respectivery subdirectory name and file name containing cached value. Those values will be sanitised first, which may lead to coflict when there are two similar keys with difference in special characters only.

# The Deadlock Problem

When using any kind of process synchronisation, a Deadlock problem may occur.

It happens when:

1. process nr 1 acquire lock A
2. then process nr 2 acquire lock B
3. then process nr 1 is trying to acquire lock B while process nr 2 is trying to acquire lock A

This leads to never-ending or lock timeout error situation called Deadlock.

If you need more detailed explanation, search the web, for example: https://en.wikipedia.org/wiki/Deadlock

The best solution to avoid deadlocks is to never use nested locks, that is: when you acquire first lock, do not acquire any other locks until you unlock the first lock. This is smart usage of locking and guarantees no deadlocks.

Regarding No Slam Cache, it means you should never do any synchronisation inside callback function creating the resource, especially using the cache with synchronisation.

Callback like this is WRONG:

`$createCallback = function() use($myVar)`

`{`

`$value = 'My Value';`

`$cache = new \inopx\cache\CacheMethodFile('inopx_cache');`

`$value2 = $cache->get('mygroup',123,30, function() { return uniqid(''); });`

`return $value . ' ' . $value2;`

`}`

...because if it's used with cache, it will create nested lock, and if other process is locking in reversed order, there is possibility of deadlock.

# Test script

You can find **cli-test-cache.php** script in the main directory of No Slam Cache package.

It meant to be run in CLI mode for general purpose testing of Cache Methods and for testing the concurrency.

You may open several Command Line Windows, put command in every window 'php cli-test-cache.php', and test concurrency.

The test script is initially configured for that, as it sleeps for 10 seconds in the callback function to give you time to execute script in the rest of the opened windows.
