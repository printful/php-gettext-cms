# PHP Gettext CMS backend

[![Master](https://scrutinizer-ci.com/g/printful/php-gettext-cms/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/printful/php-gettext-cms/)
[![Master](https://travis-ci.org/printful/php-gettext-cms.svg?branch=master)](https://travis-ci.org/printful/php-gettext-cms#)
[![Code Coverage](https://scrutinizer-ci.com/g/printful/php-gettext-cms/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/printful/php-gettext-cms/?branch=master)

### Workflow

1. Specify which directories and files (extension) to search for
2. Retrieve the list of translation objects
3. Import translation objects in database
4. Export untranslated messages
5. Import translated files to database
6. Export javascript translations for frontend

### TODO's

* Config class, where we can specify
    * locales
    * domains
    * scan directories/files/extensions
    * export directories
    * interface for file storage
    * interface for exported js storage
    
* Store in database
    * Extensions (from references)
    * Is JS translation
    
* When saving a translation file from upload, do not disable existing translation files
* When extracting translations from code, then disable all existing translation prior to saving so we filter out unneeded translations