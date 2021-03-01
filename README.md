# Posts 2 Posts

Create many-to-many relationships between all types of posts.

## Tested on

- PHP version 7.4
- WordPress version 5.6

## Installation

Add this repository in your `composer.json`:

```json
...
  "repositories": [
    {
      "type": "github",
      "url": "git@github.com:starise/posts-to-posts"
    },
  ],
...
  "require": {
    "php": ">=7.4",
    "composer/installers": "~1.10",
    "starise/posts-to-posts": "^1.10"
  },
...
  "extra": {
    "installer-paths": {
      "public/app/mu-plugins/{$name}/": [
        "type:wordpress-muplugin",
        "starise/posts-to-posts"
      ]
    }
  }
...
```

The plugin will be installed when you'll run:

```
composer install
```

## Support & Maintenance

This plugin is no longer under active development. This repo contains a fork for personal use with updated dependencies to make it work on more recent versions of PHP & WordPress. Feel free to use this repo if you need, but I'll offer no support for it.

Links: [**Documentation**](http://github.com/scribu/wp-posts-to-posts/wiki) | [Original Plugin](https://it.wordpress.org/plugins/posts-to-posts/) | [Original Repo](https://github.com/scribu/wp-posts-to-posts)
