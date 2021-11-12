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

class FlowPaymentWPCreateModuleFrontController extends ModuleFrontController
{

    public function initContent()
    {
        $this->display_column_left = false;
        $this->display_column_right = false;

        parent::initContent();
        $this->createPayment();
    }

    private function createPayment()
    {
        try {
            Logger::addLog('Entering the create payment method...');
            $cart = $this->context->cart;
            $customer = $this->context->customer;
            $recharge = (float)Configuration::get('FLOW_WP_ADDITIONAL');
            $currencyId = (int)$cart->id_currency;
            $currency = new Currency( (int)$cart->id_currency );
            $currencyName = $currency->iso_code;
            
            $orderAmount = round((float)($cart->getOrderTotal(true, Cart::BOTH)));
            $additionalAmount = round(($orderAmount * $recharge)/100.0);
            $amount = $orderAmount + $additionalAmount;

            $this->module->validateOrder(
                $cart->id,
                Configuration::get('FLOW_PAYMENT_PENDING'),
                $orderAmount,
                $this->module->displayName,
                null,
                array(),
                null,
                false,
                $cart->secure_key
            );
            
            $order = new Order(Order::getOrderByCartId($cart->id));
            $orderNumber = $order->id;
            
            $allowedCurrencies = array(
                'CLP'
            );
            
            if(!in_array($currencyName, $allowedCurrencies)){            
                throw new Exception("The currency $currencyName is not allowed by Flow");
            }
            
            $concept = html_entity_decode('Orden #'.$orderNumber.' de '.Configuration::get('PS_SHOP_NAME'), ENT_QUOTES, 'UTF-8');
        
            $urlConfirm = $this->context->link->getModuleLink('flowpaymentwp', 'confirm', array());
            $urlReturn = $this->context->link->getModuleLink('flowpaymentwp', 'return', array());
            
            $params = array(
                "commerceOrder"     => $orderNumber,
                "subject"           => $concept,
                "currency"          => $currency->iso_code,
                "amount"            => $amount,
                "email"             => $customer->email,
                "paymentMethod"     => $this->module->getPaymentMethod(),
                "urlConfirmation"   => $urlConfirm,
                "urlReturn"         => $urlReturn,
            );
            
            if($additionalAmount > 0){
                $params["optional"] = json_encode(array(
                    'Monto compra' => number_format($orderAmount, 0, ',', '.'). ' CLP',
                    'Recargo comercio' => number_format($additionalAmount, 0, ',', '.'). ' CLP'
                ));
            }
            $serviceName = "payment/create";

            $flowApi = PrestaFlowWP::getFlowApiWP();
            Logger::addLog('Calling flow service from create(): '.$serviceName.' with params: '.json_encode($params));
            $response = $flowApi->send($serviceName, $params, "POST");

            if (!isset($response["url"]) || !isset($response["token"])) {
                
                if (isset($response["message"]) && $response["code"]) {
                    throw new \Exception($response["message"], $response["code"]);
                } else {
                    throw new \Exception("There was an error tryning to create the payment in Flow");
                }

            }

            $redirect = $response["url"] . "?token=" . $response["token"];
            Tools::redirect($redirect);

        } catch (Exception $e) {
            Logger::addLog('There has been an unexpected error. Error code: '.$e->getCode(). ' - Message: '.$e->getMessage());

            //Restoring cart
            PrestaShopLogger::addLog('Restoring cart...');
            $cart = new Cart(Cart::getCartIdByOrderId($orderNumber));
            $cartDuplicate = $cart->duplicate();
            $this->context->cookie->id_cart = $cartDuplicate['cart']->id;
            $context = $this->context;
            $context->cart = $cartDuplicate['cart'];
            CartRule::autoAddToCart($context);
            $this->context->cookie->write();

            Tools::redirect($this->context->link->getModuleLink('flowpaymentwp', 'error', array('message' => $e->getMessage())));

        }
    }
}
