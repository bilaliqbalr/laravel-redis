# Laravel Redis

[![Latest Version on Packagist](https://img.shields.io/packagist/v/bilaliqbalr/laravel-redis.svg?style=flat-square)](https://packagist.org/packages/bilaliqbalr/laravel-redis)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/bilaliqbalr/laravel-redis/run-tests?label=tests)](https://github.com/bilaliqbalr/laravel-redis/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/bilaliqbalr/laravel-redis/Check%20&%20fix%20styling?label=code%20style)](https://github.com/bilaliqbalr/laravel-redis/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/bilaliqbalr/laravel-redis.svg?style=flat-square)](https://packagist.org/packages/bilaliqbalr/laravel-redis)

---
As name suggested this package will let you use Redis as a database instead of using it just for caching purpose.
It works almost the same way as using Laravel Eloquent but with some differences.
With this package forget the pain of naming keys and managing them in Redis. Some of the core features are as follows:

1. No need to create migration files, just provide required columns in `$fillable` and this package will take care of the rest
2. Perform CRUD operations just like doing them in Laravel
3. Search model functionality
4. Managing relations
---

## Installation

You can install the package via composer:

```bash
composer require bilaliqbalr/laravel-redis
```

You can publish the config file with:
```bash
php artisan vendor:publish --provider="Bilaliqbalr\LaravelRedis\LaravelRedisServiceProvider" --tag="laravel-redis-config"
```

## Usage

To create new redis model run this command 
```php
php artisan redis:model Post
```

### Prefixes
Prefixes are used to maintain the key structure for specific model, this package creates prefixes by default using Model name, 
and in case you want to change it you can do this as follow
```php
public function prefix() : string
{
    return 'post';
}
```

### Change connection
You can change redis connection in model as well
```php
protected $connection = "redis";
```

### Searching model by specific column
In case you need to get model based on specific field, you can do this by using `$searchBy` 
where you just need to specify column names in the list and this package will store a new key value pair
where key is `{model}:column:%s` (`%s` is the column value) where value will be the model id to fetch required model. 

```php
protected $searchBy = [
    'title',
];
```

To get model based on that title field, you can do this as follows
```php
$post = Post::searchByTitle("iphone");

# This works the same way as we do while using eloquent
# Post::where('title', 'iphone')->first();
```

Other operations like create, update and delete works same as in Laravel
```php
# Creating new post
$post = Post::create([
    'user_id' => 1,
    'title' => 'iPhone 13 release',
    'description' => 'Lorem ipsum dolor',
]);

# Getting data is a bit different
$post = Post::get(1); // 1 is the model id

# Get post by title
$post = Post::searchByTitle('iphone');

# Update post info
$post->update([
    'description' => 'Lorem ipsum dolor sat amet'
]);

# Delete post
$post->delete();
// or 
Post::destroy(1);
```

### Managing model relations

With this package you can even create relation between models but that is not like the one in Laravel, 
as redis is not a relational database.

```php
# User.php

# Adding post relation
public function posts() {
    return $this->relation(new \App\Models\Redis\Post);
}
```

```php
# Creating post under user 
# Below will automatically add user_id field in the Post model. 
$post = $user->posts()->create([
    'title' => 'iPhone 13 pro',
    'description' => 'Lorem ipsum dolor',
]);

# Getting all user posts
$posts = $user->post()->get();
$paginatedPosts = $user->post()->paginate();
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Credits

- [Bilal Iqbal](https://github.com/bilaliqbalr)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
