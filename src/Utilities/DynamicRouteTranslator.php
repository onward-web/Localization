<?php


namespace Arcanedev\Localization\Utilities;


use Arcanedev\Localization\Contracts\DynamicRouteTranslator as DynamicRouteTranslatorContract;
use Arcanedev\Localization\Entities\LocaleCollection;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Arr;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Arcanedev\Localization\Localization;


class DynamicRouteTranslator implements DynamicRouteTranslatorContract
{

    public function getUrlFromRouteName($url, $locale, $routeName, $attributes = [], $attributesSluged = false){
        // список динамических маршутов
        $preparedDataDynamicRoutes = Localization::getPreparedDataDynamicRoutes();

        $route = null;
        // получаем $route
        if(array_key_exists($routeName, $preparedDataDynamicRoutes) &&
          is_array($preparedDataDynamicRoutes[$routeName]) &&
          array_key_exists('route', $preparedDataDynamicRoutes[$routeName])
        ){
            $route = $preparedDataDynamicRoutes[$routeName]['route'];
        }

        if(!$route){
            throw new \Exception($routeName .'not found in $preparedDataDynamicRoutes');
        }

        if(!$attributesSluged){
            foreach($attributes as $attributeName => $attributeValue){
                $attributeModel = Localization::getModelRelationByParam((string)$attributeName);
                if(!$attributeModel){
                    continue;
                }
                $attributes[$attributeName] = (new $attributeModel)->findLocalisationSlugByItem($attributeValue, $attributeName, $locale);
            }
        }


        $url = Url::substituteAttributes($attributes, $url.'/'.$route->uri());


        return $url;
    }


    public function getDynamicDataFromUrl($url, $fromLocale, LocaleCollection $supportedLocales){

        if(empty($url)){
            return null;
        }
        $fullFindComplete = false;
        $dynamicDataFromUrl = [];


        $preparedDataDynamicRoutes = Localization::getPreparedDataDynamicRoutes();

        $parsedUrl  = parse_url($url);
        $this->clearPath($parsedUrl, $supportedLocales);


        foreach($preparedDataDynamicRoutes as $preparedDataDynamicRoute){
            $dynamicDataFromUrl['findedItems'] = [];
            $request = \Illuminate\Http\Request::create( $parsedUrl['path']);

            if( !$preparedDataDynamicRoute['route']->matches($request)){
                continue;
            }
            $attributes = [];

            // получаем атрибуты, по строке slug
            Url::hasAttributesFromUriPath(explode('/', $parsedUrl['path']), $preparedDataDynamicRoute['route']->uri(), $attributes);


            foreach($attributes as $attributeName => $attributeValue){
                $attributeModel = Localization::getModelRelationByParam((string)$attributeName);
                if(!$attributeModel){
                    continue;
                }

                $resFind = (new $attributeModel)->findItemBySlug($attributeValue, $fromLocale, $dynamicDataFromUrl['findedItems']);

                if($resFind) {
                    $dynamicDataFromUrl['findedItems'] = array_merge($dynamicDataFromUrl['findedItems'], $resFind);
                }

            }

            if(array_diff_key($attributes, $dynamicDataFromUrl['findedItems']) === array_diff_key($dynamicDataFromUrl['findedItems'], $attributes)){
                $dynamicDataFromUrl['routeName'] = $preparedDataDynamicRoute['route']->getName();
                $fullFindComplete = true;
                break;
            }

        }



        if($fullFindComplete){
            return $dynamicDataFromUrl;
        }

        return null;
    }

    public function clearPath(&$parsedUrl, LocaleCollection $supportedLocales) {


        if (empty($parsedUrl) || ! isset($parsedUrl['path'])) {
            $parsedUrl['path'] = '';
        }
        else {

            foreach ($supportedLocales->keys() as $locale) {
                foreach (["%^/?$locale/%", "%^/?$locale$%"] as $pattern) {
                    $parsedUrl['path'] = preg_replace($pattern, '$1', $parsedUrl['path']);
                }
            }
        }

        $parsedUrl['path'] = ltrim($parsedUrl['path'], '/');
        $parsedUrl['path'] = rtrim($parsedUrl['path'], '/');
    }

}
