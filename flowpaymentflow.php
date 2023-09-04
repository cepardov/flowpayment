<?php
/**
 * 2022 Tuxpan
 * Clase de módulo de pago Prestashop de Flow
 *
 *  @author flow.cl
 *  @copyright  2022 Tuxpan
 *  @version: 3.0.1
 *  @Email: soporte@tuxpan.com
 *  @Date: 15-05-2018 11:00
 *  @Last Modified by: Tuxpan
 *  @Last Modified time: 25-04-2022 11:00
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

include_once(_PS_MODULE_DIR_ . 'flowpaymentflow/lib-flow/FlowApiFlow.class.php');
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

class FlowPaymentFlow extends PaymentModule
{
    protected $errors = array();
    protected $paymentMediumName;
    
    public function __construct()
    {
        $this->paymentMediumName = 'flow';
        $this->name = 'flowpaymentflow';
        $this->friendlyPaymentMediumName = 'Pago con la pasarela de Flow';

        parent::__construct();
        
        $this->displayName = $this->l(utf8_encode('Pago usando Flow'));
        $this->description = $this->l(utf8_encode('Pago con Flow'));
        
        $this->author = 'Flow';
        $this->version = '3.0.1';
        $this->tab = 'payments_gateways';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        
        // Module settings
        $this->setModuleSettings();
        
        // Check module requirements
        $this->checkModuleRequirements();
    }
    
    public function install()
    {
        if (!parent::install() || !$this->registerHook('paymentOptions') || !$this->registerHook('paymentReturn')) {
            return false;
        }
        Configuration::updateValue('FLOW_PAYMENT_VERSION', $this->version);
        if(!Configuration::get('FLOW_PAYMENT_PENDING')){
            $orderState = new OrderState();
            $orderState->name = array();
            $orderState->module_name = $this->name;
            $orderState->send_email = false;
            $orderState->color = 'blue';
            $orderState->hidden = false;
            $orderState->delivery = false;
            $orderState->logable = false;
            $orderState->invoice = false;
            $orderState->paid = false;

            foreach (Language::getLanguages() as $language) {
                $orderState->template[$language['id_lang']] = 'payment';
                $orderState->name[$language['id_lang']] = 'Pending Payment';
            }

            if(!$orderState->add()){
                
                return false;
            }

            Configuration::updateValue('FLOW_PAYMENT_PENDING', (int)$orderState->id);
        }
        return true;
    }
    
    public function uninstall(){
        
        if(!parent::uninstall()){
            return false;
        }
        
        Configuration::deleteByName('FLOW_TITLE');
        Configuration::deleteByName('FLOW_PLATFORM');
        Configuration::deleteByName('FLOW_ADDITIONAL');
        Configuration::deleteByName('FLOW_APIKEY');
        Configuration::deleteByName('FLOW_RETURN_URL');
        
        return true;
    }
    
    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        return array($this->getExternalPaymentOption());
    }

    public function getExternalPaymentOption()
    {
        $recargo = $this->getRecargo();
     
        $externalOption = new PaymentOption();
        $externalOption->setCallToActionText("Flow - ".Configuration::get('FLOW_TITLE'))
        ->setAdditionalInformation($recargo)
        ->setAction($this->context->link->getModuleLink('flowpaymentflow', 'create', array()))
        ->setLogo($this->getLogo());
        return $externalOption;
    }
    
    public function getContent()
    {
        $imagePath = dirname(__FILE__)."/views/img";
        
        $isSubmitted = false;
        
        if (Tools::getIsset('flow_updateSettings')) {
            $isSubmitted = true;
            Configuration::updateValue('FLOW_TITLE', Tools::getValue('title'));
            Configuration::updateValue('FLOW_PLATFORM', Tools::getValue('platformType'));
            Configuration::updateValue('FLOW_ADDITIONAL', Tools::getValue('additional'));
            Configuration::updateValue('FLOW_APIKEY', Tools::getValue('apiKey'));
            Configuration::updateValue('FLOW_RETURN_URL', Tools::getValue('returnUrl'));
            $hasFile = false;
            if (isset($_FILES['logoSmall'])) {
                $file = $_FILES['logoSmall'];
                $hasFile = $file['size']>0;
                $filename = "";
                if ($hasFile) {
                    move_uploaded_file($file['tmp_name'], "$imagePath/".$file['name']);
                    $filename = $this->resizeImage($file['name']);
                }

                if($filename != '') {
                    Configuration::updateValue('FLOW_IMAGE', $filename);
                }
            }
            $this->setModuleSettings();
            $this->checkModuleRequirements();
        } else {
            $this->setModuleSettings();
        }
        
        $medio_pago = "flow";
        
        $noErrors = empty($this->errors);
        
        $vars = array(
            'errors' => $this->errors,
            'post_url' => Tools::htmlentitiesUTF8($_SERVER['REQUEST_URI']),
            'data_platformType' => $this->platformType,
            'data_apiKey' => $this->apiKey,
            'data_additional' => $this->additional,
            'data_title' => $this->title,
            'version' => $this->version,
            'img_header' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ .
            "modules/$this->name/views/img/logo.png",
            'data_logoSmall' => $this->getLogo(),
            'medio_pago' => $medio_pago,
            'noErrors' => $noErrors,
            'isSubmitted' => $isSubmitted,
            'return_url' => $this->returnUrl
        );

        $this->context->smarty->assign($vars);
        return $this->display($this->name, 'views/templates/admin/config.tpl');
    }
    
    private function setModuleSettings()
    {
        $this->title = !empty(Configuration::get('FLOW_TITLE')) ? Configuration::get('FLOW_TITLE') : utf8_encode($this->friendlyPaymentMediumName);
        $this->apiKey = Configuration::get('FLOW_APIKEY');
        $this->platformType = Configuration::get('FLOW_PLATFORM');
        $this->additional = (float)Configuration::get('FLOW_ADDITIONAL');
        $this->returnUrl = Configuration::get('FLOW_RETURN_URL');
    }
    
    private function checkModuleRequirements()
    {
        $this->errors = array();
        if ($this->title == '') {
            $this->errors['title'] = 'Debe indicar el nombre que se usará para este medio de pago';
        }
        if ($this->additional === '') {
            $this->errors['additional'] = 'Si no tiene cobro adicional, indique 0';
        } else {
            $additional = (float)$this->additional;
            if ($additional<0 || $additional>100) {
                $this->errors['additional'] = 'Porcentaje debe estar entre 0 y 100';
            }
        }
        if ($this->apiKey == '') {
            $this->errors['apiKey'] = 'Debe ingresar su llave de integración';
        }
        
    }
    
    private function getRecargo()
    {
        $str = '';
        $cart = $this->context->cart;
        $recargo = (float)Configuration::get('FLOW_ADDITIONAL');
        
        if (!is_null($recargo) && is_numeric($recargo)  && $recargo > 0) {
            $monto_orden = (int)($cart->getOrderTotal(true, Cart::BOTH));
            $monto_adicional = round(($monto_orden * $recargo)/100.0);
            $str = '<b>Se aplicara un cargo adicional del '. $recargo . '%, equivalente a $ '.$monto_adicional.'</b>';
        }
        return $str;
    }

    private function getLogo() {
        $customImage = Configuration::get('FLOW_IMAGE');
        $defaultImage = "logo-small.png";

        return Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ ."modules/{$this->name}/views/img/".
            ($this->existImage($customImage) ? $customImage : $defaultImage);
    }

    private function existImage($filename) {
        return $filename != '' && file_exists(dirname(__FILE__)."/views/img/".$filename);
    }

    private function resizeImage($filename) {
        $basePath = dirname(__FILE__)."/views/img/";
        $sourceImage = $basePath . $filename;
        $imageSize = getimagesize($sourceImage);
        $perfectSize = $this->getPerfectSize($sourceImage, $imageSize);
        $type = $imageSize["mime"];
        
        if(!$image = $this->imagecreatefromfile($sourceImage)) {
            return "";
        }
        $thumbnail = imagecreatetruecolor($perfectSize["width"], $perfectSize["height"]);
        if( $type == "image/png" ) {
            imagesavealpha($thumbnail, true);
            imagealphablending($thumbnail, false);
            $transparent = imagecolorallocatealpha($thumbnail, 0, 0, 0, 127);
            imagefill($thumbnail, 0, 0, $transparent);
        }
        imagecopyresampled($thumbnail,$image,0,0,0,0,$perfectSize["width"],$perfectSize["height"],$imageSize[0],$imageSize[1]);
        $newfilename = $this->imagetofile($basePath, "custom-logo-small", $type, $thumbnail);
        if($newfilename != '' && file_exists($basePath.$filename)) {
            unlink($basePath.$filename);
        }
        return $newfilename;
    }

    private function getPerfectSize($sourceImage, $size) {
        $perfectWidth = 35;
        $perfectHeight = 10;

        $width = $size[0];
        $height = $size[1];

        $widthHor = $perfectWidth;
        $heightHor = $height / $width * $widthHor;

        $heightVer = $perfectHeight;
        $widthVer = $width / $height * $heightVer;

        $calcSize = array(
            "width" => 0,
            "height" => 0
        );

        if ($heightHor <= $perfectHeight) {
            $calcSize["width"] = $widthHor;
            $calcSize["height"] = $heightHor;
        } else {
            $calcSize["width"] = $widthVer;
            $calcSize["height"] = $heightVer;
        }

        return $calcSize;
    }

    private function imagecreatefromfile( $filename ) {
        if (!file_exists($filename)) {
            return false;
        }
        $extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ));
        switch ($extension) {
            case 'jpeg':
            case 'jpg':
                return imagecreatefromjpeg($filename);
                break;
            case 'png':
                return imagecreatefrompng($filename);
                break;
            case 'gif':
                return imagecreatefromgif($filename);
            break;
            default:
                return false;
            break;
        }   
    }

    private function imagetofile($basepath, $filename, $type, $thumbnail) {
        //$filename .= "-".time();
        switch ($type) {
            case "image/gif"  :
                $filename .= ".gif";
                if(!imagegif($thumbnail, $basepath.$filename)) {
                    $filename = "";
                }
                break;
            case "image/png"  :
                $filename .= ".png";
                if(!imagepng($thumbnail, $basepath.$filename)) {
                    $filename = "";
                }
                break;
            case "image/jpeg" :
            default:
                $filename .= ".jpeg";
                if(!imagejpeg($thumbnail, $basepath.$filename)) {
                    $filename = "";
                }
        }
        return $filename;
    }
}
