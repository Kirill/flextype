<?php

declare(strict_types=1);

 /**
 * Flextype - Hybrid Content Management System with the freedom of a headless CMS 
 * and with the full functionality of a traditional CMS!
 * 
 * Copyright (c) Sergey Romanenko (https://awilum.github.io)
 *
 * Licensed under The MIT License.
 *
 * For full copyright and license information, please see the LICENSE
 * Redistributions of files must retain the above copyright notice.
 */

namespace Flextype;

use Glowy\Macroable\Macroable;
use Composer\Semver\Semver;
use Flextype\I18n;
use RuntimeException;

use function array_diff_key;
use function array_replace_recursive;
use function arrays;
use function count;
use function filemtime;
use function filesystem;
use function flextype;
use function is_array;
use function md5;
use function trim;

class Plugins
{
    use Macroable;

    /**
     * Locales array
     *
     * @var array
     */
    private array $locales = [];

    /**
     * Constructor
     *
     * @access public
     */
    public function __construct()
    {
        $this->locales = serializers()->yaml()->decode(filesystem()->file(ROOT_DIR . '/src/flextype/locales.yaml')->get());
        $this->init();
    }

    /**
     * Get locales
     *
     * @return array
     *
     * @access public
     */
    public function getLocales(): array
    {
        return $this->locales;
    }

    /**
     * Init Plugins
     *
     * @access protected
     */
    protected function init(): void
    {
        // Set empty plugins item
        registry()->set('plugins', []);

        // Set locale
        $locale = registry()->get('flextype.settings.locale');

        // Get plugins list
        $pluginsList = $this->getPluginsList();
       
        // Get plugins Cache ID
        $pluginsCacheID = $this->getPluginsCacheID($pluginsList);

        // If Plugins List isnt empty then continue
        if (count($pluginsList) <= 0) {
            return;
        }

        // Get plugins from cache or scan plugins folder and create new plugins cache item
        if (cache()->has($pluginsCacheID)) {
            registry()->set('plugins', cache()->get($pluginsCacheID));

            if (cache()->has($locale)) {
                I18n::add(cache()->get($locale), $locale);
            } else {
                // Save plugins dictionary
                $dictionary = $this->getPluginsDictionary($pluginsList, $locale);
                cache()->set($locale, $dictionary[$locale]);
            }
        } else {
            // Init plugin configs
            $plugins               = [];
            $defaultPluginSettings = [];
            $projectPluginSettings = [];
            $defaultPluginManifest = [];

            // Go through...
            foreach ($pluginsList as $plugin) {

                // Set plugin settings directory
                $projectPluginSettingsDir = PATH['project'] . '/config/plugins/' . $plugin['dirname'];

                // Set default plugin settings and manifest files
                $defaultPluginSettingsFile = PATH['project'] . '/plugins/' . $plugin['dirname'] . '/settings.yaml';
                $defaultPluginManifestFile = PATH['project'] . '/plugins/' . $plugin['dirname'] . '/plugin.yaml';

                // Set project plugin settings file
                $projectPluginSettingsFile = PATH['project'] . '/config/plugins/' . $plugin['dirname'] . '/settings.yaml';

                // Create project plugin settings directory
                ! filesystem()->directory($projectPluginSettingsDir)->exists() and filesystem()->directory($projectPluginSettingsDir)->create(0755, true);

                // Check if default plugin settings file exists
                if (! filesystem()->file($defaultPluginSettingsFile)->exists()) {
                    throw new RuntimeException('Load ' . $plugin['dirname'] . ' plugin settings - failed!');
                }

                // Get default plugin settings content
                $defaultPluginSettingsFileContent   = filesystem()->file($defaultPluginSettingsFile)->get();
                $defaultPluginSettings              = empty($defaultPluginSettingsFileContent) ? [] : serializers()->yaml()->decode($defaultPluginSettingsFileContent);

                // Create project plugin settings file
                ! filesystem()->file($projectPluginSettingsFile)->exists() and filesystem()->file($projectPluginSettingsFile)->put($defaultPluginSettingsFileContent);

                // Get project plugin settings content
                $projectPluginSettingsFileContent = filesystem()->file($projectPluginSettingsFile)->get();

                if (trim($projectPluginSettingsFileContent) === '') {
                    $projectPluginSettings = [];
                } else {
                    $projectPluginSettings = serializers()->yaml()->decode($projectPluginSettingsFileContent);
                }

                // Check if default plugin manifest file exists
                if (! filesystem()->file($defaultPluginManifestFile)->exists()) {
                    throw new RuntimeException('Load ' . $plugin['dirname'] . ' plugin manifest - failed!');
                }

                // Get default plugin manifest content
                $defaultPluginManifestFileContent  = filesystem()->file($defaultPluginManifestFile)->get();
                $defaultPluginManifest             = empty($defaultPluginManifestFileContent) ? [] : serializers()->yaml()->decode($defaultPluginManifestFileContent);

                // Merge plugin settings and manifest data
                $plugins[$plugin['dirname']]['manifest'] = $defaultPluginManifest;
                $plugins[$plugin['dirname']]['settings'] = array_replace_recursive($defaultPluginSettings, $projectPluginSettings);

                // Check if is not set plugin priority
                if (! isset($plugins[$plugin['dirname']]['settings']['priority'])) {
                    // Set default plugin priority = 100
                    $plugins[$plugin['dirname']]['settings']['priority'] = 100;
                }

                // Set tmp _priority field for sorting
                $plugins[$plugin['dirname']]['_priority'] = $plugins[$plugin['dirname']]['settings']['priority'];
            }

            // Sort plugins list by priority.
            $plugins = collection($plugins)->sortBy('_priority', 'ASC')->toArray();

            // ... and delete tmp _priority field for sorting
            foreach ($plugins as $pluginName => $pluginData) {
                $plugins = collection($plugins)->delete($pluginName . '._priority')->toArray();
            }

            // Get Enabled Plugins
            $plugins = $this->getEnabledPlugins($plugins);

            // Get Valid Plugins Dependencies
            $plugins = $this->getValidPluginsDependencies($plugins);

            // Save plugins list
            registry()->set('plugins', $plugins);
            cache()->set($pluginsCacheID, $plugins);

            // Save plugins dictionary
            $dictionary = $this->getPluginsDictionary($pluginsList, $locale);

            cache()->set($locale, $dictionary[$locale]);
        }

        $this->includeEnabledPlugins();

        emitter()->emit('onPluginsInitialized');
    }

    /**
     * Get plugins dictionary
     *
     * @param  array $pluginsList Plugins list
     *
     * @access public
     */
    public function getPluginsDictionary(array $pluginsList, string $locale): array
    {
        foreach ($pluginsList as $plugin) {
            $languageFile = PATH['project'] . '/plugins/' . $plugin['dirname'] . '/lang/' . $locale . '.yaml';

            if (filesystem()->file($languageFile)->exists()) {
                if (($content = filesystem()->file($languageFile)->get()) === false) {
                    throw new RuntimeException('Load file: ' . $languageFile . ' - failed!');
                }

                if (trim($content) === '') {
                    $translates = [];
                } else {
                    $translates = serializers()->yaml()->decode($content);
                }
                
                I18n::add($translates, $locale);
            } else {
                I18n::add([], registry()->get('flextype.settings.locale'));
            }
        }

        return I18n::$dictionary;
    }

    /**
     * Get plugins Cache ID
     *
     * @param  array $pluginsList Plugins list
     *
     * @access public
     */
    public function getPluginsCacheID(array $pluginsList): string
    {
        // Plugin cache id
        $_pluginsCacheID = '';

        // Go through...
        if (is_array($pluginsList) && count($pluginsList) > 0) {
            foreach ($pluginsList as $plugin) {
                $defaultPluginSettingsFile = PATH['project'] . '/plugins/' . $plugin['dirname'] . '/settings.yaml';
                $defaultPluginManifestFile = PATH['project'] . '/plugins/' . $plugin['dirname'] . '/plugin.yaml';
                $projectPluginSettingsFile = PATH['project'] . '/config/plugins/' . $plugin['dirname'] . '/settings.yaml';

                $f1 = filesystem()->file($defaultPluginSettingsFile)->exists() ? filemtime($defaultPluginSettingsFile) : '';
                $f2 = filesystem()->file($defaultPluginManifestFile)->exists() ? filemtime($defaultPluginManifestFile) : '';
                $f3 = filesystem()->file($projectPluginSettingsFile)->exists() ? filemtime($projectPluginSettingsFile) : '';

                $_pluginsCacheID .= $f1 . $f2 . $f3;
            }
        }

        // Create Unique Cache ID for Plugins
        return md5('plugins' . PATH['project'] . '/plugins/' . $_pluginsCacheID);
    }

    /**
     * Get valid plugins dependencies
     *
     * @param  array $plugins Plugins list
     *
     * @access public
     */
    public function getValidPluginsDependencies(array $plugins): array
    {
        // Set verified plugins array
        $verifiedPlugins = [];

        // Set non verfied plugins
        $nonVerifiedPlugins = $plugins;

        // Go through plugins list and verify them.
        foreach ($plugins as $pluginName => &$pluginData) {
            // Set verified true by default
            $verified = true;

            // If there is any dependencies for this plugin
            if (isset($pluginData['manifest']['dependencies'])) {
                // Go through plugin dependencies
                foreach ($pluginData['manifest']['dependencies'] as $dependency => $constraints) {
                    // Verify flextype version
                    if ($dependency === 'flextype') {
                        if (! Semver::satisfies(registry()->get('flextype.manifest.version'), $constraints)) {
                            $verified = false;

                            // Remove plugin where it is require this dependency
                            foreach ($plugins as $_plugin_name => $_pluginData) {
                                if (! $_pluginData['manifest']['dependencies'][$pluginName]) {
                                    continue;
                                }

                                unset($plugins[$_plugin_name]);
                                unset($verifiedPlugins[$_plugin_name]);
                            }
                        }
                    } else {
                        // Verify plugin dependencies
                        if (! isset($plugins[$dependency])) {
                            $verified = false;

                            // Remove plugin where it is require this dependency
                            foreach ($plugins as $_plugin_name => $_pluginData) {
                                if (! $_pluginData['manifest']['dependencies'][$pluginName]) {
                                    continue;
                                }

                                unset($plugins[$_plugin_name]);
                                unset($verifiedPlugins[$_plugin_name]);
                            }
                        } else {
                            $version = $plugins[$dependency]['manifest']['version'];
                            if (! Semver::satisfies($version, $constraints)) {
                                $verified = false;

                                // Remove plugin where it is require this dependency
                                foreach ($plugins as $_plugin_name => $_pluginData) {
                                    if (! $_pluginData['manifest']['dependencies'][$pluginName]) {
                                        continue;
                                    }

                                    unset($plugins[$_plugin_name]);
                                    unset($verifiedPlugins[$_plugin_name]);
                                }
                            }
                        }
                    }
                }
            }

            if (! $verified) {
                continue;
            }

            $verifiedPlugins[$pluginName] = $pluginData;
        }

        // Show alert if dependencies are not installed properly
        $diff = array_diff_key($nonVerifiedPlugins, $verifiedPlugins);
        if (count($diff) > 0) {
            echo '<b>Dependencies need to be installed properly for this plugins:</b>';
            echo '<ul>';
            foreach ($diff as $pluginName => $pluginData) {
                echo '<li>' . $pluginName . '</li>';
            }

            echo '</ul>';
            die;
        }

        // Return verified plugins list
        return $verifiedPlugins;
    }

    /**
     * Get plugins list
     *
     * @return array
     *
     * @access public
     */
    public function getPluginsList(): array
    {
        // Get Plugins List
        $pluginsList = [];

        if (filesystem()->directory(PATH['project'] . '/plugins/')->exists()) {
            foreach (filesystem()->find()->in(PATH['project'] . '/plugins/')->directories()->depth(0) as $plugin) {
                $pluginName = $plugin->getBasename();
                if (filesystem()->file(PATH['project'] . '/plugins/' . $pluginName . '/plugin.php')->exists() &&
                    filesystem()->file(PATH['project'] . '/plugins/' . $pluginName . '/plugin.yaml')->exists() && 
                    filesystem()->file(PATH['project'] . '/plugins/' . $pluginName . '/settings.yaml')->exists()) {
                    $pluginsList[$pluginName]['dirname']  = $plugin->getBasename();
                    $pluginsList[$pluginName]['pathname'] = $plugin->getPathname();
                }
            }
        }

        return $pluginsList;
    }

    /**
     * Get enabled plugins
     *
     * @access private
     */
    private function getEnabledPlugins($plugins): array
    {
        $enabledPlugins = [];

        foreach ($plugins as $name => $plugin) {
            if (! collection($plugin)->has('settings.enabled') || collection($plugin)->get('settings.enabled') == false) {
                continue;
            }

            $enabledPlugins[$name] = $plugin;
        }

        return $enabledPlugins;
    } 

    /**
     * Include enabled plugins
     *
     * @access private
     */
    private function includeEnabledPlugins(): void
    {
        if (! is_array(registry()->get('plugins')) || count(registry()->get('plugins')) <= 0) {
            return;
        }

        foreach (registry()->get('plugins') as $pluginName => $plugin) {
            if (! registry()->get('plugins.' . $pluginName . '.settings.enabled')) {
                continue;
            }

            require_once PATH['project'] . '/plugins/' . $pluginName . '/plugin.php';
        }
    }
}
