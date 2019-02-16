# Laravel Scout MySQL Driver
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)

This package is a MySQL driver for using laravel scout. The main aim of this package is to emulate a
more full featured search implementation in a development/testing environment. Although this package could
be used in production it is not advised. Use either the official Algolia driver or our
[Elasticsearch driver](https://github.com/Eloquent-Technologies/laravel-scout-elastic).

## Contents
- [Installation](#installation)
- [Usage](#usage)
- [License](#license)

## Installation
You can install the package via composer:

``` bash
composer require eloquent/laravel-scout-mysql
```

Laravel will automatically register the packages service provider that is responsible for registering
the elastic driver with the scout engine.

## Usage
After you've published the Laravel Scout package configuration add the following:
```php
    // config/scout.php
    // Set your driver to elasticsearch
    'driver' => env('SCOUT_DRIVER', 'mysql'),

...
    'mysql' => [
        // set to true if your primary keys are UUIDs
        'uuids' => false,
    ],
...
```

This driver works by creating a new *index* table for each of your searchable models. Internally, we use the result
of the `searchableAs()` as the name of this table. You will most likely need to define this to avoid table name 
collisions with your models existing table name.
```php
    public function searchableAs()
    {
        return 'customer_index';
    }
```

Initially, you don't need to worry about creating this table, as it is done automatically when indexing if the table
doesnt exist. By default, the table will contain only to columns:
 - The result of `$model->getForeignKey()` - This will also be the primary key.
 - `index` - `Text` field that will be used for searching. This will be a json encoded string of the result of your
   models `toSearchableArray()`. This allows you to add fields from related resources that can be searched on.
 
 However, if you wish to add any where clauses, or sorting to your search queries you can define a 
 `searchableProperties()` method on your searchable models. For example:
 ```php
    public function searchableProperties()
    {
        return [
            'name' => ['type' => 'text'],
            'email' => ['type' => 'text'],
            'company' => ['type' => 'keyword'],
            'dob' => ['type' => 'date'],
            'number_of_siblings' => ['type' => 'integer'],
            'married' => ['type' => 'boolean'],
        ];
    }
```
Additional fields are added for any field with one of the following types: `keyword`, `integer`, `date`, `boolean`
thus allowing you to filter or sort results based on these fields. 

> You need to ensure that these fields are also returned from your `toSearchableArray()` method to ensure that the 
> values get populated correctly in the index table.

> NOTE: Once index tables are created, you will need to manage them yourself. For example, if you decide to add a
> new field to `searchableProperties()` you will also need to create a migration to add the field to the index table.

You wil now be able to use Laravel scout as described in the [official documentation](https://laravel.com/docs/5.7/scout).

## Disclaimer
As mentioned before, we do not advise this to be used in production. We designed this implementation to work 
seamlessly with our [Elasticsearch scout driver](https://github.com/Eloquent-Technologies/laravel-scout-elastic) and
only use the mysql driver for testing or development when no elasticsearch instance is available.

## TODO
- Add the ability to use MySQLs full text search ability on the `index` field, ensuring that the ability to use this
  with an SQLite database is not broken.

## License
The MIT License (MIT).
