<?php

include_once(dirname(__FILE__).'/../../../config/config.inc.php');
include_once(__DIR__."/FlowApiFlow.class.php");

class PrestaFlowFlow
{
    public static function getFlowApiFlow()
    {
        $platform = Configuration::get('FLOW_PLATFORM');
        $isTestPlatform = !$platform || $platform == 'test';
        $urlApi = $isTestPlatform ? "https://sandbox.flow.cl/api/v2" : "https://www.flow.cl/api/v2";

        $apiKey = Configuration::get('FLOW_APIKEY');
        
        return new FlowApiFlow($urlApi, $apiKey);
    }
}