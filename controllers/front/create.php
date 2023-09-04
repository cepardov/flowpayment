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

use PrestaShop\PrestaShop\Adapter\Entity\PrestaShopLogger;

require_once(dirname(__FILE__) . "/../../lib-flow/PrestaFlowFlow.php");
require_once(dirname(__FILE__) . "/../../lib-flow/FlowUtils.php");

class FlowPaymentFlowCreateModuleFrontController extends ModuleFrontController
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
            PrestaShopLogger::addLog("Entering the create payment method...");
            $cart = $this->context->cart;
            $customer = $this->context->customer;
            $recharge = (float)Configuration::get('FLOW_ADDITIONAL');
            $currencyId = (int)$cart->id_currency;
            $currency = new Currency( (int)$cart->id_currency );
            $currencyName = $currency->iso_code;
            
            if($currencyName === "CLP")
            {
                $orderAmount = round((float)($cart->getOrderTotal(true, Cart::BOTH)));
                $additionalAmount = round(($orderAmount * $recharge)/100.0);
            }
            else
            {
                $orderAmount = (float)($cart->getOrderTotal(true, Cart::BOTH));
                $additionalAmount = (float)($orderAmount * $recharge)/100.0;
            }

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
            
            $concept = html_entity_decode('Orden #'.$orderNumber.' de '.Configuration::get('PS_SHOP_NAME'), ENT_QUOTES, 'UTF-8');
        
            $urlConfirm = $this->context->link->getModuleLink('flowpaymentflow', 'confirm', array());
            $urlReturn = $this->context->link->getModuleLink('flowpaymentflow', 'return', array());

            // if($additionalAmount > 0){
            //     $params["optional"] = json_encode(array(
            //         'Monto compra' => number_format($orderAmount, 0, ',', '.'). ' CLP',
            //         'Recargo comercio' => number_format($additionalAmount, 0, ',', '.'). ' CLP'
            //     ));
            // }
            
            $amount = $orderAmount + $additionalAmount;

            $data = array(
                "commerce_order" => $orderNumber,
                "subject" => $concept,
                "currency" => $currency->iso_code,
                "amount" => $amount,
                "email" => $customer->email,
                "url_confirm" => $urlConfirm,
                "url_return" => $urlReturn,
                "payment_method" => 9,
                "payment_currency" => $currency->iso_code
            );

            $customerData = array(
                "first_name" => $customer->firstname,
                "last_name" => $customer->lastname,
                "email" => $customer->email,
                "id" => $customer->id,
                "dob" => FlowUtils::checkDate($customer->birthday, "Y-m-d") ? $customer->birthday : null
            );

            $data["customer"] = $customerData;
            
            $deliveryAddress = $this->getAddress($order->id_address_delivery);

            if (!empty($deliveryAddress)) {
                $data["shipping_address"] = $deliveryAddress;
            }

            $billingAddress = $this->getAddress($order->id_address_invoice);

            if (!empty($billingAddress)) {
                $data["billing_address"] = $billingAddress;
            }
            
            $items = $this->getProducts($cart);

            if (!empty($items)) {
                $data["items"] = $items;
            }

            $data["metadata"] = $this->getMetadataComerce();

            $flowApi = PrestaFlowFlow::getFlowApiFlow();
            PrestaShopLogger::addLog('Calling flow service order/create from createPayment(): '.' with params: '.json_encode($data));
            $response = $flowApi->order($data);
            PrestaShopLogger::addLog('Flow response: '.json_encode($response));

            $redirect = $response["url_payment"];
            Tools::redirect($redirect);

        } catch (Exception $e) {
            if ($e->getCode() !== 1000) {
                PrestaShopLogger::addLog('There has been an unexpected error. Error code: '.$e->getCode(). ' - Message: '.$e->getMessage());
            }

            //Restoring cart
            PrestaShopLogger::addLog('Restoring cart...');
            $cart = new Cart(Cart::getCartIdByOrderId($orderNumber));
            $cartDuplicate = $cart->duplicate();
            $this->context->cookie->id_cart = $cartDuplicate['cart']->id;
            $context = $this->context;
            $context->cart = $cartDuplicate['cart'];
            CartRule::autoAddToCart($context);
            $this->context->cookie->write();
            $errorMessage = base64_encode($e->getMessage());
            Tools::redirect($this->context->link->getModuleLink('flowpaymentflow', 'error', array('message' => $errorMessage)));
        }
    }

    private function getAddress($addressId)
    {
        $address = new Address($addressId);
        $country = new Country($address->id_country);
        $addressData = array(
            "name" => $address->firstname." ".$address->lastname,
            "line1" => $address->address1,
            "line2" => $address->address2 ? $address->address2 : null ,
            "city" => $address->city ? $address->city : null,
            "zip" => $address->postcode,
            "phone" => $address->phone ? $address->phone : null,
            "country" => $country->iso_code
        );

        if (!empty($addressData["name"]) && !empty($addressData["country"]) && !empty($addressData["line1"])) {
            return $addressData;
        }

        return null;
    }

    private function getProducts($cart)
    {
        $products = $cart->getProducts(true);
        $finalProducts = array();
        foreach ($products as $product) {
            $finalProducts[] = array(
                "name" => $product["name"],
                "type" => "sku",
                "sku" => $product["id_product"],
                "description" => $product["description_short"],
                "quantity" => $product["cart_quantity"],
                "unit_cost" => FlowUtils::formatPrice($product["price_without_reduction"]),
                "amount" => FlowUtils::formatPrice($product["price"])
            );
        }
        
        return $finalProducts;
    }

    /**
     * Return metadata information comerce plugin
     *
     * @return array
     */
    private function getMetadataComerce()
    {
        $shopName = empty(Configuration::get('PS_SHOP_NAME')) ? "" : trim(Configuration::get('PS_SHOP_NAME'));
        
        $metadata = array();
        $metadata[] = array("key" => 'ecommerce_name', "value" => "Prestashop", "visible" => false);
        $metadata[] = array("key" => 'ecommerce_version', "value" => Configuration::get('PS_INSTALL_VERSION'), "visible" => false);
        $metadata[] = array("key" => 'plugin_name', "value" => "Flow Prestashop Checkout", "visible" => false);
        $metadata[] = array("key" => 'plugin_version', "value" => Configuration::get('FLOW_PAYMENT_VERSION'), "visible" => false);
        if (strlen($shopName) > 0) {
            $metadata[] = array("key" => 'shop_name', "value" => $shopName, "visible" => false);
        }
        return $metadata;
    }
}
