<?php

declare(strict_types=1);

namespace Arcanedev\Localization;

use Arcanedev\Localization\Contracts\LocalesManager as LocalesManagerContract;
use Arcanedev\Localization\Contracts\Localization as LocalizationContract;
use Arcanedev\Localization\Contracts\RouteTranslator as RouteTranslatorContract;
use Arcanedev\Localization\Exceptions\UnsupportedLocaleException;
use Arcanedev\Localization\Utilities\Url;
use Illuminate\Contracts\Foundation\Application as ApplicationContract;
use Illuminate\Contracts\View\Factory as ViewFactoryContract;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
/**
 * Class     Localization
 *
 * @author   ARCANEDEV <arcanedev.maroc@gmail.com>
 */
class Localization implements LocalizationContract
{
    /* -----------------------------------------------------------------
     |  Properties
     | -----------------------------------------------------------------
     */

    /**
     * Base url.
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * Laravel application instance.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    private $app;

    /**
     * The RouteTranslator instance.
     *
     * @var \Arcanedev\Localization\Contracts\RouteTranslator
     */
    protected $routeTranslator;

    /**
     * The LocalesManager instance.
     *
     * @var \Arcanedev\Localization\Contracts\LocalesManager
     */
    private $localesManager;


    private static $localizedURLCache = [];

    private $cacheKeyFormat = null;

    private $useExternalCache = null;

    private $externalCacheTtl = null;

    private $exceptNotReplacedParam = [];

    /* -----------------------------------------------------------------
     |  Constructor
     | -----------------------------------------------------------------
     */

    /**
     * Localization constructor.
     *
     * @param  \Illuminate\Contracts\Foundation\Application       $app
     * @param  \Arcanedev\Localization\Contracts\RouteTranslator  $routeTranslator
     * @param  \Arcanedev\Localization\Contracts\LocalesManager   $localesManager
     */
    public function __construct(
        ApplicationContract     $app,
        RouteTranslatorContract $routeTranslator,
        LocalesManagerContract  $localesManager
    ) {
        $this->app             = $app;
        $this->routeTranslator = $routeTranslator;
        $this->localesManager  = $localesManager;

        $this->localesManager->setDefaultLocale(
            $this->app['config']->get('app.locale')
        );

        $this->cacheKeyFormat = $this->localesManager->getCacheFormat();

        $this->useExternalCache = $this->localesManager->getUseExternalCache();

        $this->externalCacheTtl = $this->localesManager->getExternalCacheTtl();

        $this->exceptNotReplacedParam = $this->localesManager->getExceptNotReplacedParam();

        $routeTranslator->setExceptNotReplacedParam($this->exceptNotReplacedParam);
    }

    /* -----------------------------------------------------------------
     |  Getters & Setters
     | -----------------------------------------------------------------
     */

    /**
     * Get Request instance.
     *
     * @return \Illuminate\Http\Request
     */
    private function request()
    {
        return $this->app['request'];
    }

    /**
     * Returns default locale.
     *
     * @return string
     */
    public function getDefaultLocale()
    {
        return $this->localesManager->getDefaultLocale();
    }

    /**
     * Return an array of all supported Locales.
     *
     * @return \Arcanedev\Localization\Entities\LocaleCollection
     */
    public function getSupportedLocales()
    {
        return $this->localesManager->getSupportedLocales();
    }

    /**
     * Set the supported locales.
     *
     * @param  array  $supportedLocales
     *
     * @return self
     */
    public function setSupportedLocales(array $supportedLocales)
    {
        $this->localesManager->setSupportedLocales($supportedLocales);

        return $this;
    }

    /**
     * Get supported locales keys.
     *
     * @return array
     */
    public function getSupportedLocalesKeys()
    {
        return $this->localesManager->getSupportedLocalesKeys();
    }

    /**
     * Returns current language.
     *
     * @return string
     */
    public function getCurrentLocale()
    {
        return $this->localesManager->getCurrentLocale();
    }

    /**
     * Returns current language.
     *
     * @return \Arcanedev\Localization\Entities\Locale
     */
    public function getCurrentLocaleEntity()
    {
        return $this->localesManager->getCurrentLocaleEntity();
    }

    /**
     * Returns current locale name.
     *
     * @return string
     */
    public function getCurrentLocaleName()
    {
        return $this->getCurrentLocaleEntity()->name();
    }

    /**
     * Returns current locale script.
     *
     * @return string
     */
    public function getCurrentLocaleScript()
    {
        return $this->getCurrentLocaleEntity()->script();
    }

    /**
     * Returns current locale direction.
     *
     * @return string
     */
    public function getCurrentLocaleDirection()
    {
        return $this->getCurrentLocaleEntity()->direction();
    }

    /**
     * Returns current locale native name.
     *
     * @return string
     */
    public function getCurrentLocaleNative()
    {
        return $this->getCurrentLocaleEntity()->native();
    }

    /**
     * Returns current locale regional.
     *
     * @return string
     */
    public function getCurrentLocaleRegional()
    {
        return $this->getCurrentLocaleEntity()->regional();
    }

    /**
     * Get all locales.
     *
     * @return \Arcanedev\Localization\Entities\LocaleCollection
     */
    public function getAllLocales()
    {
        return $this->localesManager->getAllLocales();
    }

    /**
     * Set and return current locale.
     *
     * @param  string|null  $locale
     *
     * @return string
     */
    public function setLocale($locale = null)
    {
        return $this->localesManager->setLocale($locale);
    }

    /**
     * Sets the base url for the site.
     *
     * @param  string  $url
     *
     * @return $this
     */
    public function setBaseUrl($url)
    {
        if (substr($url, -1) !== '/') $url .= '/';

        $this->baseUrl = $url;

        return $this;
    }

    /* -----------------------------------------------------------------
     |  Main Methods
     | -----------------------------------------------------------------
     */

    /**
     * Translate routes and save them to the translated routes array (used in the localize route filter).
     *
     * @param  string  $routeName
     *
     * @return string
     */
    public function transRoute($routeName)
    {
        return $this->routeTranslator->trans($routeName);
    }

    /**
     * Returns an URL adapted to $locale or current locale.
     *
     * @param  string|null  $url
     * @param  string|null  $locale
     *
     * @return string
     */
    public function localizeURL($url = null, $locale = null)
    {
        return $this->getLocalizedURL($locale, $url);
    }

    /**
     * It returns an URL without locale (if it has it).
     *
     * @param  string|null  $url
     *
     * @return string
     */
    public function getNonLocalizedURL($url = null)
    {
        return $this->getLocalizedURL(false, $url);
    }

    /**
     * Returns an URL adapted to $locale or current locale.
     *
     * @param  string|null  $locale //локаль к
     * @param  string|null  $url
     * @param  array        $attributes
     * @param  bool|false   $showHiddenLocale
     * @param  bool|false   $attributesSluged
     *
     * @return string|false
     */
    public function getLocalizedURL($locale = null, $url = null, array $originalAttributes = [], $showHiddenLocale = false, $fromLocale = null, $attributesSluged = false, $alloCache = true)
    {

        $args = func_get_args();
        if (empty($url)) {
            $args[] = $this->request()->fullUrl();
        }

        $hash = md5(serialize($args));

        $cacheKey = $this->cacheKeyFormat['prefix']
            .str_replace(['{hash}', ], [$hash], $this->cacheKeyFormat['params']);


        if(array_key_exists($cacheKey, self::$localizedURLCache) && $alloCache){
            return self::$localizedURLCache[$cacheKey];
        }

        if(Cache::has($cacheKey) &&  $alloCache && $this->useExternalCache){
            $fromCache = \Cache::get($cacheKey);
            self::$localizedURLCache[$cacheKey] = $fromCache;
            return $fromCache;
        }

        if (is_null($locale))
            $locale = $this->getCurrentLocale();

        if (empty($fromLocale))
            $fromLocale = $this->getCurrentLocale();

        $this->isLocaleSupportedOrFail($locale);

        if (empty($originalAttributes))
            $attributes = Url::extractAttributes($url);
        else{
            $attributes = $originalAttributes;
        }

        $cacheForever = $attributes || $originalAttributes ? false : true;

        // например, переводим текущий маршут
        if (empty($url)) {
            if ($this->routeTranslator->hasCurrentRoute()) {
                if (empty($attributes))
                    $attributes = $this->request()->route()->parameters();

                $resUrlFromRouteName = $this->getUrlFromRouteName(
                    $locale,
                    $this->routeTranslator->getCurrentRoute(),
                    $attributes,
                    $showHiddenLocale,
                    $attributesSluged
                );
                if($alloCache){
                    self::$localizedURLCache[$cacheKey] = $resUrlFromRouteName;
                    $cacheForever && $this->useExternalCache ? Cache::forever($cacheKey, $resUrlFromRouteName) : Cache::put($cacheKey, $resUrlFromRouteName, $this->externalCacheTtl);
                }

                return $resUrlFromRouteName;
            }

            $url = $this->request()->fullUrl();
        }

        // получаем данные маршута, routeName и findedItems, для текущего языка с последующим создания url с помощью getUrlFromRouteName, используеться для динамических url
        $dynamicDataFromUrl = $this->routeTranslator->getDynamicDataFromUrl($url, $originalAttributes, $fromLocale);
        if(isset($dynamicDataFromUrl['routeName'])){
            $res =  $this->getUrlFromRouteName(
                $locale,
                $dynamicDataFromUrl['routeName'],
                $dynamicDataFromUrl['findedItems'],
                $showHiddenLocale,
                $attributesSluged
            );
            if($alloCache){
                self::$localizedURLCache[$cacheKey] = $res;
                $cacheForever && $this->useExternalCache ? Cache::forever($cacheKey, $res) : Cache::put($cacheKey, $res, $this->externalCacheTtl);
            }

            return $res;
        }

        // используется для созданых роутов на основе переводчика
        if (
            $locale &&
            ($translatedRoute = $this->findTranslatedRouteByUrl($url, $attributes, $this->getCurrentLocale()))
        ) {

            $res = $this->getUrlFromRouteName($locale, $translatedRoute, $attributes, $showHiddenLocale, $attributesSluged);

            if($alloCache){
                self::$localizedURLCache[$cacheKey] = $res;
                $cacheForever && $this->useExternalCache ? Cache::forever($cacheKey, $res) : Cache::put($cacheKey, $res, $this->externalCacheTtl);
            }

            return $res;
        }



        $baseUrl    = $this->request()->getBaseUrl();
        $parsedUrl  = parse_url($url);

        $translatedRoute = $this->routeTranslator->getTranslatedRoute(
            $baseUrl, $parsedUrl, $this->getDefaultLocale(), $this->getSupportedLocales()
        );

        if ($translatedRoute !== false){
            $res = $this->getUrlFromRouteName($locale, $translatedRoute, $attributes, $showHiddenLocale, $attributesSluged);

            if($alloCache){
                self::$localizedURLCache[$cacheKey] = $res;
                $cacheForever && $this->useExternalCache ? Cache::forever($cacheKey, $res) : Cache::put($cacheKey, $res, $this->externalCacheTtl);
            }

            return $res;
        }


        if ( ! empty($locale)) {
            if ($locale !== $this->getDefaultLocale() || ! $this->isDefaultLocaleHiddenInUrl() || $showHiddenLocale) {
                $parsedUrl['path'] = $locale.'/'.ltrim($parsedUrl['path'], '/');
            }
        }

        $parsedUrl['path'] = ltrim(ltrim($baseUrl, '/') . '/' . $parsedUrl['path'], '/');
        $parsedUrl['path'] = rtrim($parsedUrl['path'], '/');

        $url = Url::unparse($parsedUrl);

        if (filter_var($url, FILTER_VALIDATE_URL)){
            self::$localizedURLCache[$cacheKey] = $url;
            $cacheForever && $this->useExternalCache ? Cache::forever($cacheKey, $url) : Cache::put($cacheKey, $url, $this->externalCacheTtl);

            return $url;
        }


        $res  = $this->createUrlFromUri(
            empty($url) ? $parsedUrl['path'] : $url
        );

        if($alloCache){
            self::$localizedURLCache[$cacheKey] = $res;
            $cacheForever && $this->useExternalCache ? Cache::forever($cacheKey, $res) : Cache::put($cacheKey, $res, $this->externalCacheTtl);
        }

        return $res;
    }

    /**
     * Create an url from the uri.
     *
     * @param  string  $uri
     *
     * @return string
     */
    public function createUrlFromUri($uri)
    {
        $uri = ltrim($uri, '/');

        return empty($this->baseUrl)
            ? $this->app[\Illuminate\Contracts\Routing\UrlGenerator::class]->to($uri)
            : $this->baseUrl.$uri;
    }

    /**
     * Get locales navigation bar.
     *
     * @return string
     */
    public function localesNavbar()
    {
        /** @var  \Illuminate\Contracts\View\Factory  $view */
        $view = $this->app[ViewFactoryContract::class];

        return $view->make('localization::navbar', ['supportedLocales' => $this->getSupportedLocales()])
            ->render();
    }

    /* -----------------------------------------------------------------
     |  Translation Methods
     | -----------------------------------------------------------------
     */

    /**
     * Returns the translated route for an url and the attributes given and a locale
     *
     * @param  string  $url
     * @param  array   $attributes
     * @param  string  $locale
     * @return string|false
     */
    private function findTranslatedRouteByUrl($url, $attributes, $locale)
    {
        if (empty($url))
            return false;

        // check if this url is a translated url
        foreach ($this->routeTranslator->getTranslatedRoutes() as $translatedRoute) {
            $translatedUrl = $this->getUrlFromRouteName($locale, $translatedRoute, $attributes, false, false, false);

            if ($this->getNonLocalizedURL($translatedUrl) === $this->getNonLocalizedURL($url))
                return $translatedRoute;
        }

        return false;
    }

    /**
     * Returns an URL adapted to the route name and the locale given.
     *
     * @param  string|bool  $locale
     * @param  string       $transKey
     * @param  array        $attributes
     * @param  bool|false   $showHiddenLocale
     * @param  bool|false   $attributesSluged
     *
     * @return string|false
     */
    public function getUrlFromRouteName($locale, $transKey, array $attributes = [], $showHiddenLocale = false, $attributesSluged = false )
    {

        $this->isLocaleSupportedOrFail($locale);

        $route = $this->routeTranslator->getUrlFromRouteName(
            $locale,
            $this->getDefaultLocale(),
            $transKey,
            $attributes,
            $this->isDefaultLocaleHiddenInUrl(),
            $showHiddenLocale,
            $attributesSluged
        );



        // This locale does not have any key for this route name
        if (empty($route)) return false;

        return rtrim($this->createUrlFromUri($route));
    }

    /**
     * Set route name from request.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function setRouteNameFromRequest(Request $request)
    {
        $routeName = $this->routeTranslator->getRouteNameFromPath(
            $request->getUri(), $this->getCurrentLocale()
        );

        $this->routeTranslator->setCurrentRoute($routeName);
    }

    /* -----------------------------------------------------------------
     |  Check Methods
     | -----------------------------------------------------------------
     */

    /**
     * Hide the default locale in URL ??
     *
     * @return bool
     */
    public function isDefaultLocaleHiddenInUrl()
    {
        return $this->localesManager->isDefaultLocaleHiddenInUrl();
    }

    /**
     * Check if Locale exists on the supported locales collection.
     *
     * @param  string|bool  $locale
     *
     * @return bool
     */
    public function isLocaleSupported($locale)
    {
        return ! ($locale !== false && ! $this->localesManager->isSupportedLocale($locale));
    }

    /**
     * Check if the locale is supported or fail if not.
     *
     * @param  string  $locale
     *
     * @throws \Arcanedev\Localization\Exceptions\UnsupportedLocaleException
     */
    private function isLocaleSupportedOrFail($locale): void
    {
        if ( ! $this->isLocaleSupported($locale))
            throw new UnsupportedLocaleException(
                "Locale '{$locale}' is not in the list of supported locales."
            );
    }
}
