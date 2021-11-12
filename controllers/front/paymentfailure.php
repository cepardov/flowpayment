<?php

class FlowPaymentWPPaymentFailureModuleFrontController extends ModuleFrontController{


    public function __construct(){
        parent::__construct();
    }

    public function initContent(){
        $this->display_column_left = false;
        $this->display_column_right = false;

        parent::initContent();

        $this->setTemplate('module:flowpaymentwp/views/templates/front/payment_failure.tpl');
    }
}