Requirements

Node + npm (for asset builds)

Composer (for autoload + PHP deps)

WordPress (runtime)

Optionally WP-CLI for POT extraction

Setup

```npm install```

```composer install```


Development

`npm run watch`


Build for Release

`npm run zip`

Directory Conventions

/app — PHP classes (autoloaded)

/assets — source JS/SASS

/dist — compiled assets

/vendor — composer deps