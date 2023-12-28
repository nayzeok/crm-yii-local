<?php

namespace common\classes;

use yii\base\InvalidConfigException;
use yii\httpclient\Client;
use yii;
use yii\httpclient\Exception;

//TODO сделать интерфейс для этого класса и оформить вызовы этого класса по DI
class BarelitaERPIntegration
{

    /**
     * @var string|null
     */
    private ?string $token;

    /**
     *
     */
    public function __construct()
    {
        $this->token = $this->getToken();
    }

    /**
     * Receiving a token
     *
     * @return string|null Token or null if token could not be obtained
     */
    public function getToken(): ?string
    {
        $client = new Client();
        $response = $client->createRequest()
            ->setMethod('POST')
            ->setUrl(Yii::$app->params['barelitaAuthUrl'])
            ->setHeaders(['Content-Type' => 'application/json'])
            ->setContent(json_encode([
                'usuario' => Yii::$app->params['barelitaUser'],
                'password' => Yii::$app->params['barelitaPassword'],
            ]))
            ->send();

        if ($response->isOk) {
            $responseData = json_decode($response->content, true);
            if (isset($responseData['result']) && isset($responseData['result']['token'])) {
                return $responseData['result']['token'];
            }
        }
        return null;
    }

    /**
     * Sends the order to the API.
     *
     * @param array $orderData Array of order data
     * @return array
     * @throws Exception
     * @throws InvalidConfigException
     */
    //TODO Переделать на отправку модели ордера сюда, а в модели функцию sendToErp() обертку для этой функции и подключать по DI через интерфейс
    public function sendOrderToApi(array $orderData): array
    {
        $client = new Client();

        if (empty($this->token)) {
            return ['error' => 'Failed to retrieve API token.'];
        }

        // Экранирование специальных символов в данных заказа перед их кодированием в JSON
        array_walk_recursive($orderData, function (&$item) {
            if (is_string($item)) {
                $item = addslashes($item);
            }
        });

        $bodyData = json_encode(array_merge(["token" => $this->token], $orderData));
        Yii::info($bodyData, 'orderInfo');

        $response = $client->createRequest()
            ->setMethod('POST')
            ->setUrl(Yii::$app->params['barelitaSendOrderUrl'])
            ->setHeaders(['Content-Type' => 'application/json'])
            ->setContent($bodyData)
            ->send();

        if ($response->isOk) {
            $responseData = json_decode($response->content, true);
            $erpOrderID = $responseData['result']['id'] ?? null;

            if ($erpOrderID != null) {
                Yii::info("Заказ {$orderData['order_id']} успешно отправлен в ERP. ID от ERP: {$erpOrderID}.", 'orderInfo');
                return [
                    'success' => true,
                    'id' => $erpOrderID,
                    'responseData' => $response->getData()
                ];
            }
            Yii::error("Не удалось получить ID от ERP после успешного добавления.", 'orderInfo');
        }
        $errorMessage = "$response->statusCode: Failed to send order to ERP.";
        Yii::error($errorMessage, 'orderInfo');
        return ['error' => $errorMessage, 'responseData' => $response->getData()];
    }

    /**
     * Gets order status from API by order ID.
     *
     * @param int $orderId Order ID.
     * @return array
     * @throws InvalidConfigException
     * @throws Exception
     */
    public function getOrderStatusFromAPI(int $orderId): array
    {

        if (empty($this->token)) {
            return ['error' => 'Failed to retrieve API token.'];
        }

        $url = str_replace('#orderId#', $orderId, Yii::$app->params['barelitaOrderStatusUrl']);
        $client = new Client();

        $response = $client->createRequest()
            ->setMethod('GET')
            ->setUrl($url)
            ->setHeaders(
                [
                    'Content-Type' => 'application/json',
                    'Authorization' => "Bearer {$this->token}"
                ])
            ->send();

        if ($response->isOk) {
            $responseData = json_decode($response->content, true);
            $status = $responseData['status'] ?? null;

            return [
                'success' => true,
                'status' => $status,
                'responseData' => $response->getData()
            ];
        }

        return ['error' => 'Failed to get order status from API.'];
    }
}