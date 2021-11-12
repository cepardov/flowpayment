<?php
/**
* 2018 Tuxpan
* Clase de módulo Front de pago Prestashop de Flow
*
*  @author flow.cl
*  @copyright  2018 Tuxpan
*  @version: 2.0
*  @Email: soporte@tuxpan.com
*  @Date: 15-05-2018 11:00
*  @Last Modified by: Tuxpan
*  @Last Modified time: 15-05-2018 11:00
*/

require_once(dirname(__FILE__) . "/../../lib-flow/PrestaFlowWP.php");
require_once(dirname(__FILE__) . "/../../flowpaymentwp.php");

class FlowPaymentWPConfirmModuleFrontController extends ModuleFrontController
{

    public function __construct(){
        parent::__construct();
    }

    public function initContent(){
        parent::initContent();
        $this->setTemplate('module:flowpaymentwp/views/templates/front/confirmation.tpl');
    }

    public function postProcess(){

        try{

            PrestaShopLogger::addLog('Entering the confirm callback', 1);
        
            if (!Tools::getIsset("token")) {
                throw new Exception("No token received", 1);
            }

            $token = filter_input(INPUT_POST, 'token');
            $params = array( "token" => $token );
            $serviceName = 'payment/getStatus';
            
            Logger::addLog('Calling flow service from confirm(): '.$serviceName.' with params: '.json_encode($params));
            $flowApi = PrestaFlowWP::getFlowApiWP();
            $response = $flowApi->send($serviceName, $params, "GET");
            Logger::addLog('Flow response: '.json_encode($response));

            $amount = (int)$response["amount"];
            $order = new Order((int) $response['commerceOrder']);
            $cart = new Cart(Cart::getCartIdByOrderId($order->id));

            if (!$cart) {
                throw new Exception('The order does not exists.');
            }
        
            $recharge = (float) Configuration::get('FLOW_WP_ADDITIONAL');
            $orderTotal = round((float) $cart->getOrderTotal(true, Cart::BOTH));
            $orderTotalWithAdditional = $orderTotal + round(($orderTotal * $recharge) / 100.0);

            if($amount != $orderTotalWithAdditional ){
                throw new Exception('The amount has been altered. Aborting...');
            }

            $status = $response['status'];

            /*if($this->isTesting($response)){
                PrestaShopLogger::addLog('Testing environment detected, setting up simulation...');
                $this->setUpProductionEnvSimulation($status);
            }*/

            if($this->isPendingInFlow($status)){
                Logger::addLog('Setting order as pending...');
                $orderStatus = Configuration::get('FLOW_PAYMENT_PENDING');
            }
            elseif($this->isPaidInFlow($status)){
                Logger::addLog('Setting order as paid...');
                $orderStatus = Configuration::get('PS_OS_PAYMENT');
            }
            elseif($this->isCanceledInFlow($status)){
                Logger::addLog('Setting order as canceled...');
                $orderStatus = Configuration::get('PS_OS_CANCELED');
            }
            else{
                Logger::addLog('Setting order as rejected...');
                $orderStatus = Configuration::get('PS_OS_ERROR');
            }

            //If the order already has already the pending state, it means it this was previously called, therefore
            //we update the status.

            if($order->getCurrentState() === Configuration::get('FLOW_PAYMENT_PENDING')){
                PrestaShopLogger::addLog('Changing the order status');
                
                if($orderStatus !== Configuration::get('FLOW_PAYMENT_PENDING')){
                    PrestaShopLogger::addLog('Changing the order status to '.$orderStatus);
                    $order->setCurrentState($orderStatus);
                }
            }
            
            return;
        }catch(Exception $e){

            Logger::addLog('There has been an unexpected error. Code: '.$e->getCode(). ' Message: '.$e->getMessage());
            return;
        }
    }

    public function isPendingInFlow($status){
        return $status === 1;
    }

    public function isPaidInFlow($status){
        return $status === 2;
    }

    public function isRejectedInFlow($status){
        return $status === 3;
    }

    public function isCanceledInFlow($status){
        return $status === 4;
    }
    
    private function isTesting($flowPaymentData){
        return strtolower(Configuration::get('FLOW_WP_PLATFORM')) === 'test'
            && (strtolower($flowPaymentData['paymentData']['media']) === 'multicaja'  || strtolower($flowPaymentData['paymentData']['media']) == 'servipag' );
    }
    
    private function setUpProductionEnvSimulation(&$status){
        $status = 1;
    }
}
