<?php

namespace common\models;

use common\classes\SendOrderToERP;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\db\Expression;
use yii\db\StaleObjectException;
use yii\base\Request;
use common\classes\BarelitaERPIntegration;
use yii\validators\Validator;

/**
 * This is the model class for table "orders".
 *
 * @property int $id
 * @property string|null $address_info
 * @property string|null $comment
 * @property float|null $total_price
 * @property int $lead_product_id
 * @property int|null $current_queue_id
 * @property int|null $current_operator_id
 * @property int $status
 * @property int $reject_reason
 * @property int|null $foreign_id
 * @property string $created_at
 * @property string|null $blocked_at
 * @property string $update_at
 * @property string|null $delete_at
 * @property string|null $response_data
 * @property float|null $lead_price
 * @property float|null $lead_revenue
 * @property string|null $lead_site
 * @property string|null $lead_web_id
 * @property string|null $campaign_id
 * @property string|null $lead_partner_id
 * @property string|null $datepicker
 *
 * @property Queue $currentQueue
 * @property User $currentOperator
 * @property Product $product
 * @property CustomerInfo $customerInfo
 * @property OrderDetail $orderDetail
 * @property Attempt[] $attempts
 * @property Attempt $lastAttempt
 * @property int $countAttempts
 * @property int $countCurrentQueueAttempts
 * @property Product[] $allProducts
 * @property Product[] $goods
 * @property Product[] $gifts
 * @property OrderProduct[] $orderGoods
 * @property OrderProduct[] $orderGifts
 * @property array $preparedAPIData
 * @property Promotion $promotion
 *
 * //TODO Вынести lead info в отдельную таблицу по аналогии с customerInfo и orderDetail
 * //TODO сделать акции
 */
class Order extends ActiveRecord
{
    const STATUS_NEW = 1;
    const STATUS_APPROVED = 2;
    const STATUS_REJECT = 3;
        const REJECT_SUB_STATUS_PRODUCT_IS_TOO_EXPENSIVE = 1;
        const REJECT_SUB_STATUS_CHANGED_MIND = 2;
        const REJECT_SUB_STATUS_MEDICAL_CONTRAINDICATIONS = 3;
        const REJECT_SUB_STATUS_ORDERED_NO_COMMENTS = 4;
        const REJECT_SUB_STATUS_PRODUCT_IS_UNKNOWN = 5;
        const REJECT_SUB_STATUS_ALREADY_HAVE_BOUGHT_IT_AT_ANOTHER_SHOP = 6;
        const REJECT_SUB_STATUS_NEGATIVE_FEEDBACK_FROM_INTERNET_FRIEND = 7;
        const REJECT_SUB_STATUS_PERSONAL_REASON_DOES_NOT_WANT_TO_DISCUSS = 8;
        const REJECT_SUB_STATUS_CANNOT_AFFORD_IT = 9;
        const REJECT_SUB_STATUS_DELIVERED = 10;
        const REJECT_SUB_STATUS_REJECT = 11;
        const REJECT_SUB_STATUS_INQUIRY = 12;
        const REJECT_SUB_STATUS_OUT_OF_DELIVERY_ZONE = 13;
        const REJECT_SUB_STATUS_DELIVERY_DATES_VIOLATION = 14;
        const REJECT_SUB_STATUS_AUTO_REJECT = 15;
        const REJECT_SUB_STATUS_LOST_CONNECTION = 16;
    const STATUS_RECALL = 4;
    const STATUS_NO_ANSWER = 5;
    const STATUS_TRASH = 6;
    const STATUS_PENDING = 7;
    const STATUS_IN_PROCESS = 8;
    const STATUS_UNDELIVERED = 9;
    const STATUS_DELIVERED = 10;
    const STATUS_RETURNED = 11;
    const STATUS_FINANCE_MONEY_RECEIVED = 12;

    const STATUS_LABELS = [
        1 => 'New',
        2 => 'Approved',
        3 => 'Reject',
        4 => 'Recall',
        5 => 'No answer',
        6 => 'Trash',
        7 => 'Pending',
        8 => 'In process',
        9 => 'Undelivered',
        10 => 'Delivered',
        11 => 'Returned',
        12 => 'Finance money received',
    ];
    const REJECT_SUB_STATUS_LABELS = [
        1 => "Product is too expensive",
        2 => 'Changed mind',
        3 => 'Medical Contraindications',
        4 => 'No comments',
        5 => 'Product is unknown',
        6 => 'Already have bought it at another shop',
        7 => 'Negative feedback from internet friend',
        8 => 'Personal reason, does not want to discuss',
        9 => 'Cannot afford it ',
        10 => 'Delivered',
        11 => 'Reject',
        12 => 'Inquiry',
        13 => 'Out of delivery zone',
        14 => 'Delivery dates violation',
        15 => 'Auto reject',
    ];


    /**
     * @var bool
     */
    private bool $fromCallForm = false;

    /**
     * @return bool
     */
    public function getFromCallForm(): bool
    {
        return $this->fromCallForm;
    }

    /**
     * @var array|null
     */
    private ?array $preparedAPIData = null;

    /**
     * @return string
     */
    public static function tableName(): string
    {
        return 'orders';
    }

    /**
     * @param $insert
     * @return bool
     */
    public function beforeSave($insert): bool
    {
        if ($this->fromCallForm) {

            $this->link('queues', $this->currentQueue,
                [
                    'is_first_queue_attempt' => $this->countCurrentQueueAttempts == 0 ? 1 : null,
                    'user_id' => Yii::$app->user->id
                ]
            );
            $this->current_operator_id = null;
        }

        $this->update_at = date('Y-m-d H:i:s', time());

        return parent::beforeSave($insert);
    }

    /**
     * @param $insert
     * @param $changedAttributes
     * @return void
     * @throws Exception
     * @throws StaleObjectException
     */
    public function afterSave($insert, $changedAttributes): void
    {
        parent::afterSave($insert, $changedAttributes);

        // Обработка связи с продуктом при создании нового объекта
        if ($insert && $this->product) {
            $this->link('goods', $this->product,
                ['quantity' => 1,
                    'price_for_one' => $this->lead_price,
                    'total_price' => $this->lead_price,]
            );
        }

        // Обработка логики вызова формы и вставки
        if ($this->fromCallForm || $insert) {
            $this->fromCallForm = false;
            //при смене статуса на апрув, реджект, треш, но ансвер убирать очередь?
            if (
                isset($changedAttributes['status'])
                && ($changedAttributes['status'] != $this->status)
                && in_array($this->status, [
                    //статусы финального флоу колл-центра
                    Order::STATUS_APPROVED,
                    Order::STATUS_REJECT,
                    Order::STATUS_TRASH,
                    //статусы доставки
                    Order::STATUS_PENDING,
                    Order::STATUS_IN_PROCESS,
                    Order::STATUS_UNDELIVERED,
                    Order::STATUS_DELIVERED,
                    Order::STATUS_RETURNED,
                    Order::STATUS_FINANCE_MONEY_RECEIVED
                ])
                && isset($this->currentQueue)
            ) {
                $this->unlink('currentQueue', $this->currentQueue);
            }

            // Обработка триггеров по смене статуса
            if ($insert || (isset($changedAttributes['status']) && $changedAttributes['status'] != $this->status)) {
                $triggers = Trigger::find()
                    ->where([
                        'trigger_type' => [Trigger::TRIGGER_TYPE_LEAD, Trigger::TRIGGER_TYPE_LEAD_WITH_WEB_ID_OR_SITE],
                        'entity' => Trigger::ACTION_TYPE_STATUS,
                        'entity_id' => $this->status
                    ])
                    ->all();

                foreach ($triggers as $trigger) {
                    if ($trigger->trigger_type == Trigger::TRIGGER_TYPE_LEAD_WITH_WEB_ID_OR_SITE) {
                        $matchesTrigger = false;

                        // Проверяем соответствие каждого условия
                        if (!empty($this->lead_web_id) && trim($this->lead_web_id) == trim($trigger->lead_web_id)) {
                            $matchesTrigger = true;
                        }
                        if (!empty($this->lead_site) && trim($this->lead_site) == trim($trigger->lead_site)) {
                            $matchesTrigger = true;
                        }
                        if (!empty($this->campaign_id) && trim($this->campaign_id) == trim($trigger->campaign_id)) {
                            $matchesTrigger = true;
                        }

                        if ($matchesTrigger) {
                            $this->blocked_at = null;
                            $this->link('currentQueue', $trigger->queue);
                        }
                    } else if ($trigger->trigger_type == Trigger::TRIGGER_TYPE_LEAD) {
                        // Логика для обычного триггера
                        if ($this->status != self::STATUS_RECALL) {
                            $this->blocked_at = null;
                        }
                        $this->link('currentQueue', $trigger->queue);
                    }
                }
            }


        // Обработка логики очереди и попыток
        if ($this->currentQueue && ($this->countCurrentQueueAttempts >= $this->currentQueue->attempts)) {
            $triggers = Trigger::find()
                ->where([
                    'trigger_type' => Trigger::TRIGGER_TYPE_QUEUE,
                    'queue_id' => $this->current_queue_id
                ])
                ->all();

            foreach ($triggers as $trigger) {
                switch ($trigger->entity) {
                    case Trigger::ACTION_TYPE_QUEUE:
                        if ($trigger->entity_id != $this->current_queue_id) {
                            $this->current_queue_id = $trigger->entity_id;
                            $this->save(true, ['current_queue_id']);
                        }
                        break;
                    case Trigger::ACTION_TYPE_STATUS:
                        if ($trigger->entity_id != $this->status) {
                            $this->status = $trigger->entity_id;
                            $this->save(false, ['status']);
                            $this->unlink('currentQueue', $this->currentQueue);
                        }
                        break;
                }
            }
        }
        }

        // Обработка изменений в текущей очереди
        if (isset($changedAttributes['current_queue_id']) && $changedAttributes['current_queue_id'] != $this->current_queue_id) {
            Attempt::updateAll(['deleted_at' => new Expression('NOW()')], ['order_id' => $this->id, 'deleted_at' => null]);
        }
    }

    /**
     * Sends the order to the ERP system.
     *
     * The method checks if the current order is new and approved, or if the order status has been changed to approved.
     * If so, the method logs information about the sending process, then uses the integration with the ERP system
     * to send the order data through the API. Upon successful dispatch, the order status is updated to 'pending'
     * and is saved. If an error occurs during sending or saving, a corresponding message is logged
     * about the error and a corresponding flash message is set in the user session.
     */
    public function sendToERP(): void
    {
        Yii::info("Sending the order to ERP", 'orderProcess');
        $orderApi = new BarelitaERPIntegration();
        $response = $orderApi->sendOrderToApi($this->getPreparedAPIData());

        if (isset($response['success']) && $response['success']) {
            // Заказ успешно отправлен в ERP
            $this->status = Order::STATUS_PENDING;
            if (!$this->save(true, ['status'])) {
                Yii::$app->session->setFlash('error', 'Error when saving order status.');
            } else {
                Yii::info("Order successfully sent to ERP and status changed to pending", 'orderProcess');
            }
        } else {
            // Обработка ошибок отправки в ERP
            $error = $response['error'] ?? 'Unknown error when sending an order to ERP';
            Yii::$app->session->setFlash('error', $error);
        }
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            [['status'], 'required'],
            [['total_price', 'lead_price', 'lead_revenue'], 'number'],
            [['lead_product_id', 'status', 'foreign_id', 'current_queue_id', 'current_operator_id'], 'integer'],
            [['created_at', 'update_at', 'delete_at', 'blocked_at', 'lead_web_id'], 'safe'],
            [['lead_site', 'lead_partner_id'], 'string', 'max' => 255],
            [['response_data'], 'string', 'max' => 3000],
            ['status', 'in', 'range' => array_keys(self::STATUS_LABELS)],
            [['reject_reason'], 'in', 'range' => array_keys(self::REJECT_SUB_STATUS_LABELS)],
            [['current_queue_id'], 'exist', 'skipOnError' => true, 'targetClass' => Queue::className(), 'targetAttribute' => ['current_queue_id' => 'id']],
            [['current_operator_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::className(), 'targetAttribute' => ['current_operator_id' => 'id']],
            [['lead_product_id'], 'exist', 'skipOnError' => true, 'targetClass' => Product::className(), 'targetAttribute' => ['lead_product_id' => 'id']],
            [['status'], 'validateStatus'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels(): array
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'total_price' => Yii::t('app', 'Total Price'),
            'lead_product_id' => Yii::t('app', 'Lead Product'),
            'status' => Yii::t('app', 'Status'),
            'current_queue_id' => Yii::t('app', 'Current Queue'),
            'current_operator_id' => Yii::t('app', 'Current Operator'),
            'foreign_id' => Yii::t('app', 'Foreign ID'),
            'created_at' => Yii::t('app', 'Created At'),
            'blocked_at' => Yii::t('app', 'Blocked At'),
            'update_at' => Yii::t('app', 'Update At'),
            'delete_at' => Yii::t('app', 'Delete At'),
            'response_data' => Yii::t('app', 'Response Data'),
            'lead_price' => Yii::t('app', 'Lead Price'),
            'lead_revenue' => Yii::t('app', 'Lead Revenue'),
            'lead_site' => Yii::t('app', 'Lead Site'),
            'lead_web_id' => Yii::t('app', 'Lead Web ID'),
            'lead_partner_id' => Yii::t('app', 'Lead Partner ID'),
            'sex' => Yii::t('app', 'Sex'),
            'reject_reason' => Yii::t('app', 'Reject Reason'),
        ];
    }

    /**
     * Checks if there are any paid items in the order.
     * Iterates through the order goods to check if any item has a price greater than zero.
     *
     * @return bool Returns true if there are paid items, false otherwise.
     */
    public function hasPaidItems(): bool
    {
        foreach ($this->orderGoods as $orderProduct) {
            if ($orderProduct->total_price > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the list of allowed statuses based on the user's role.
     *
     * @param string $role The user's role.
     * @return array An array of allowed statuses.
     */
    public function getAllowedStatusesByRole($role): array
    {
        $statusMap = [
            User::ROLE_OPERATOR => [
                Order::STATUS_APPROVED,
                Order::STATUS_RECALL,
                Order::STATUS_REJECT,
                Order::STATUS_TRASH,
                Order::STATUS_NO_ANSWER,
                Order::STATUS_PENDING,
            ],
            User::ROLE_SUPERVISOR => [
                Order::STATUS_NEW,
                Order::STATUS_APPROVED,
                Order::STATUS_RECALL,
                Order::STATUS_REJECT,
                Order::STATUS_TRASH,
                Order::STATUS_NO_ANSWER,
                Order::STATUS_PENDING,
            ],
        ];

        return $statusMap[$role] ?? [];
    }

    /**
     * Validates the 'status' attribute for operator-specific allowed statuses.
     *
     * @param string $attribute The attribute currently being validated.
     * @param array|null $params Additional parameters (optional).
     * @param \yii\validators\Validator $validator The validator currently being used.
     * @return void This method does not return a value.
     */
    public function validateStatus($attribute, $validator, $params = []): void
    {
        $role = Yii::$app->user->identity->role;
        $allowedStatuses = $this->getAllowedStatusesByRole($role);

        if (!in_array($this->$attribute, $allowedStatuses)) {
            $this->addError($attribute, 'Unacceptable status for your role.');
        }
    }

    /**
     * Gets query for [[CurrentQueue]].
     *
     * @return ActiveQuery
     */
    public function getCurrentQueue(): ActiveQuery
    {
        return $this->hasOne(Queue::className(), ['id' => 'current_queue_id']);
    }

    /**
     * Gets query for [[CurrentOperator]].
     *
     * @return ActiveQuery
     */
    public function getCurrentOperator(): ActiveQuery
    {
        return $this->hasOne(User::className(), ['id' => 'current_operator_id']);
    }

    /**
     * Gets query for [[Product]].
     *
     * @return ActiveQuery
     */
    public function getProduct(): ActiveQuery
    {
        return $this->hasOne(Product::className(), ['id' => 'lead_product_id']);
    }

    /**
     * Gets query for [[Attempts]].
     *
     * @return ActiveQuery
     */
    public function getAttempts(): ActiveQuery
    {
        return $this->hasMany(Attempt::className(), ['order_id' => 'id']);
    }

    /**
     * Gets query for [[CountAttempts]].
     *
     * @return int
     */
    public function getCountAttempts(): int
    {
        return $this->hasMany(Attempt::className(), ['order_id' => 'id'])
            ->count();
    }

    /**
     * Gets query for [[CountCurrentQueueAttempts]].
     *
     * @return int
     */
    public function getCountCurrentQueueAttempts(): int
    {
        return $this->getCurrentQueueAttempts()
            ->count();
    }

    /**
     * Gets query for [[CurrentQueueAttempts]].
     *
     * @return ActiveQuery
     */
    public function getCurrentQueueAttempts(): ActiveQuery
    {
        return $this->hasMany(Attempt::className(), ['order_id' => 'id'])
            ->andWhere([
                Attempt::tableName() . '.deleted_at' => null,
            ]);
    }

    /**
     * Gets query for [[LastAttempt]].
     *
     * @return ActiveQuery
     */
    public function getLastAttempt(): ActiveQuery
    {
        return $this->hasOne(Attempt::className(), ['order_id' => 'id'])
            ->orderBy(Attempt::tableName() . '.created_at desc');
    }

    /**
     *
     * return ActiveQuery
     *
     * @throws InvalidConfigException
     */
    public function getQueues(): ActiveQuery
    {
        return $this->hasMany(Queue::classname(), ['id' => 'queue_id'])
            ->viaTable(Attempt::tableName(), ['order_id' => 'id']);
    }

    /**
     * @return Order
     */
    public function setFromCallForm(): Order
    {
        $this->fromCallForm = true;
        return $this;
    }

    /**
     * Gets query for [[CustomersInfo]].
     *
     * @return ActiveQuery
     */
    public function getCustomerInfo(): ActiveQuery
    {
        return $this->hasOne(CustomerInfo::className(), ['order_id' => 'id']);
    }

    /**
     * Gets query for [[OrdersInfo]].
     *
     * @return ActiveQuery
     */
    public function getOrderDetail(): ActiveQuery
    {
        return $this->hasOne(OrderDetail::className(), ['order_id' => 'id']);
    }

    /**
     * Gets query for [[Product]].
     *
     * @return ActiveQuery
     * @throws InvalidConfigException
     */
    public function getGoods(): ActiveQuery
    {
        return $this->hasMany(Product::classname(), ['id' => 'product_id'])
            ->viaTable(OrderProduct::tableName(), ['order_id' => 'id'], function ($query) {
                $query->andWhere([
                    OrderProduct::tableName() . '.product_type' => OrderProduct::TYPE_PAID,
                ]);
            });

    }

    /**
     * Gets query for [[Product]].
     *
     * @return ActiveQuery
     * @throws InvalidConfigException
     */
    public function getAllProducts(): ActiveQuery
    {
        return $this->hasMany(Product::classname(), ['id' => 'product_id'])
            ->viaTable(OrderProduct::tableName(), ['order_id' => 'id']);
    }

    /**
     * Gets query for [[Product]].
     *
     * @return ActiveQuery
     * @throws InvalidConfigException
     */
    public function getGifts(): ActiveQuery
    {
        return $this->hasMany(Product::classname(), ['id' => 'product_id'])
            ->viaTable(OrderProduct::tableName(), ['order_id' => 'id'], function ($query) {
                $query->andWhere([
                    OrderProduct::tableName() . '.product_type' => OrderProduct::TYPE_GIFTS,
                ]);
            });
    }

    /**
     * Gets query for [[OrderProduct]].
     *
     * @return ActiveQuery
     */
    public function getOrderGoods(): ActiveQuery
    {
        return $this->hasMany(OrderProduct::classname(), ['order_id' => 'id'])
            ->andWhere([
                OrderProduct::tableName() . '.product_type' => OrderProduct::TYPE_PAID,
            ]);
    }

    /**
     * Gets query for [[OrderProduct]].
     *
     * @return ActiveQuery
     */
    public function getAllOrderProducts(): ActiveQuery
    {
        return $this->hasMany(OrderProduct::classname(), ['order_id' => 'id']);
    }

    /**
     * Gets query for [[OrderProduct]].
     *
     * @return ActiveQuery
     */
    public function getOrderGifts(): ActiveQuery
    {
        return $this->hasMany(OrderProduct::classname(), ['order_id' => 'id'])
            ->andWhere([
                OrderProduct::tableName() . '.product_type' => OrderProduct::TYPE_GIFTS,
            ]);
    }

    /**
     * @return void
     */
    public function blockOrder(): void
    {
        //Вернул как было :)
        $this->blocked_at = date('Y-m-d H:i:s', time() + ((Yii::$app->params['block_order_by_operator'] ?? 30) * 60));
        $this->link('currentOperator', Yii::$app->user->identity);
    }

    /**
     * @param array $postOrderProducts
     * @return bool
     */
    public function saveProductsByRequest(array $postOrderProducts): bool
    {
        $this->unlinkAll('goods', true);
        $this->unlinkAll('gifts', true);

        foreach ($postOrderProducts as $postOrderProduct) {
            try {
                $orderProduct = new OrderProduct();
                $orderProduct->order_id = $this->id;

                if (!$orderProduct->load($postOrderProduct)) {
                    throw new Exception('Goods load error!');
                }

                if (!$orderProduct->save()) {
                    throw new Exception('Goods save error!! ' . json_encode($orderProduct->errors));
                }
            } catch (Exception $e) {
                Yii::$app->session->addFlash('error', $e->getMessage());
            }
        }

        try {
            if (count($postOrderProducts) != $this->getAllOrderProducts()->count()) {
                throw new Exception('Not all goods have been saved! ');
            }
        } catch (Exception|InvalidConfigException $e) {
            Yii::$app->session->addFlash('error', $e->getMessage());
            return false;
        }

        return true;
    }


    /**
     * @param Request $request
     * @return void
     * @throws Exception
     */
    public function saveByRequest(Request $request): void
    {
        try {
            $transaction = Yii::$app->db->beginTransaction();

            $customerInfo = $this->customerInfo ?? new CustomerInfo();
            $orderDetail = $this->orderDetail ?? new OrderDetail();

            if ($this->fromCallForm) {
                if (!isset($this->blocked_at)) {
                    $this->blocked_at = date('Y-m-d H:i:s', time() + (($this->currentQueue->interval ?? 0) * 60));
                }
            }

            if (!$this->load($request->post()) || !$this->save()) {
                throw new Exception('Order save error! ' . json_encode($this->errors));
            }

            if ($customerInfo->isNewRecord) {
                $customerInfo->order_id = $this->id;
            }
            if (!$customerInfo->load($request->post()) || !$customerInfo->save()) {
                throw new Exception('Customer info save error! ' . json_encode($customerInfo->errors));
            }

            if ($orderDetail->isNewRecord) {
                $orderDetail->order_id = $this->id;
            }

            $orderDetail->setAddressInfo(new AddressInfo($request->post('AddressInfo')));
            if (!$orderDetail->load($request->post()) || !$orderDetail->save()) {
                throw new Exception('Order detail save error! ' . json_encode($orderDetail->errors) . json_encode($orderDetail->attributes));
            }

            if (!$this->saveProductsByRequest($request->post('OrderProduct') ?? [])) {
                throw new Exception('Goods save error!');
            }

            $this->refresh();

            // Проверка наличия платных товаров
            if ($this->hasPaidItems()) {
                // Логика обработки, если в заказе есть платные товары
            } else {
                throw new Exception('No paid items in the order');
            }

            if ($this->status == self::STATUS_APPROVED) {
                $this->sendToERP();
            }

            $transaction->commit();
        } catch (Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * @return array|null
     */
    public function getPreparedAPIData(): ?array
    {
        return [
            "order_id" => $this->id,
            "customer_name" => $this->customerInfo->name,
            "total_price" => $this->total_price,
            "status" => $this->status,
            "foreign_id" => $this->foreign_id ?? null,
            "customer_mobile" => $this->customerInfo->phone,
            "customer_email" => $this->customerInfo->email,
            "customer_extra_phones" => $this->customerInfo->extra_phones,
            "address_info" => $this->orderDetail->address_info,
            "comment" => $this->orderDetail->comment,
            "goods" => array_map(function ($item) {
                return [
                    "product_id" => $item['product_id'],
                    "quantity" => $item['quantity'],
                    "total_price" => $item['total_price'],
                    "is_gift" => intval($item['product_type'] == 4)
                ];
            }, $this->allOrderProducts)
        ];
    }
}
