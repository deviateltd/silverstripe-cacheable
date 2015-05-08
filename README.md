# SilverStripe Cacheable

Cachable was originally designed to cache navigation hierarchies as objects to improve
site performance, but has since been re-written to allow any object to be cached.

At its core is `Zend_Cache` and as such the module can use either the memcache or
memcached Zend backends, see: `code/_config.php`.

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
be run as the webserver user e.g. www-data via a crontask on your server(s).

Cache's are also selectively re-built when objects are published or removed via the CMS. 
See the various `onAfterXX()` methods in `code/extensions/Cacheable.php`.

__Note:__ If your site has many 100s of pages, and you attempt to run the task
via the browser, you'll likely exceed one or both of PHP's `max_execution_time` and/or
`memory_limit` ini settings limits. The advantage of the CLI is that on some systems
notably Debian-like O/S', the CLI SAPI is automatically allocated an unlimited 
execution time and memory limit without the need to manually call `ini_set()` anywhere.

If you want to boost these PHP settings as a one off for these tasks, look at PHP's `-d` 
switch that allows you to arbitrarily override settings normally set in scripts
using `ini_set()`.

### How to cache an object

The object(s) that the module will cache are decided simply by the addition of a special
`<% with %>` block. For example; by wrapping the following block around your call(s) to `$Menu()` and assuming
you've primed your cache using the BuildTask above, this will vastly improve the performance
of your site:

    <% with $CachedData %>
    ...Menu template logic here...
    <% end_with %>


### Options

By default the module will use `memcached` as its chosen cache-store if the extension
is loaded. However this can be overridden in your project's YML config, possible values
are `memcache`, `memcached` or `fs`:

    CacheableConfig:
      # Use Filesystem instead of default memcached
      cache_mode: fs

The module uses its own default "server" parameters to pass to both `memcached` and `memcache`
but there is some scope to override these in your project's YML config:

    CacheableConfig:
      # Override module defaults for the 'server' array
      server_opts:
        memcached:
          host: localhost
          port: 11212
          weight: 2

By default the module will attempt to rebuild the cache if one doesn't exist, whenever
a user hits the site. For sites with a large number of page objects, this probably isn't
a good idea, so this can be overridden in config also:

    CacheableConfig:
    # Instruct Cacheable not to try and build a cache via the "first user pays" approach
      build_cache_onload: false

__Note:__ The cache rebuild is also skipped when a flush is in effect.

The Rebuild task can be passed a `Versioned` stage "Stage" or "Live" which will restrict
rebuilding the cache to just the passed mode, thus:

    #> sudo -u www-data ./framework/sake dev/tasks/CacheableNavigation_Rebuild Mode=Live

## FAQ

 Q: Why the references to "CacheableNavigation" all over the place?

 A: The module was originally designed to cache mega/side/footer menu's which are 
 usually the biggest performance killers on any moderately sized SilverStripe site.
 These references will be gradually refactored out.

 Q: How does this module compare with SilverStripe's [Partial Caching](http://doc.silverstripe.org/en/developer_guides/performance/partial_caching) feature?

 A: A full performance comparison methodology has been prepared, and we await the results. However,
 `Cacheable` improves over Partial Caching in that, there's never one user who needs to
 bare the overhead of priming the cache. With `Cacheable` this is done via a BuildTask with the added
 bonus of avoiding that rare beast the [Cache Stampede](http://en.wikipedia.org/wiki/Cache_stampede).

 Q: How does this module compare with a site without it, but with an Opcode Cache running?

 A: A full performance comparison methodology has been prepared, and we await the results.