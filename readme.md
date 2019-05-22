# KQL Client

Run extended Kirby queries in your frontend. This is an alternative to the standard Kirby API Client. 

> This plugin is completely free and published "as is" under the MIT license. However, if you are using it in a commercial project and want to help me keep up with maintenance, please consider [buying me caffeine](https://buymeacoff.ee/zLFxgCHlG) or purchasing your license(s) through my [affiliate link](https://a.paddle.com/v2/click/1129/36164?link=1170).

## Example: Get all listed notes

```js
await kql `site.page("notes").children.listed.project(${ { title: 1, tags: {split: 1} } })`
```

Results in: 

```json
{
  "https://example.com/notes/across-the-ocean": {
    "title": {
      "value": "Across the ocean"
    },
    "tags": {
      "split": [
        "ocean",
        "pacific"
      ]
    }
  },
  "https://example.com/notes/a-night-in-the-forest": {
    "title": {
      "value": "A night in the forest"
    },
    "tags": {
      "split": [
        "forest",
        "trees"
      ]
    }
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
The plugin also includes a new "Pages Method" and a "Page Method" to help retrieve the values we are interested in.

```js
const countChildrenProjection = { 
  title: 1,
  children: { 
    listed: {
      count: 1
    }
  }
}

await kql `site.index.limit(2).project(${ countChildrenProjection })`
```

returns:

```json
{
  "https://example.com/photography": {
    "title": {
      "value": "Photography"
    },
    "children": {
      "listed": {
        "count": 9
      }
    }
  },
  "https://example.com/photography/animals": {
    "title": {
      "value": "Animals"
    },
    "children": {
      "listed": {
        "count": 0
      }
    }
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

### Blacklisting methods

*With great power comes great responsability...*

By default access to these methods is denied:

```
Kirby\Cms\User::loginPasswordless
Kirby\Cms\App::impersonate
Kirby\Cms\ModelWithContent::drafts
Kirby\Cms\ModelWithContent::childrenAndDrafts
```

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
