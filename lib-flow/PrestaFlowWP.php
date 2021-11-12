<?php

include_once(dirname(__FILE__).'/../../../config/config.inc.php');
include_once(__DIR__."/FlowApiWP.class.php");

class PrestaFlowWP
{
    public static function getFlowApiWP()
    {
        $platform = Configuration::get('FLOW_WP_PLATFORM');
        $isTestPlatform = !$platform || $platform == 'test';
        $urlApi = $isTestPlatform ? "https://sandbox.flow.cl/api" : "https://www.flow.cl/api";

        $apiKey = Configuration::get('FLOW_WP_APIKEY');
        $secretKey = Configuration::get('FLOW_WP_PRIVATEKEY');
        
        return new FlowApiWP($apiKey, $secretKey, $urlApi);
    }
}