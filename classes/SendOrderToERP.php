<?php

namespace common\classes;

use yii\base\Controller;
use yii;
class SendOrderToERP {

    public function GetToken()
    {
        $client = new Client();
        $response = $client->createRequest()
            ->setMethod('POST')
            ->setUrl('https://www.barelita.com/ApiRest/auth')
            ->setHeaders(['Content-Type' => 'application/json'])
            ->setContent(json_encode([
                'usuario' => 'user_api',
                'password' => '123456',
            ]))
            ->send();

        if ($response->isOk) {
            $responseData = json_decode($response->content, true);
            print_r($responseData);
            if (isset($responseData['result']) && isset($responseData['result']['token'])) {
                return $responseData['result']['token'];
            }
        }
        return null;
    }

    public function sendOrderToApi($orderData)
    {
        $client = new Client();

        $token = $this->GetToken();
        if ($token) {
            $response = $client->createRequest()
                ->setMethod('POST')
                ->setUrl('http://www.barelita.com/ApiRest/orders')
                ->setHeaders(['Content-Type' => 'application/json'])
                ->setContent(json_encode([
                    "token" => $token,
                    "customer_name" => $orderData['name'],
                    "total_price" => $orderData['price'],
                    "status" => $orderData['status'],
                    "foreign_id" => $orderData['order_id'],
                    "customer_mobile" => $orderData['phone'],
                    "customer_email" => $orderData['email'] ?? '',
                    "customer_extra_phones" => "{}",
                    "address_info" => "{}",
                    "comment" => "",
                    "product_id" => $orderData['goods_id'],
                    "quantity" => $orderData['quantity'],
                    "is_gift" => 0,
                    "goods" => $orderData['goods_name'] ?? ''
                ]))
                ->send();

            if ($response->isOk) {
                $responseData = json_decode($response->content, true);

                //проверка на отправку через создание файла, для отладки

                $logfile = fopen("C:\logs\api_log.txt", "a");
                $logData = "Заказ {$orderData['order_id']} успешно отправлен в API.\n";
                fwrite($logfile, $logData);
                $result['success'] = true;
            } else {
                $result['error'] = 'Failed to send order to API.';
            }
        } else {
            $result['error'] = 'Failed to retrieve API token.';
        }
        return $result;
    }
    private function getOrderStatusFromApi($orderId)
    {
        $client = new Client();

        $token = $this->GetToken();
        if ($token) {
            $url = "http://www.barelita.com/ApiRest/orders/{$orderId}/status";

            $response = $client->request('GET', $url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => "Bearer {$token}"
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $responseData = json_decode($response->getBody(), true);
                $status = $responseData['status'] ?? null;
                return $status;
            } else {
                $result['error'] = 'Failed to get order status from API.';
            }
        } else {
            $result['error'] = 'Failed to retrieve API token.';
        }
        return $result;
    }
}