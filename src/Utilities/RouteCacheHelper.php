<?php

namespace Arcanedev\Localization\Utilities;
use Arcanedev\Localization\Contracts\RouteCacheHelper as RouteCacheHelperContract;
use Illuminate\Support\Facades\Cache;

class RouteCacheHelper implements RouteCacheHelperContract
{
    public $cacheKeyFormat = null;

    public $useExternalCache = null;

    public $externalCacheTtl = null;

    public $allowTypeToExternalCache = null;

    public function __construct()
    {
        $this->localCache = [];
    }

    /**
     * @var array
     */
    public $localCache = [];

    public function createCacheKey(array $cacheArgs):string
    {
        $hash = md5(serialize($cacheArgs));

        return $this->cacheKeyFormat['prefix']
            .str_replace(['{hash}', ], [$hash], $this->cacheKeyFormat['params']);
    }

    public function getFromCache(string $cacheKey):?string
    {
        if(array_key_exists($cacheKey, $this->localCache)){
            return (string)$this->localCache[$cacheKey];
        }

        if(Cache::has($cacheKey) &&  $this->useExternalCache){
            $fromExternalCache = (string)Cache::get($cacheKey);
            $this->localCache[$cacheKey] = $fromExternalCache;

            return $fromExternalCache;
        }
        return null;
    }

    public function isExistQuery(string $url){

        return parse_url($url, PHP_URL_QUERY) ? true : false;
    }


    public function saveToExternalCache($cacheKey, $cacheValue){
        Cache::put($cacheKey, $cacheValue, $this->externalCacheTtl);
    }

    public function isSaveToExternal(bool $queryInUrl, string $typeName){
        if(!$this->useExternalCache || $queryInUrl || !in_array($typeName, $this->allowTypeToExternalCache, true)){
            return false;
        }else{
            return true;
        }

    }

    public function transformTypeName($typeName){
        return str_replace('.', '_', $typeName);
    }


}
