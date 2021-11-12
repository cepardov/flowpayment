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

class FlowPaymentWPReturnModuleFrontController extends ModuleFrontController
{

    public function initContent()
    {
        $this->display_column_left = false;
        $this->display_column_right = false;

        parent::initContent();
        $this->returnPayment();
    }

    private function returnPayment()
    {

        try {

            PrestaShopLogger::addLog('Entering the return callback...');

            if (!Tools::getIsset("token")) {
                throw new Exception("No se recibio el token", 1);
            }

            $serviceName = "payment/getStatus";

            $token = filter_input(INPUT_POST, 'token');
            $params = array( "token" => $token );

            $flowApi = PrestaFlowWP::getFlowApiWP();
            PrestaShopLogger::addLog('Calling flow service from return(): '.$serviceName.' with params: '.json_encode($params));
            $response = $flowApi->send($serviceName, $params, "GET");
            PrestaShopLogger::addLog('Flow response: '.json_encode($response));

            //$order = new Order((int) $response['commerceOrder']);
            $orderNumber = (int) $response['commerceOrder'];
            PrestaShopLogger::addLog('[Return] orderNumber: '.$orderNumber);
            $order = new Order(Order::getOrderByCartId($orderNumber));
            PrestaShopLogger::addLog('[Return] order: '.json_encode($order));
            $cart = new Cart(Cart::getCartIdByOrderId($order->id));
            
            $status = $response["status"];
            $amount = (int)$response["amount"];
            $recharge = (float)Configuration::get('FLOW_WP_ADDITIONAL');
            $orderTotal = (int)($cart->getOrderTotal(true, Cart::BOTH));
            
            $orderTotalAdditional = (int)($orderTotal + round(($orderTotal * $recharge)/100.0));
    
            $orderStatusPaid = (int)Configuration::get('PS_OS_PAYMENT');
            $orderStatusPending = (int)Configuration::get('FLOW_PAYMENT_PENDING');
            $orderStatusRejected = (int)Configuration::get('PS_OS_ERROR');
            $orderStatusCanceled = (int)Configuration::get('PS_OS_CANCELED');

            if($this->userCanceledPayment($status, $response)){
                PrestaShopLogger::addLog('The user canceled the payment. Redirecting to the checkout...');
                $this->restoreCart($order->id);
                //Redirecting to the checkout
                Tools::redirect('order');
            }

            /*if($this->isTesting($response)){
                PrestaShopLogger::addLog('Testing environment detected, setting up simulation...');
                $this->setUpProductionEnvSimulation($status, $response);
            }*/
            //PrestaShopLogger::addLog('[Return] obtener orden...');
            //$order = new Order(Order::getOrderByCartId($cart->id));
            //PrestaShopLogger::addLog('[Return] orden obtenida '.$order);

            //If for some reason the confirmation callback was never called. We validate the order right here.
            
            //If the order has a valid status, this is: Either paid or pending
            if($order->valid || $order->getCurrentState() == $orderStatusPending ){

                
                if($this->userGeneratedCoupon($status, $response)){
                    PrestaShopLogger::addLog('The user generated a coupon. Redirecting there...');

                    //If the there's any return url configured, we redirect there.
                    if(!empty(Configuration::get('FLOW_WP_RETURN_URL'))){
                        Tools::redirect(Configuration::get('FLOW_WP_RETURN_URL'));
                    }
                    Tools::redirect($this->context->link->getModuleLink('flowpaymentwp', 'coupon', array()));
                }
    
                if($this->isPaidInFlow($status)){                    
                    PrestaShopLogger::addLog('Everything went right. Redirecting to the success page.');
                    //$order->setCurrentState((int)Configuration::get('PS_OS_PAYMENT'));
                    $this->redirectToSuccess($cart, $order);
                }
                else{
                    $this->restoreCart($order->id);
                    PrestaShopLogger::addLog('Order was rejected. Redirecting to failure...');
                    $this->redirectToFailure();
                }
            }
            //Otherwise, we redirect the user to our very own failure page, since apparently ps doesn't have any payment error page.
            else{
                
                $this->restoreCart($order->id);
                PrestaShopLogger::addLog('Order was rejected. Redirecting to failure...');
                $this->redirectToFailure();
            }

        } catch (Exception $e) {
            PrestaShopLogger::addLog('There has been an unexpected error. Error code: '.$e->getCode(). ' - Message: '.$e->getMessage());
            Tools::redirect($this->context->link->getModuleLink('flowpaymentwp', 'error', array('code' => $e->getCode())));
        }
    }

    private function restoreCart($orderId){
        
        PrestaShopLogger::addLog('Restoring cart...');
        $cart = new Cart(Cart::getCartIdByOrderId($orderId));
        $cartDuplicate = $cart->duplicate();
        $this->context->cookie->id_cart = $cartDuplicate['cart']->id;
        $context = $this->context;
        $context->cart = $cartDuplicate['cart'];
        CartRule::autoAddToCart($context);
        $this->context->cookie->write();
    }
    
    private function isPendingInFlow($status){
        return $status === 1;
    }

    private function isPaidInFlow($status){
        return $status === 2;
    }

    private function isRejectedInFlow($status){
        return $status === 3;
    }

    private function isCanceledInFlow($status){
        return $status === 4;
    }

    private function userCanceledPayment($status, $flowPaymentData){
        return $this->isPendingInFlow($status)
        && empty($flowPaymentData['paymentData']['media'])
        && empty($flowPaymentData['pending_info']['media']);
    }

    private function userGeneratedCoupon($status, $flowPaymentData){
        
        return $this->isPendingInFlow($status)
        && !empty($flowPaymentData['pending_info']['media']
        && empty($flowPaymentData['paymentData']['media']));
    }
    
    private function isTesting($flowPaymentData){
        return strtolower(Configuration::get('FLOW_WP_PLATFORM')) === 'test'
            && (strtolower($flowPaymentData['paymentData']['media']) === 'multicaja'  || strtolower($flowPaymentData['paymentData']['media']) == 'servipag' );
    }
    
    private function setUpProductionEnvSimulation(&$status, &$flowPaymentData){
        $status = 1;
        $flowPaymentData['pending_info']['media'] = $flowPaymentData['paymentData']['media'];
        $flowPaymentData['paymentData']['media'] = '';
    }
    
    private function redirectToSuccess($cart, $order){
        PrestaShopLogger::addLog('Redireccionando compra correcta...');
        Tools::redirect('index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&id_order='.$order->id.'&key='.$cart->secure_key);
    }
    
    private function redirectToFailure($params = array()){
        PrestaShopLogger::addLog('Redireccionando compra erronea...');
        Tools::redirect($this->context->link->getModuleLink('flowpaymentwp', 'paymentfailure', $params));
    }
    
    private function redirectToError($params = array()){
        Tools::redirect($this->context->link->getModuleLink('flowpaymentwp', 'error', $params));
    }
}
