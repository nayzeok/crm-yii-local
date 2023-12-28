<?php

namespace common\models;

use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\JsonExpression;

/**
 * This is the model class for table "orders_detail".
 *
 * @property int $id
 * @property int $order_id
 * @property string|null $address_by_client
 * @property string|null $address_info
 * @property string|null $comment
 * @property string $created_at
 * @property string $updated_at
 * @property string|null $deleted_at
 *
 * @property Order $order
 * @property AddressInfo $addressInfo
 */
class OrderDetail extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return 'orders_detail';
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['order_id'], 'required'],
            [['order_id'], 'integer'],
            [['address_info', 'comment', 'created_at', 'updated_at', 'deleted_at'], 'safe'],
            [['address_info', 'comment'], 'string', 'max' => 65000],
            [['address_by_client'], 'string', 'max' => 255],
            [['order_id'], 'exist', 'skipOnError' => true, 'targetClass' => Order::className(), 'targetAttribute' => ['order_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels(): array
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'order_id' => Yii::t('app', 'Order ID'),
            'address_info' => Yii::t('app', 'Address Info'),
            'address_by_client' => Yii::t('app', 'Address sent by the client'),
            'comment' => Yii::t('app', 'Comment'),
            'created_at' => Yii::t('app', 'Created At'),
            'updated_at' => Yii::t('app', 'Updated At'),
            'deleted_at' => Yii::t('app', 'Deleted At'),
        ];
    }

    /**
     * @return AddressInfo
     */
    public function getAddressInfo(): AddressInfo
    {
        return $this->address_info ? new AddressInfo(json_decode($this->address_info)) : new AddressInfo();
    }

    /**
     * @param AddressInfo $array_address_info
     */
    public function setAddressInfo(AddressInfo $array_address_info): void
    {
        $this->address_info = json_encode($array_address_info->attributes);
    }

    /**
     * Gets query for [[Order]].
     *
     * @return ActiveQuery
     */
    public function getOrder(): ActiveQuery
    {
        return $this->hasOne(Order::className(), ['id' => 'order_id']);
    }
}
