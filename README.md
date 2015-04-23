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

Insert the `Cacheable` extension into your project's config.yml file so that it extends SiteTree:


    SiteTree:
      extensions:
        - Cacheable

Now run `dev/build` via the browser or command line - and don't forget to flush.

## Usage

A cache is built after running the `CacheableNavigation_Rebuild` task. This should be
setup to run as a crontask on your server(s).

Cache's are also selectively re-built when objects are published via the CMS. See the
various `onAfterXX()` methods in `code/extensions/Cacheable.php`.
