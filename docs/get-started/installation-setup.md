# Installation & Setup
You can install Comments via the plugin store, or through Composer.

## Craft Plugin Store
To install **Comments**, navigate to the _Plugin Store_ section of your Craft control panel, search for `Comments`, and click the _Try_ button.

## Composer
You can also add the package to your project using Composer and the command line.

1. Open your terminal and go to your Craft project:
```shell
cd /path/to/project
```

2. Then tell Composer to require the plugin, and Craft to install it:
```shell
composer require verbb/comments && php craft plugin/install comments
```

Composer also installs `verbb/editor`, which powers rich authoring, and `verbb/protect`, which runs the spam checks. Both are Composer dependencies that Comments loads itself, so neither appears as a separate plugin for you to install or enable.

## Licensing
You can try Comments in a development environment for as long as you like. Once your site goes live, you are required to purchase a license for the plugin.

For more information, see [Craft's Commercial Plugin Licensing](https://craftcms.com/docs/5.x/system/plugins.html#commercial-plugin-licensing).
