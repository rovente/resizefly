<?php
/**
 * Created by PhpStorm.
 * User: alpipego
 * Date: 14.07.2017
 * Time: 10:45
 */

use Alpipego\Resizefly\Common\Composer\Autoload\ClassLoader;
use Alpipego\Resizefly\Plugin;
use Alpipego\Resizefly\Upload\Uploads;

require_once __DIR__ . '/../src/Common/Composer/Autoload/ClassLoader.php';

$classLoader = new ClassLoader();
$classLoader->setPsr4('Alpipego\\Resizefly\\', realpath(__DIR__ . '/../src/'));
$classLoader->register();

require_once __DIR__ . '/functions.php';

add_action('plugins_loaded', function () use ($classLoader) {
    $plugin = new Plugin();
    $plugin->addDefiniton(__DIR__ . '/config/plugin.php');
    $plugin->loadTextdomain(__DIR__ . '/../languages');

    $file = realpath(__DIR__ . '/../resizefly.php');
    $plugin['config.path']     = trailingslashit(plugin_dir_path($file));
    $plugin['config.url']      = plugin_dir_url($file);
    $plugin['config.basename'] = plugin_basename($file);
    $plugin['config.siteurl']  = get_bloginfo('url');
    $plugin['config.version']  = '2.0.1';

    // settings/filterable configuration values
    $plugin['options.cache.suffix']      = get_option('resizefly_resized_path', 'resizefly');
    $plugin['options.duplicates.suffix'] = apply_filters('resizefly/duplicate/dir', 'resizefly-duplicate');

    // set the cache path throughout the plugin
    $plugin['options.cache.path'] = function (Plugin $plugin) {
        return trailingslashit($plugin->get(Uploads::class)
                                      ->getBasePath()) . trim($plugin->get('options.cache.suffix'),
                DIRECTORY_SEPARATOR);
    };
    $plugin['options.cache.url']  = function (Plugin $plugin) {
        return trailingslashit($plugin->get(Uploads::class)
                                      ->getBaseUrl()) . trim($plugin->get('options.cache.suffix'),
                DIRECTORY_SEPARATOR);
    };
    // set the duplicates path
    $plugin['options.duplicates.path'] = function (Plugin $plugin) {
        return trailingslashit($plugin->get(Uploads::class)
                                      ->getBasePath()) . trim($plugin->get('options.duplicates.suffix'),
                DIRECTORY_SEPARATOR);
    };

    $plugin->offsetSet('loader', $classLoader);

    // filter for addons to register themselves
    $plugin->offsetSet('addons', apply_filters('resizefly/addons', []));

    foreach ($plugin->get('addons') as $addonName => $addon) {
        add_filter('resizefly/addons/' . $addonName, function () use ($plugin) {
            return $plugin;
        });
    }

    // Add own implementation to image editors
    add_filter('wp_image_editors', function (array $editors) {
        array_unshift($editors, '\\Alpipego\\Resizefly\\Image\\EditorImagick');

        return $editors;
    });

    if (is_admin()) {
        $plugin->addDefiniton(__DIR__ . '/config/admin.php');

        require_once __DIR__ . '/actions/activation.php';
    }

    // save options to retrieve them on uninstall
    update_option('resizefly_options', $plugin->get('options'), false);

    $plugin->run();

    // register user added image sizes
    require_once __DIR__ . '/actions/after-setup-theme.php';

    // check if image size in attachment metadata
    require_once __DIR__ . '/actions/wp-get-attachment-src.php';

    // handle image resizing
    require_once __DIR__ . '/actions/template-redirect.php';

});
