# PHP Cache Slamming problem
Cache slamming is a issue people often doesn't know about, but it's making most of the caching systems pretty ineffective, regardless of caching method: files, memcached, database etc.

It's because problem lies in lack of process synchronisation not the storage method.

Here is example:

Let's say we have very resource consuming work, that we want to cache. Let's say it involves several DB calls, and overally it takes few seconds to complete, which is very long on busy sytems.

Now, on busy system, where can be few or more HTTP requests per sec. demanding such resource, here is what happens when resource is not cached, or it's expired:

1. First process fails to extract cached resource, so it begins to create it, which will take few seconds and a lot of server power (processor/memory/io).

1. In the meantime, when first process is creating the resource and consuming server resources, other precesses are trying to read cache, fails, and doing the same work what process nr 1 is doing.

1. Performance downspike happens, everything is slowed down, and it continues to the moment when last of the processes will put the resource in the cache.

**This is called cache slamming and it's wrong!**

> There should be only one process creating the resource while other should wait and sleep until process nr 1 will finish and put resource in cache, after that rest of the waiting processes should be woken up and extract newly created resource from cache.

# The Solution to Slamming and basic No Slam Cache usage


No Slam Cache Package is a solution to Cache Slamming Problem, providing process synchronisation using PECL Synch package (SyncReaderWriter Class: http://php.net/manual/en/class.syncreaderwriter.php), and many readers one writer at once synchronisation model.

Using No Slam Cache requires different than usual approach to creating the resource, NOT like this:

if (!resurce in cache) {

1. create resource

2. put in cache

}

It must be done like this:

`$cache = new CacheMethod();`

`$cache->get($group, $key, $lifetimeInSeconds, $createCallback);`

Where **$group** is a cache group - think of it like name of SQL table, and **$key** is a cache key, think of it like unique ID of row in the table. Pair $group and $key must be unique, but $key value can be repeated in different groups.

**$lifetimeInSeconds** - self explanatory

**$createCallback** - it's no arguments callback function which will create the resource, but only, if resource is expired or not exist in the cache.

It will be done with synchronisation, that is: only one process will write to the cache while others will wait and then get freshly created resource, instead of slamming the cache.

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

# Cache Methods commons

Every Cache Method implements interface **\inopx\cache\InterfaceCacheMethod** and comes with constructor containing **$syncTimeoutSeconds** variable with default value of 30.

**$syncTimeoutSeconds** is a value of lock timeout, that is, if process waits longer than **$syncTimeoutSeconds** seconds, it fails to acquire lock and then, instead of throwing error, creates resource using callback and writes to the cache.

Because of that, it is important to override this value in case your work may take longer time to complete.


# Cache Method File

Class \inopx\cache\CacheMethodFile is a File Storage Method class with constructor:

`__construct( string $baseDir = 'inopx_cache', integer $syncTimeoutSeconds = 30 )`

Where **$baseDir** is a base directory for cache files, without trailing separator. The base directory must exist and be writable for PHP.

For every group there will be spearated subdirectory in the base dir named after the group, but sanitised first for proper filesystem directory name.

Every key if is not numeric will be converted to number by crc32 function.

Based on that numer, the special subdirectory structure will be created if number exceeds 100. That is to ensure that no more than 100 files and 10 subdirectories are in every subdirectory of the group directory. 

Look at \inopx\io\IOTool Class and Method **getClusteredDir**

On some filesystems large number of files and/or subdirectories in one directory may leed to long disk seek times, and slow down IO. "Directory clustering" is preventing this problem from happening.


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

TO BE CONTINUED
