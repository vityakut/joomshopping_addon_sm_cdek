<?php
/**
 * @version      1.0a
 * @date         01.07.2019
 * @author       Viktor Kutyavin
 * @copyright    Copyright (C) 2019 Viktor Kutyavin. All rights reserved.
 * @license      see license.txt
 */

require_once JPATH_ROOT.'/components/com_jshopping/shippings/sm_cdek/lib/cdek.php';

class sm_cdek extends shippingextRoot
{
    var $version = 2;
    private static $rubCode, $error, $note, $alerts, $debug;

    function showShippingPriceForm($params, &$shipping_ext_row, &$template)
    {
        include(dirname(__FILE__) . "/shippingpriceform.php");
    }

    function showConfigForm($config, &$shipping_ext, &$template)
    {
        $cdek = new Cdek();
        $packaging = $cdek->get('packaging');

        $sm = $this->getSM($shipping_ext->id);
        $checkedNo = $checkedYes = '';
        if ($config['debug']) {
            $checkedYes = 'checked="checked"';
        }
        else {
            $checkedNo = 'checked="checked"';
        }

        $shippingMetods = $cdek->get('methods');

        include(dirname(__FILE__) . "/configform.php");
    }

    function getPrices($cart, $params, $prices, &$shipping_ext_row, &$shipping_method_price)
    {

        //параметры способа доставки
        $sm_params = unserialize($shipping_ext_row->params);
        self::$debug = (!empty($sm_params['debug'])) ? (int)$sm_params['debug'] : 0;

        //загружаем пользователя
        $user = &JFactory::getUser();
        if ($user->id) {
            $adv_user = &JSFactory::getUserShop();
        } else {
            $adv_user = &JSFactory::getUserShopGuest();
        }

        $metods = $sm_params["shippingMetods"];
        $metods = array_flip($metods);
        $zip = ($adv_user->delivery_adress == 1) ? trim($adv_user->d_zip) : trim($adv_user->zip);

        $error = false;
        if(!isset($metods[$shipping_method_price->shipping_method_id])){
            $error = true;
        }
        if(empty($sm_params["zip_code"])){
            $error = true;
            self::$error[] = 'Не установлен индекс точки отправления';
        }
        if(empty($zip)){
            self::$error[] = 'Не опреледен индекс точки доставки';
            $error = true;
        }

        if($error){
            $prices['shipping'] = false;
            $prices['package'] = false;
            return $prices;
        }

        //конвертируем в валюту фронта
        $jshopConfig = &JSFactory::getConfig();
        $jshopConfig->loadCurrencyValue();

        if(empty(self::$rubCode)){
            $db = JFactory::getDbo();
            $query = 'SELECT `currency_value`
                  FROM `#__jshopping_currencies`
                  WHERE `currency_code_iso`="RUB"
                  LIMIT 1';
            $db->setQuery($query);
            self::$rubCode = $db->loadResult();
            self::$rubCode = (empty(self::$rubCode)) ? 1 : self::$rubCode;
        }


        $metodId = $metods[$shipping_method_price->shipping_method_id];
        $weight = 0;
        $quantity = 0;
        foreach ($cart->products as $product) {
            $weight += $product["quantity"] * $product["weight"];
	        $quantity += $product["quantity"];
        }

        $cdek = Cdek::getInstance();
        $cdek->code = $metodId;
        $cdek->weight = ($weight < $cdek->minweight) ? $cdek->minweight : $weight;
        $cdek->senderCityPostCode = $sm_params["zip_code"];
        $cdek->receiverCityPostCode = $zip;
        if(($sm_params["authLogin"]) && ($sm_params["authPassword"])) {
            $cdek->authLogin = $sm_params["authLogin"];
            $cdek->authPassword = $sm_params["authPassword"];
        }

        if (self::$debug) {
	        self::$note[] = 'Способ доставки '.$shipping_method_price->shipping_method_id;
        }
        //вычисляем стоимость доставки
        $price = $cdek->calculatePrice(self::$debug, self::$note);
        if($price == 0){
	        if (self::$debug) self::$error[] = 'Способ доставки '.$shipping_method_price->shipping_method_id;
	        self::$error[] = implode($cdek->get('error'), "\r\n");
            self::$alerts[] = $cdek->get('alerts');
            $price = -9999;
        }
        $prices['shipping'] = $price;

        $prices['shipping'] = ($prices['shipping'] * self::$rubCode);
        if (self::$debug) {
            self::$note[] = 'Тариф '.$cdek->code;
            self::$note[] = 'Вес '.$cdek->weight.' кг.';
            self::$note[] = 'Индекс места отправления '.$cdek->senderCityPostCode;
            self::$note[] = 'Индекс места назначения '.$cdek->receiverCityPostCode;
            self::$note[] = 'Стоимость посылки '.$price.' руб.';
            self::$note[] = 'Стоимость посылки в валюте магазина '.$prices['shipping'] ;
            self::printDebug();
        }

        return $prices;
    }

    /**
     * Способы доставки
     * @return mixed
     */
    private function getSM($shipping_ext_id)
    {
//        TODO: прятать сдек если недоступна доставка
        $db = JFactory::getDbo();

        $query = 'SELECT shipping_method
                  FROM `#__jshopping_shipping_ext_calc`
                  WHERE id = '.(int)$shipping_ext_id;

        $shippings = $db->setQuery($query,0,1)->loadResult();
        if(empty($shippings)){
            return array();
        }
        $shippings = unserialize($shippings);

        if(!is_array($shippings) || !count($shippings)){
            return array();
        }
        $enableShippings = array();
        foreach ($shippings as $k => $v){
            if($v == 1){
                $enableShippings[] = $k;
            }
        }
        if(!count($enableShippings)){
            return array();
        }
        $enableShippings = implode(', ', $enableShippings);

        $lang = &JSFactory::getLang();
        $query = 'SELECT shipping_id as id, `' . $lang->get("name") . '` as name
                  FROM `#__jshopping_shipping_method`
                  WHERE shipping_id IN ('.$enableShippings.')
                  ORDER BY ordering';
        $db->setQuery($query);
        $result = $db->setQuery($query)->loadObjectList();
        if(!is_array($result)){
            $result = array();
        }
        return $result;
    }

    private static function printDebug()
    {
        echo '<pre>';
        if (count(self::$note)) {
            echo '<h4>Информация</h4>';
            echo '<ul>';
            foreach (self::$note as $n) {
                echo '<li>' . $n . '</li>';
            }
            echo '</ul>';
        }

        if (count(self::$error)) {
            echo '<h4>Ошибки</h4>';
            echo '<ul>';
            foreach (self::$error as $e) {
                echo '<li><span style="color: #ff0000;">' . $e . '</span></li>';
            }
            echo '</ul>';
        }
        if (count(self::$alerts)){
            echo '<h4>Сообщения</h4>';
            echo '<ul>';
            foreach (self::$alerts as $alert) {
                echo '<li><span>' . $alert . '</span></li>';
            }
            echo '</ul>';
        }
        echo '</pre>';
        self::$note = self::$error = self::$alerts = array();
    }

}

?>
