<?php

class FlowPaymentWPErrorModuleFrontController extends ModuleFrontController{
    
    public function initContent(){
        parent::initContent();
        $message = Tools::getValue('message');
        $this->context->smarty->assign('errorMessage', $message);
        $this->setTemplate('module:flowpaymentwp/views/templates/front/flow_error.tpl');
    }
    
    public function getErrorMsg($error_code){
        $errors = array(
            '1' => "Ha ocurrido un error, no se recibió token de orden",
            '000' => "Ha ocurrido un error inesperado en la comunicacion con el medio de pago",
            '108' => "Ha ocurrido un error inesperado en la comunicacion con el medio de pago. La firma no es v&aacutelida",
            '501' => "Ha ocurrido un error inesperado en la comunicacion con el medio de pago"
        );
        return isset($errors[$error_code])?$errors[$error_code]:$errors['000'];
    }
    
}
