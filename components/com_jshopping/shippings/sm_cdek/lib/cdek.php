<?php
    /**
     * @version      1.0a
     * @date         01.07.2019
     * @author       Viktor Kutyavin
     * @copyright    Copyright (C) 2019 Viktor Kutyavin. All rights reserved.
     * @license      see license.txt
     */

class Cdek
{
    private $apiUrl, $methods, $date, $error, $alerts;

    var $code, //объект тарификации
        $authLogin,
        $authPassword,
        $secure,
        $dateExecute,
        $senderCityPostCode,
        $receiverCityPostCode,
        $tariffId,

        $version, // версия api
        $weight, //вес, кг.
        $minweight


    ;

    private static $_instance;

    public function __construct()
    {
        $date = new DateTime();
        $this->date = $date->format('Ymd');
        $this->minweight = 0.1;
        $this->version = "1.0";
        $this->apiUrl = 'http://api.cdek.ru/calculator/calculate_price_by_json.php';
        $this->dateExecute = date("Y-m-d");
        $this->methods = array(
            136 => array(
                'code' => 136,
                'name' => 'Посылка склад-склад',
                'mode' => 4,
                'minWeight' => 0.1,
                'maxWeight' => 30,
            ),
            137 => array(
                'code' => 137,
                'name' => 'Посылка склад-дверь',
                'mode' => 3,
                'minWeight' => 0.1,
                'maxWeight' => 30,
            ),
            138 => array(
                'code' => 138,
                'name' => 'Посылка дверь-склад',
                'mode' => 2,
                'minWeight' => 0.1,
                'maxWeight' => 30,
            ),
            139 => array(
                'code' => 139,
                'name' => 'Посылка дверь-дверь',
                'mode' => 1,
                'minWeight' => 0.1,
                'maxWeight' => 30,
            ),
            233 => array(
                'code' => 233,
                'name' => 'Экономичная посылка дверь-склад',
                'mode' => 2,
                'minWeight' => 0.1,
                'maxWeight' => 50,
            ),
            234 => array(
                'code' => 234,
                'name' => 'Экономичная посылка дверь-дверь',
                'mode' => 1,
                'minWeight' => 0.1,
                'maxWeight' => 50,
            ),

        );

        $this->services = array(
            2  => 'СТРАХОВАНИЕ',
            7  => 'ОПАСНЫЙ ГРУЗ',
            16 => 'ЗАБОР В ГОРОДЕ ОТПРАВИТЕЛЕ',
            17 => 'ДОСТАВКА В ГОРОДЕ ПОЛУЧАТЕЛЕ',
            24 => 'УПАКОВКА 1',
            25 => 'УПАКОВКА 2',
            30 => 'ПРИМЕРКА НА ДОМУ',
            31 => 'ДОСТАВКА ЛИЧНО В РУКИ',
            32 => 'СКАН ДОКУМЕНТОВ',
            36 => 'ЧАСТИЧНАЯ ДОСТАВКА',
            37 => 'ОСМОТР ВЛОЖЕНИЯ',
        );
    }

    public function get($var, $default=null){
        return (isset($this->$var)) ? $this->$var : $default;
    }

    public static function getInstance() {
        if (!is_object(self::$_instance)) {
            self::$_instance = new Cdek();
        }
        return self::$_instance;
    }

    public function calculatePrice($debug=false, &$note=array())
    {
        if(!isset($this->methods[$this->code])){
            $this->error = 'Tarif '.$this->code.' not isset';
            return false;
        }

        $method = $this->methods[$this->code];

        if($this->weight > $method['maxWeight'] || $this->weight < $method['minWeight']){
            $this->error[] = 'Weight does not correspond to the send method "'.$method['name'].'"';
            return 0;
        }
        $url = $this->apiUrl;
        $json = $this->buildJsonQuery();

        if($debug){
            $note[] = 'URL: '.$url;
            $note[] = 'JSON: '.$json;
        }

        if(!empty($url)){
            $data = $this->openHttp($url, $json);
        }
        else{
            return 0;
        }
        if($debug){
            $note[] = 'API Response: '. json_encode($data);
        }
        if(isset($data["result"])){
            return $data["result"]['price'];
        }
        else if(isset($data["error"])){
            if ($debug){
                $this->error = $data["error"][0];
            }
            $this->alerts = "Доставка СДЕК недоступна";
        }
        else{
            $this->error = 'API error';
        }
        return false;
    }

    private function buildJsonQuery(){
        $params = array(
            'version' => $this->version,
            'dateExecute' => $this->dateExecute,
            'senderCityPostCode' => $this->senderCityPostCode,
            'receiverCityPostCode' => $this->receiverCityPostCode,
            'tariffId' => $this->code,
            'modeId' => $this->methods[$this->code]['mode'],
            'goods' => array(
                array(
                    'weight' => $this->weight,
                    'volume' => "0.1",
                )
            ),
        );
        if (($this->authLogin) && ($this->authPassword)){
            $params['authLogin'] = $this->authLogin;
            $params['secure'] = md5($this->dateExecute . "&" . $this->authPassword);
        }
        return json_encode($params);
    }

    private function openHttp($url, $json)
    {
        if (!function_exists('curl_init')) {
            die('ERROR: CURL library not found!');
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");

        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch,  CURLOPT_HTTPHEADER, array(
            'Cache-Control: no-store, no-cache, must-revalidate',
            'Content-Type: application/json',
            "Expires: " . date("r")
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result, true);
    }
}