# SilverStripe Cacheable

[![License](https://poser.pugx.org/deviateltd/silverstripe-cacheable/license.svg)](https://github.com/deviateltd/silverstripe-cacheable/LICENCE)

Cachable was originally designed to cache navigation hierarchies as objects to improve
site performance, but has since be re-written to allow any object to be cached.

At its core is `Zend_Cache` and as such the module can use either the memcache or
memcached Zend backends (See [code/_config.php](code/_config.php)).

## Installation

  1) Git Clone


    #> git clone https://github.com/deviate/silverstripe-cacheable.git

  2) Composer command


    composer require deviate/silverstripe-cacheable dev-master

  3) Composer (Manual)

Edit your project's `composer.json` as follows:

Add a new line under the "require" block:


    deviate/silverstripe-cacheable

Add a new block under the "repositories" block:


      {
       "type": "vcs",
       "url": "https://github.com/deviate/silverstripe-cacheable.git"
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
various `onAfterXX()` methods in [code/extensions/Cacheable.php](code/extensions/Cacheable.php).
