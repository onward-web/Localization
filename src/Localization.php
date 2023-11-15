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
use Arcanedev\Localization\Contracts\RouteCacheHelper;

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


    /**
     * The LocalesManager instance.
     *
     * @var \Arcanedev\Localization\Contracts\RouteCacheHelper
     */
    private $routeCacheHelper = null;

    /**
     * @var null|array
     */
    protected static $preparedDataDynamicRoutes = null;

    /**
     * @var null|array
     */
    protected static $modelParamsRelation = null;

    public static $exceptNotReplacedParam = [];



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
     * @param  \Arcanedev\Localization\Contracts\RouteCacheHelper  $routeCacheHelper
     */
    public function __construct(
        ApplicationContract     $app,
        RouteTranslatorContract $routeTranslator,
        LocalesManagerContract  $localesManager,
        RouteCacheHelper  $routeCacheHelper
    ) {
        $this->app             = $app;
        $this->routeTranslator = $routeTranslator;
        $this->localesManager  = $localesManager;

        $this->localesManager->setDefaultLocale(
            $this->app['config']->get('app.locale')
        );

        $routeCacheHelper->cacheKeyFormat = $this->localesManager->getCacheFormat();
        $routeCacheHelper->useExternalCache = $this->localesManager->getUseExternalCache();
        $routeCacheHelper->externalCacheTtl = $this->localesManager->getExternalCacheTtl();
        $routeCacheHelper->allowTypeToExternalCache = $this->localesManager->getAllowTypesToExternalCache();

        $this->routeCacheHelper = $routeCacheHelper;

        self::$exceptNotReplacedParam = $this->localesManager->getExceptNotReplacedParam();
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



        if (is_null($locale)) {
            $locale = $this->getCurrentLocale();
        }

        if (empty($fromLocale)) {
            $fromLocale = $this->getCurrentLocale();
        }

        $this->isLocaleSupportedOrFail($locale);

        // не кэшируем при наличии query string
        $cacheKey = null;
        $queryInUrl = false;
        if($alloCache){

            $cacheArgs['locale'] = $locale;
            $cacheArgs['url'] = $url;
            $cacheArgs['attributes'] = $originalAttributes;
            $cacheArgs['showHiddenLocale'] = $showHiddenLocale;
            $cacheArgs['fromLocale'] = $fromLocale;
            $cacheArgs['attributesSluged'] = $attributesSluged;
            if (empty($url)) {
                $cacheArgs['url'] = $this->request()->fullUrl();
            }

            $cacheKey = (string)$this->routeCacheHelper->createCacheKey($cacheArgs);

            $fromCache = $this->routeCacheHelper->getFromCache($cacheKey);
            if($fromCache){
                return $fromCache;
            }
            $queryInUrl = (bool)$this->routeCacheHelper->isExistQuery($cacheArgs['url']);
        }


        if (empty($originalAttributes))
            $attributes = Url::extractAttributes($url);
        else{
            $attributes = $originalAttributes;
        }



        // пуcтой url, передим текущий маршут, здесь переводиться только переводымий маршут на основе переводчикам
        if (empty($url)) {
            if ($this->routeTranslator->hasCurrentRoute()) {
                if (empty($attributes))
                    $attributes = $this->request()->route()->parameters();

                $resCurrentSimpleTranslate = $this->getUrlFromRouteName(
                    $locale,
                    $this->routeTranslator->getCurrentRoute(),
                    $attributes,
                    $showHiddenLocale,
                    $attributesSluged
                );


                if($alloCache){
                    $this->routeCacheHelper->localCache[$cacheKey] = $resCurrentSimpleTranslate;
                    $this->routeCacheHelper->isSaveToExternal($queryInUrl, 'translate_current_simple_translatable') ? $this->routeCacheHelper->saveToExternalCache($cacheKey, $resCurrentSimpleTranslate) : null;

                }

                return $resCurrentSimpleTranslate;
            }

            $url = $this->request()->fullUrl();
        }

        // Ситуация с маршутами
        // имя роута указано
        // имя роута указано, перевод текущего url

        // Стуация со slug
        //  $attributesSluged = false
        //  $attributesSluged = true

        // Ситуация по $locale $fromLocale
        // $locale === $fromLocale
        // $locale !== $fromLocale


        // Ситуация когда необходимо задействовать getDynamicDataFromUrl
        //$locale !== $fromLocale || !self::isDynamicRoute($url)

        $dynamicRouteName = $dynamicAttributes = null;
        // при переводе текущего url, self::isDynamicRoute даст false, также необходимо преобразовать ;
        if($locale !== $fromLocale ||  !self::isDynamicRoute($url) ){
            $dynamicDataFromUrl = $this->routeTranslator->getDynamicDataFromUrl($url, $fromLocale, $this->getSupportedLocales());
            if(isset($dynamicDataFromUrl['routeName'])){
                $dynamicRouteName = $dynamicDataFromUrl['routeName'];
                $dynamicAttributes = $dynamicDataFromUrl['findedItems'];
                $attributesSluged = false;
            }
        }else if(self::isDynamicRoute($url)){
            $dynamicRouteName = $url;
            $dynamicAttributes = $attributes;
        }


        // перевод динамических маршутов
        if($dynamicRouteName){
            $resCreateUrlFromDynamic =  $this->getUrlFromRouteName(
                $locale,
                $dynamicRouteName,
                $dynamicAttributes,
                $showHiddenLocale,
                $attributesSluged
            );
            if($alloCache){
                $this->routeCacheHelper->localCache[$cacheKey] = $resCreateUrlFromDynamic;
                $typeName = $this->routeCacheHelper->transformTypeName($dynamicRouteName);
                $this->routeCacheHelper->isSaveToExternal($queryInUrl, 'translate_dynamic_'.$typeName) ? $this->routeCacheHelper->saveToExternalCache($cacheKey, $resCreateUrlFromDynamic) : null;

            }

            return $resCreateUrlFromDynamic;
        }



        // переводим простой переведимые маршут, по $translatedRoute,  localization()->getLocalizedURL('ru', 'routes.garage.index')
        if (
            $locale &&
            ($translatedRoute = $this->findTranslatedRouteByUrl($url, $attributes, $this->getCurrentLocale()))
        ) {

            $res = $this->getUrlFromRouteName($locale, $translatedRoute, $attributes, $showHiddenLocale, $attributesSluged);

            if($alloCache){
                $this->routeCacheHelper->localCache[$cacheKey] = $res;
                $this->routeCacheHelper->isSaveToExternal($queryInUrl, 'translate_simple_by_route_name') ? $this->routeCacheHelper->saveToExternalCache($cacheKey, $res) : null;
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
                $this->routeCacheHelper->localCache[$cacheKey] = $res;
                $this->routeCacheHelper->isSaveToExternal($queryInUrl, 'translate_base_url') ? $this->routeCacheHelper->saveToExternalCache($cacheKey, $res) : null;
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
            $this->routeCacheHelper->localCache[$cacheKey] = $url;
            $this->routeCacheHelper->isSaveToExternal($queryInUrl, 'translate_parsed_url') ? $this->routeCacheHelper->saveToExternalCache($cacheKey, $url) : null;

            return $url;
        }


        $res  = $this->createUrlFromUri(
            empty($url) ? $parsedUrl['path'] : $url
        );

        if($alloCache){
            $this->routeCacheHelper->localCache[$cacheKey] = $res;
            $this->routeCacheHelper->isSaveToExternal($queryInUrl, 'translate_create_url_from_uri') ? $this->routeCacheHelper->saveToExternalCache($cacheKey, $url) : null;

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

    public static function getExceptNotReplacedParam(): array
    {
        if(!self::$exceptNotReplacedParam){
            self::$exceptNotReplacedParam = config('localization.except_not_replaced_param');
        }

        return self::$exceptNotReplacedParam;
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


    public static function getPreparedDataDynamicRoutes()
    {
        if(self::$preparedDataDynamicRoutes){
            return self::$preparedDataDynamicRoutes;
        }

        $allRoutes = app('router')->getRoutes();

        foreach ($allRoutes as $route) {

            if(!$route->dynamic){
                continue;
            }
            // путь к маршуту
            $routeUri = $route->uri();
            // делим на слеши, для дальнейшего поиска необезательных атрибутов
            $routeUriPaths = explode('/', $routeUri);

            // необезательные параметры
            $optionalAttribute = [];

            foreach($routeUriPaths as $routeUriPath){
                if(str_starts_with($routeUriPath, '{?') || str_ends_with($routeUriPath, '?}')){
                    $attributeName    = preg_replace(['/{/', '/\?/', '/}/'], '', $routeUriPath);

                    $optionalAttribute[] = (string)$attributeName;
                }
            }

            // формируем масссив обезательных параметров
            $parameterNames = $route->parameterNames();

            $requiredParameters = [];
            foreach($parameterNames as $parameterName){
                if(!in_array($parameterName, $optionalAttribute, true)){
                    $requiredParameters[] = (string)$parameterName;
                }
            }


            self::$preparedDataDynamicRoutes[$route->getName()] = [
                'requiredParameters' => $requiredParameters,
                'optionalParameters' => $optionalAttribute,
                'parameters' => $route->parameterNames(),
                'route' => $route
            ];
        }

        return self::$preparedDataDynamicRoutes;
    }

    public static function getModelParamsRelation()
    {
        if(self::$modelParamsRelation){
            return self::$modelParamsRelation;
        }
        self::$modelParamsRelation = config('localization.model_params_relation');

        return self::$modelParamsRelation;
    }

    public static function getModelRelationByParam(string $param){
        $modelParamsRelation = self::getModelParamsRelation();

        foreach($modelParamsRelation as $model => $params){
            if(in_array($param, $params, true)){
                return $model;
            }
        }
    }

    public static function isDynamicRoute(string $routeName){
        $preparedDataDynamicRoutes = self::getPreparedDataDynamicRoutes();
        if(array_key_exists($routeName, $preparedDataDynamicRoutes)){
            return true;
        }
        return false;
    }
}
