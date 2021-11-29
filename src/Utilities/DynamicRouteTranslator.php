<?php


namespace Arcanedev\Localization\Utilities;


use Arcanedev\Localization\Contracts\DynamicRouteTranslator as DynamicRouteTranslatorContract;
use Arcanedev\Localization\Entities\LocaleCollection;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Arr;
use Illuminate\Routing\Exceptions\UrlGenerationException;

class DynamicRouteTranslator implements DynamicRouteTranslatorContract
{

    public $candidatesRouteNames = [];

    public $modelParamRelation = [];

    public $dynamicRoutes = [];

    public $routes;

    public function __construct()
    {
        
        $this->candidatesRouteNames = config('dynamic-url.candidates_route_names', []);

        $this->modelParamRelation = config('dynamic-url.model_param_relation', []);
    }

    // подготовляем данные
    protected function prepareDinamicRoute(){

        if(!empty($this->dynamicRoutes)){
            return;
        }

        $routeCollection = RouteFacade::getRoutes(); // RouteCollection object
        $allRoutes = $routeCollection->getRoutes();

        foreach ($allRoutes as $route) {
            if(empty($route->getName()) || !in_array($route->getName(), $this->candidatesRouteNames, true)){
                continue;
            }
            $this->dynamicRoutes[$route->getName()] = $route->parameterNames();
            $this->routes[$route->getName()] = $route;
        }

    }

    public function getUrlFromRouteName($url, $locale, $routeName, $attributes = [], $attributesSluged = false){

        $this->prepareDinamicRoute();

        // получаем параметры динамического роута
        $routeParams = $this->dynamicRoutes[$routeName];

        // проходимся по параметрам роута
        foreach($routeParams as $routeParam){
            $paramValue = Arr::get($attributes, $routeParam, null);

            // если значение не передано в $attributes для определенного параметра роутера это ошибка
            if(is_null($paramValue)){
                throw new \Exception('Invalid params: "'.$routeParam.'" for route: '.$routeName);
            }
            
            // при числовых значених получаем slug из базы, если string то slug уже в атрибутах и формировать запрос в базу уже не нужно
            if($attributesSluged){
                $url .= '/'.$paramValue;
            }else{
                //ищем в по modelParamRelation модель которая отвичает за сохранения параметра
                $model = $this->findModelByParam($routeParam);
                $url .= '/'.(new $model)->findLocalisationSlugByItem((int)$paramValue, (string)$routeParam, (string)$locale);
            }
        }

        return $url;
    }

    private function findModelByParam($routeParam){

        foreach($this->modelParamRelation as $model => $params){
            if(!in_array($routeParam, $params, true)){
                continue;
            }
            return $model;
        }
        return null;
    }

    public function getDynamicDataFromUrl($url, $attributes, $fromLocale){
        if(empty($url)){
            return false;
        }


        $this->prepareDinamicRoute();

        $parsedUrl  = parse_url($url);
        $this->clearPath($parsedUrl);

        $dynamicDataFromUrl = [];
        $dynamicDataFromUrl['findedItems'] = [];

        $slugsArr =  explode('/', $parsedUrl['path'] );

        $flugFindComplete = false;

        $position = 0; // текущая позиция поиска
        $previousPositions = []; // прошлые элементы, для определения типов источников поиска, напр. что бы не искать по ModelGroupDescription, если на указанной позиии ее нету
        $findedItems = [];

        foreach($slugsArr as $slugVal){
            // получение списка моделей, при указанной позиции и с поиском роута по предыдущим позициям
            $slugModels = get_model_relation($position, $this->dynamicRoutes, $this->modelParamRelation, $previousPositions);

            foreach($slugModels as $slugModel){
                $resFind = (new $slugModel)->findItemBySlug($slugVal, $fromLocale, $findedItems);

                if($resFind){
                    $dynamicDataFromUrl['findedItems'] = array_merge($dynamicDataFromUrl['findedItems'],$resFind);
                    $previousPositions[$position] = key($resFind);
                    break;
                }
            }
            $position++;
        }


        if(count($dynamicDataFromUrl['findedItems']) === count($slugsArr)){
            $flugFindComplete = true;
        }

        if($flugFindComplete){

            foreach($this->dynamicRoutes as $dynamicRoute => $params){
                if(count($params) !== count($slugsArr)){
                    continue;
                }
                // параметры в запросе должны совпадать с параметрами для роута
                if(array_diff($params, array_keys($dynamicDataFromUrl['findedItems'])) === array_diff(array_keys($dynamicDataFromUrl['findedItems']), $params)){
                    $dynamicDataFromUrl['routeName'] = $dynamicRoute;
                    break;
                }
            }
            return $dynamicDataFromUrl;
        }
        return null;
    }

    public function clearPath(&$parsedUrl) {
        $supportedLocales =  localization()->getSupportedLocales();


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