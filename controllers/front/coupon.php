<?php

class FlowPaymentWPCouponModuleFrontController extends ModuleFrontController{

    public function __construct(){
        parent::__construct();
        $this->display_column_left = false;
        $this->display_column_right = false;
    }

    public function initContent(){
        parent::initContent();
        $this->setTemplate('module:flowpaymentwp/views/templates/front/coupon.tpl');
    }
}