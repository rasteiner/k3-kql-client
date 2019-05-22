# KQL Client

Run extended Kirby queries in your frontend. This is an alternative to the standard Kirby API Client. 

> This plugin is completely free and published "as is" under the MIT license. However, if you are using it in a commercial project, this work is saving you some time or you just want to help me keep up with maintenance, please consider [buying me caffeine](https://buymeacoff.ee/zLFxgCHlG) or purchasing your license(s) through my [affiliate link](https://a.paddle.com/v2/click/1129/36164?link=1170).

## Example: Get all listed notes

```js
await kql `notes.children.listed.limit(3).project(${ { title: 'this.title.value', tags: 'this.tags.split' } })`
```

Results in: 

```json
{
  "https://example.com/notes/across-the-ocean": {
    "title": "Across the ocean",
    "tags": [
      "ocean",
      "pacific"
    ]
  },
  "https://example.com/notes/a-night-in-the-forest": {
    "title": "A night in the forest",
    "tags": [
      "forest",
      "trees"
    ]
  },
  "https://example.com/notes/in-the-jungle-of-sumatra": {
    "title": "In the jungle of Sumatra",
    "tags": [
      "jungle",
      "nature",
      "sumatra",
      "plants"
    ]
  }
}
```

## Install
Download and extract or git submodule this repository into a folder of `site/plugins`.

### Include script into template
The client script needs to be loaded. The simplest way is to use the `KQL::clientScript()` helper.

```php
<?= KQL::clientScript() ?>
```
This produces a script tag with the necessary configuration automatically. 

## Syntax
The query syntax is an extension of the Kirby query syntax offered in 3.2.
Everything that works there, should also work here. 

### Additional features
In addition to the normal syntax, KQL offers some more features:

#### Falsy Coalescing Operator (`??`):
Fallback to other values if the first one is either `null`, `false`, `""`, "empty" (on fields, collections and arrays) or if one of the called methods throws an Exception. 

Example:
```js
await kql `(site.page("not-existing") ?? site.page("notes")).title.html.value ?? "Fallback"`
// > "Notes"

await kql `(site.page("not-existing") ?? site.page("also-not-existing")).title.html.value ?? "Fallback"`
// > "Fallback"
```

#### Array Subscript Operator (`[index]`)
Get access to an element of an array or a Kirby Collection with square brackets (like in javascript).
This also allows you to dynamically define the index with an expression or use indexes that wouldn't otherwise be allowed by the `.` operator syntax. 

When used on a Kirby Collection it acts as syntax sugar for [`$collection->nth()`](https://getkirby.com/docs/reference/@/cms/collection/nth)

Examples:
```js 
await kql `["this", "is", "an", "array"][3]`
//> "array"

await kql `notes.children.listed[doubleOf(2)]` //you need to have "notes" and "doubleOf" defined in config
//> the 4th listed note 


const obj = {
    "not an ideal property name": "hello"
}
await kql `${obj}["not an ideal property name"]`
//> "hello"
```

#### Configurable server values:
The variables you have access to are configurable ([see below](#global-variables)).

Example:
```js
await kql `notes.filterBy('date', '>=', today)`
```

#### Injected client values:
The query syntax by itself allows you to use some literal values:
 - Booleans: `true`, `false`
 - Null: `null`
 - Strings: `"line 1 \n line 2"`, `'line 1 \n also line 1'`
 - Integers: `1`, `100`
 - Floats: `3.14`, `.2`
 - Arrays: `[ 10, [ "nested", "array" ], true, notes ]`

Sometimes this isn't enough. E.g there is no "object literal" or no "associative array literal". 
For this reason, the KQL client allows you to inject objects (or really any serializable value) from the clientside. This happens via template strings:

```js
//I have a logged in session here

const newNote = {
  slug: 'a-new-note',
  template: 'note',
  content: {
    title: 'A new note',
    text: 'Hello world!\nThis is a new note'
  }
}

await kql `site.page('notes').createChild(${newNote})`

```

#### Projections
The plugin also includes a "Pages Method" and a "Page Method", both called `project` which helps retrieving the values we are interested in.
The "mapping" works by passing the `project` method an object, where you associate a key to a query.

`this` refers to the "current page".

Example:

```js
const countChildrenProjection = { 
  title: 'this.title.html.value',
  childCount: 'this.children.listed.count'
}

await kql `site.children.listed.project(${ countChildrenProjection })`
```

returns:

```json
{
  "https://example.com/photography": {
    "title": "Photography",
    "childCount": 9
  },
  "https://example.com/notes": {
    "title": "Notes",
    "childCount": 7
  },
  "https://example.com/about": {
    "title": "About us",
    "childCount": 0
  }
}
```

## Config

### Global variables

The queries are run against a set of configurable variables. 
By default, a variable `site` (which is set to `site()`) is accessible. 

You can configure your own variables in your config.php file:

```php
<?php 
return [
    'rasteiner.kql.get-globals' => function() {
        return [
            'site' => site(),
            'notes' => page('notes'),
            'today' => date('Y-m-d')
        ];
    }
];
```

### Enabling public access
By default the API is restricted to only loggen in users. If you need the api to be public, set the `rasteiner.kql.public` config value to `true`.
When enabling public access, security becomes even more important; consider setting up a Whitelist of allowed function calls. 

### Whitelisting methods
By default no whitelist is set up. You can create one in your config file. 

Only methods present in the whitelist will be callable directly from the query.
This doesn't mean that they can't be called at all: if you whitelist method **A** and not method **B**. Method **B** can still be called by **A**.

Example config:

```php
<?php

return [
    'rasteiner.kql.whitelist' => [
        'Kirby\\Cms\\Pages' => [
            'listed',
            'project',
        ],
        'Kirby\\Cms\\Page' => [
            'children',
            'title',
            'tags'
        ],
        'Kirby\\Cms\\Field' => [
            'html',
            'value',
            'split'
        ]
    ]
];
```


### Blacklisting methods

By default access to these methods is denied:

```
Kirby\Cms\User::loginPasswordless
Kirby\Cms\App::impersonate
Kirby\Cms\Model::drafts
Kirby\Cms\Model::childrenAndDrafts
Kirby\Cms\Model::query
Kirby\Cms\Model::toString
Kirby\Cms\Model::createNum
```

Values in the blacklist override those in the whitelist.

You can add your own via the `rasteiner.kql.blacklist` config option.

Example:

if for some reason you want to disable calls to `kirbytext()`
```php
<?php 
return [
    'rasteiner.kql.blacklist' => [
        'Kirby\\Cms\\Field' => [
            'kirbytext',
            'kt'
        ]
    ]
];
```

#### Disabling default blacklist
You shouldn't do this. 

But if you really want to (you might be running the plugin in a controlled environment), you could use the `rasteiner.kql.override-default-blacklist` option (set that to `true`).
