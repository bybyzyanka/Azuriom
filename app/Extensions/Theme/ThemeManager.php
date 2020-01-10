<?php

namespace Azuriom\Extensions\Theme;

use Azuriom\Extensions\ExtensionManager;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

class ThemeManager extends ExtensionManager
{
    /**
     * The current theme if set.
     *
     * @var string|null
     */
    private $currentTheme;

    /**
     * The themes/ directory.
     *
     * @var string
     */
    private $themesPath;

    /**
     * The themes/ public directory for assets.
     *
     * @var string
     */
    private $themesPublicPath;

    /**
     * Create a new ThemeManager instance.
     *
     * @param  \Illuminate\Filesystem\Filesystem  $files
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct($files);

        $this->themesPath = resource_path('themes');
        $this->themesPublicPath = public_path('assets/themes');
    }

    /**
     * Load and enable the given theme.
     * Currently this method can only be call once.
     *
     * @param  string  $theme
     */
    public function loadTheme(string $theme)
    {
        if ($this->currentTheme !== null) {
            throw new RuntimeException('A theme has been already loaded');
        }

        $this->currentTheme = $theme;

        $viewsPath = $this->path('views');

        // Add theme path to view finder
        view()->getFinder()->prependLocation($viewsPath);

        config([
            'view.paths' => array_merge([$viewsPath], config('view.paths', []))
        ]);

        $this->loadConfig($theme);

        $this->createAssetsLink($theme);
    }

    /**
     * Get the path of the specified theme.
     * If no theme is specified the current theme is used.
     * When no theme is specified and there is no theme enabled, this
     * will return null.
     *
     * @param  string  $path
     * @param  string|null  $theme
     * @return string|null
     */
    public function path(string $path = '', string $theme = null)
    {
        if ($theme === null) {
            if (! $this->hasTheme()) {
                return null;
            }

            $theme = $this->currentTheme;
        }

        return $this->themesPath("/{$theme}/{$path}");
    }

    /**
     * Get the public path of the specified theme.
     *
     * @param  string  $path
     * @param  string|null  $theme
     * @return string|null
     */
    public function publicPath(string $path = '', string $theme = null)
    {
        if ($theme === null) {
            if (! $this->hasTheme()) {
                return null;
            }

            $theme = $this->currentTheme;
        }

        return $this->themesPublicPath("/{$theme}/{$path}");
    }

    /**
     * Get the themes path which contains the installed themes.
     *
     * @param  string  $path
     * @return string
     */
    public function themesPath(string $path = '')
    {
        return $this->themesPath.$path;
    }

    /**
     * Get the themes public path which contains the assets of the installed themes.
     *
     * @param  string  $path
     * @return string
     */
    public function themesPublicPath(string $path = '')
    {
        return $this->themesPublicPath.$path;
    }

    /**
     * Get an array containing the descriptions of the installed themes.
     *
     * @return array
     */
    public function findThemesDescriptions()
    {
        $directories = $this->files->directories($this->themesPath);

        $themes = [];

        foreach ($directories as $dir) {
            $themes[$this->files->basename($dir)] = $this->getJson($dir.'/theme.json');
        }

        return $themes;
    }

    /**
     * Get the description of the given theme.
     *
     * @param  string|null  $theme
     * @return mixed|null
     */
    public function findDescription(string $theme = null)
    {
        $path = $this->path('/theme.json', $theme);

        if ($path === null) {
            return null;
        }

        return $this->getJson($path);
    }

    /**
     * Get an array containing the installed themes names.
     *
     * @return string[]
     */
    public function findThemes()
    {
        $directories = $this->files->directories($this->themesPath);

        return array_map(function ($dir) {
            return $this->files->basename($dir);
        }, $directories);
    }

    /**
     * Delete the given theme.
     *
     * @param  string  $theme
     */
    public function delete(string $theme)
    {
        if ($this->findDescription($theme) === null) {
            return;
        }

        $this->files->delete($this->themesPublicPath($theme));

        $this->files->delete($this->path($theme));
    }

    /**
     * Get the current theme, or null if none is active.
     *
     * @return string|null
     */
    public function currentTheme()
    {
        return $this->currentTheme;
    }

    /**
     * Get if there is any active theme enabled.
     *
     * @return bool
     */
    public function hasTheme()
    {
        return $this->currentTheme !== null;
    }

    protected function loadConfig(string $theme)
    {
        $themeConfig = app('cache')->remember('theme.config', now()->addDay(),
            function () use ($theme) {
                return $this->getConfig($theme);
            });

        if ($themeConfig !== null) {
            foreach ($themeConfig as $key => $value) {
                config(['theme.'.$key => $value]);
            }
        }
    }

    protected function getConfig(string $theme)
    {
        return $this->getJson(themes_path($theme.'/config.json'), true);
    }

    protected function createAssetsLink(string $theme)
    {
        if ($this->files->exists($this->publicPath('', $theme))) {
            return;
        }

        $themeAssetsPath = $this->path('assets', $theme);

        if ($this->files->exists($themeAssetsPath)) {
            $this->files->link($themeAssetsPath, $this->publicPath('', $theme));
        }
    }
}
