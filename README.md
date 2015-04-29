# SilverStripe Cacheable

Cachable was originally designed to cache navigation hierarchies as objects to improve
site performance, but has since been re-written to allow any object to be cached.

At its core is `Zend_Cache` and as such the module can use either the memcache or
memcached Zend backends `code/_config.php`.

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

If you want to boost these as a one off just to run these tasks, look at PHP's `-d` 
switch that allows you to arbitrarily override settings normally set in scripts
using `ini_set`.
