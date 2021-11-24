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
    public $ssl = true;

    public function initContent() {

        parent::initContent();
        //$this->setTemplate('module:flowpaymentwp/views/templates/front/confirmation.tpl');
        $this->returnPayment();
    }

    private function returnPayment()
    {

        try {

            PrestaShopLogger::addLog('[return] Entering the return callback...',1);

            if (!Tools::getIsset("token")) {
                throw new Exception("No se recibio el token", 1);
            }

            $orderStatusPaid = (int)Configuration::get('PS_OS_PAYMENT');
            $orderStatusPending = (int)Configuration::get('FLOW_PAYMENT_PENDING');
            $orderStatusRejected = (int)Configuration::get('PS_OS_ERROR');

            $serviceName = "payment/getStatus";

            $token = filter_input(INPUT_POST, 'token');
            $params = array( "token" => $token );

            Logger::addLog('Calling flow service from confirm(): '.$serviceName.' with params: '.json_encode($params));
            $flowApi = PrestaFlowWP::getFlowApiWP();
            $response = $flowApi->send($serviceName, $params, "GET");
            PrestaShopLogger::addLog('Flow response: '.json_encode($response));

            $order = new Order((int) $response['commerceOrder']);
            $cart = new Cart(Cart::getCartIdByOrderId($order->id));

            if (!$cart) {
                throw new Exception('The order does not exists.');
            }
            
            $status = $response["status"];
            $amount = (int)$response["amount"];
            $recharge = (float)Configuration::get('FLOW_WP_ADDITIONAL');
            $orderTotal = (int)($cart->getOrderTotal(true, Cart::BOTH));
            $orderTotalWithAdditional = $orderTotal + round(($orderTotal * $recharge) / 100.0);

            if($amount != $orderTotalWithAdditional ){
                throw new Exception('The amount has been altered. Aborting...');
            }

            if($this->userCanceledPayment($status, $response)){
                PrestaShopLogger::addLog('The user canceled the payment. Redirecting to the checkout...');
                $order->setCurrentState((int)Configuration::get('PS_OS_CANCELED'));
                $this->restoreCart($order->id);
                //Redirecting to the checkout
                Tools::redirect('order');
            }

            $order = new Order(Order::getOrderByCartId($cart->id));
            PrestaShopLogger::addLog('[return] order 2: '.json_encode($order));

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
                    $order->setCurrentState((int)Configuration::get('PS_OS_PAYMENT'));
                    $this->redirectToSuccess($cart, $order);
                }
                else{
                    $order->setCurrentState((int)Configuration::get('PS_OS_ERROR'));
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
        $customer = $order->getCustomer();
        PrestaShopLogger::addLog('Customer...'.json_encode($customer));
        $urlOrderConfirmation = 'index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&id_order='.$order->id.'&key='.$customer->secure_key;
        PrestaShopLogger::addLog('urlOrderConfirmation: '.$urlOrderConfirmation);
        //Tools::redirect($urlOrderConfirmation);

        $mod_id = Module::getInstanceByName($order->module);

        if (Tools::getValue('return') == 'ok') {
            Tools::redirect(
                Tools::getShopDomainSsl(
                    true,
                    true
                ) . __PS_BASE_URI__ . 'index.php?controller=order-confirmation&id_cart=' . $cart->id
                . '&id_module=' . (int)$mod_id->id . '&id_order=' . $order->id . '&key=' . $customer->secure_key
            );
        }
    }
    
    private function redirectToFailure($params = array()){
        PrestaShopLogger::addLog('Redireccionando compra erronea...');
        Tools::redirect($this->context->link->getModuleLink('flowpaymentwp', 'paymentfailure', $params));
    }
    
    private function redirectToError($params = array()){
        Tools::redirect($this->context->link->getModuleLink('flowpaymentwp', 'error', $params));
    }
}
