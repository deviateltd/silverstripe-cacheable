# SilverStripe Cacheable

Cachable is an Object Cache for SilverStripe. It was originally designed to only cache SiteTree objects normally found in
website navigation hierarchies, in order to improve site performance. Future versions aim
to allow an increased range of standard SilverStripe DataObjects to be cached.

At its core is `Zend_Cache` and as such the module can use the Memcache, Memcached, APCu or File
Zend Backend cache's. See: `code/_config.php`.

## Installation

  1) Git Clone


    #> git clone https://github.com/deviateltd/silverstripe-cacheable.git

  2) Composer command


    composer require deviateltd/silverstripe-cacheable dev-master

  3) Composer (Manual)

Edit your project's `composer.json` as follows:

Add a new line under the "require" block:


    deviateltd/silverstripe-cacheable

Add a new block under the "repositories" block:


    {
        "type": "vcs",
        "url": "https://github.com/deviateltd/silverstripe-cacheable.git"
    }

Now run `dev/build` via the browser or command line - and don't forget to flush.

## Usage

A cache is built after running the `CacheableNavigation_Rebuild` task. This should 
be run as the webserver user e.g. www-data via a crontask on your server(s):

    #> sudo -u www-data /usr/bin/php path/to/framework/cli-script.php dev/tasks/ProcessJobQueueTask queue=2

A better way is to run this task natively as the www-data user by running the following and pasting
the above command (without 'sudo -u') into the editor that appears - like so:

    #> sudo crontask -u www-data -e

See the [QueuedJobs Github wiki](https://github.com/silverstripe-australia/silverstripe-queuedjobs/wiki) for more info.

This task is in itself, very memory intensive, so it can be passed some parameters to help with debugging 
and to improve performance:

Pass the following to restrict the cache rebuilding to just "Live" or just "Draft" items, this is useful if you know your
CMS users aren't using the preview mode, or you're stress testing only the frontend:

    Stage=Live
    Stage=Stage

By default, the BuildTask will "chunk" your site-tree and push each chunk onto a background 
task using the [QueuedJobs module](https://github.com/silverstripe-australia/silverstripe-queuedjobs), however, if you 
have no need for this, e.g. your site only has a few hundred pages, you can skip it and build the cache in one go like so:

    SkipQueue=1

__Note:__ If your site has many 100s of pages, and you attempt to run the task
via the browser or with `SkipQueue=1`, you may exceed one or both of PHP's `max_execution_time` and/or
`memory_limit` ini settings limits. The advantage of the CLI is that on some systems
notably Debian-like O/S', the CLI SAPI is automatically allocated an unlimited 
execution time and memory limit without the need to manually call `ini_set()` anywhere.

If you want to boost these PHP settings as a one off for these tasks, look at PHP's `-d` 
switch that allows you to arbitrarily override settings normally set in scripts
using `ini_set()`.

Regardless, we recommend using the default mode with QueuedJobs.

Each cached object is also selectively re-built the related CMS content is published, unpublished or deleted in the CMS. 
See the various `onAfterXX()` methods in `code/extensions/Cacheable.php`.

### Using cached objects

Out of the box, and assuming you've already built the cache, the module will handle calls to `$Menu()` in your templates
as follows:

    <% with $CachedData %>
        $Menu(1)
    <% end_with %>

You can also access cache contents directly, allowing you to iterate over the entire SiteTree, use filters etc,
just as though you're dealing with a standard ORM result list in your PHP logic. The following example shows how
to get an `SS_List` of your sitetree, from the ORM or the object-cache:

    /**
     * 
     * Source all items via ORM or Object cache if available and return the resulting 
     * list.
     * 
     * @return SS_List|null
     */
    public function getSiteTree() {
        $list = ArrayList::create();
        $modeParts = explode('.', Versioned::get_reading_mode());
        $mode = array_pop($modeParts);
        
        if(class_exists('Cacheable')) {
            $conf = SiteConfig::current_site_config();
            $cacheService = new CacheableNavigationService($mode, $conf);
            if($cache = $cacheService->getObjectCache()) {
                $list = ArrayList::create($cache->get_site_map());
            }
        } else {
            Versioned::reading_stage($mode);
            $list = DataObject::get('Page');
        }
        
        return $list;
    }


### Options

By default the module will use `file` as its chosen cache-backend. However this can be overridden in your project's YML config. 
Possible values are `memcache`, `memcached` or `apc`:

    CacheableConfig:
      # Use memcached instead of default `file`
      cache_mode: memcached

The module uses its own default "server" and "client" parameters to pass to `memcached` and `memcache`
but there is some scope to override these in your project's YML config:

    CacheableConfig:
      # Override module defaults for the 'server' array
      opts:
        memcached:
          servers:
            host: localhost
            port: 11212
            weight: 2
          client:
            9: 1
            2: 1
            16: true

By default the module will attempt to rebuild the cache if one doesn't exist, whenever
a user hits the site. For sites with a large number of page objects this probably isn't
a good idea, and you should override this in config also:

    CacheableConfig:
    # Instruct Cacheable not to build a cache via the "first user pays" approach
      build_cache_onload: false

__Note:__ The cache rebuild is also skipped when a flush is in effect.

The Rebuild task can be passed a `Versioned` stage "Stage" or "Live" which will restrict
rebuilding the cache to just the passed stage, thus:

    #> sudo -u www-data ./framework/sake dev/tasks/CacheableNavigation_Rebuild Stage=Live

By default only a minimal number of class properties and methods are cached. If your
project makes use of additional properties/methods, simply modify your project's
config.yml file accordingly. E.g. if you had a custom field and method defined in your Page class
called "WibbleField" and "getWibble", you could instruct the module to cache them thus:

    CacheableSiteTree:
      cacheable_fields:
        - WibbleField
      cacheable_functions:
        - getWibble

Similarly you do the same for your custom site config, via `CacheableSiteConfig`.

Out of the box when using the "file" backend, Cacheable will store its object cache files
in a directory called "cacheable" under SilverStripe's temp folder. However in some circumstances
such as when using the [Deploynaut](https://github.com/silverstripe/deploynaut) deployment system, SilverStripe's temp directory is
deleted and re-created, thus truncating your cache entirely. In this case, you can use the `alt_cache_dir`
option in your YML config, which specifies an alternate location that Deploynaut will leave alone:

__Note:__ Any custom directory locations are always made relative to the `assets` directory. This makes
the cache portable on those platforms that replicate / duplicate database and assets onto other VM's or bare metal.

Instruct Cacheable to build the cache under: `assets/_foo/bar/cacheable`:

    CacheableConfig:
      alt_cache_dir: 'foo/bar'

 Instruct Cacheable to build the cache under: `assets/_cacheable`:

    CacheableConfig:
      alt_cache_dir: cacheable

## Cache inspector

If you have CMS admin access, you can request a specific URL to inspect the state
of your caches. The URI is: `/cacheable/inspector` and it provides the following information:

 * No. Cached objects vs Database derived totals - useful for debugging
 * The backend currently in-use
 * Cache file-listing, no. files, total cache disk usage, cache last-updated time etc ("File" backend only)

## FAQ

 Q: Why the references to "CacheableNavigation" all over the place?

 A: The module was originally designed to cache mega/side/footer menu's which source their list data
from the SilverStripe `$Menu()` method as these site-areas are usually the biggest performance killers on any moderately sized SilverStripe site.
 These references will be gradually refactored out and the module made more generic.

 Q: How does this module compare with SilverStripe's [Partial Caching](http://doc.silverstripe.org/en/developer_guides/performance/partial_caching) feature?

 A: A full performance comparison methodology has been prepared, and we await the results. However,
 `Cacheable` improves over Partial Caching in that, there's never one user who needs to
 bare the overhead of priming the cache (1). With `Cacheable` this is done via a BuildTask with the added
 bonus of avoiding that rare beast the [Cache Stampede](http://en.wikipedia.org/wiki/Cache_stampede).

 Q: How does this module compare with a site without it, but with an Opcode Cache running?

 A: A full performance comparison methodology has been prepared, and we await the results.

## Footnotes

 1) This is only true when your project overrides default module behaviour, like so:

    CacheableConfig:
    # Instruct Cacheable not to build a cache via the "first user pays" approach
      build_cache_onload: false