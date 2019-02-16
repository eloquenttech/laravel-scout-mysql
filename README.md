# Laravel Scout MySQL Driver
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)

This package is a MySQL driver for using laravel scout. The main aim of this package is to emulate a
full featured search implementation in a development/testing environment. Although this package could
be used in production it is not advised. Use either the official Algolia driver or our
[Elasticsearch driver](https://github.com/Eloquent-Technologies/laravel-scout-elastic) for a more 
complete search solution.

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
of the `searchableAs()` method as the name of this table. You will most likely need to define this to avoid table name 
collisions with your models existing table name.
```php
    public function searchableAs()
    {
        return 'customer_index';
    }
```

Initially, you don't need to worry about creating this table, it is done automatically when indexing if the table
doesnt exist. By default, the table will only contain the following columns:
 - The result of `$model->getForeignKey()` - This will also be the primary key.
 - `index` - A `text` field that will be used for the search. This will be a json encoded string of the result of your
   models `toSearchableArray()` method. This allows you to add fields from related resources that can be searched on.
 
However, if you wish to add any where clauses to your search requests, or if you will want to sort them, you can 
define a `searchableProperties()` method on your searchable models. For example:
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
> new *searchable property* field you will also need to create a migration to add the field to the index table.

You wil now be able to use Laravel scout as described in the [official documentation](https://laravel.com/docs/5.7/scout).

## Disclaimer
As mentioned before, we do not advise this to be used in production. We designed this implementation as a way to
stub our [Elasticsearch driver](https://github.com/Eloquent-Technologies/laravel-scout-elastic) for use in
environments where we do not have access to an elastic search instance.

## TODO
- Add the ability to use MySQLs full text search ability on the `index` field, ensuring that the ability to use this
  with an SQLite database is not broken.

## License
The MIT License (MIT).
