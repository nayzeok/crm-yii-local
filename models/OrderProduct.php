<?php

namespace common\models;

use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "orders_products".
 *
 * @property int $id
 * @property int $order_id
 * @property int $product_id
 * @property int $quantity
 * @property bool $product_type
 * @property float $price_for_one
 * @property float $total_price
 * @property int|null $promotion_id
 * @property string $created_at
 * @property string $updated_at
 * @property string|null $deleted_at
 *
 * @property Product $product
 */
class OrderProduct extends ActiveRecord
{
    const TYPE_PAID = 1;
    const TYPE_FREE = 2;
    const TYPE_DISCOUNT = 3;
    const TYPE_GIFTS = 4;

    const TYPE_LABELS = [
        1 => 'Paid',
        2 => 'Free',
        3 => 'Discount',
        4 => 'Gift'
    ];

    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return 'orders_products';
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['order_id', 'product_id'], 'required'],
            [['order_id', 'product_id', 'product_type'], 'integer'],
            [['price_for_one', 'total_price'], 'number'],
            [['quantity'], 'integer', 'min' => 1],
            [['created_at', 'updated_at', 'deleted_at'], 'safe'],
            [['order_id'], 'exist', 'skipOnError' => true, 'targetClass' => Order::className(), 'targetAttribute' => ['order_id' => 'id']],
            [['product_id'], 'exist', 'skipOnError' => true, 'targetClass' => Product::className(), 'targetAttribute' => ['product_id' => 'id']],
            [['promotion_id'], 'exist', 'skipOnError' => true, 'targetClass' => Promotion::className(), 'targetAttribute' => ['promotion_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels(): array
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'order_id' => Yii::t('app', 'Order'),
            'product_id' => Yii::t('app', 'Product'),
            'quantity' => Yii::t('app', 'Quantity'),
            'product_type' => Yii::t('app', 'Product type'),
            'price_for_one' => Yii::t('app', 'Price For One'),
            'total_price' => Yii::t('app', 'Total Price'),
            'promotion_id' => Yii::t('app', 'Promotion'),
            'created_at' => Yii::t('app', 'Created At'),
            'updated_at' => Yii::t('app', 'Updated At'),
            'deleted_at' => Yii::t('app', 'Deleted At'),
        ];
    }

    /**
     * @param $insert
     * @return bool
     */
    public function beforeSave($insert): bool
    {
        //TODO заполнять price_by_one и total_price
        return parent::beforeSave($insert);
    }

    /**
     * @return bool
     */
    public function isGift(): bool
    {
        return !empty($this->product_type == self::TYPE_GIFTS);
    }

    /**
     * @return bool
     */
    public function isPromotionGift(): bool
    {
        return !empty($this->promotion_id) && $this->product_type == self::TYPE_GIFTS;
    }

    /**
     * Gets query for [[Product]].
     *
     * @return ActiveQuery
     */
    public function getProduct(): ActiveQuery
    {
        return $this->hasOne(Product::className(), ['id' => 'product_id']);
    }

    /**
     * Gets query for [[Promotion]].
     *
     * @return ActiveQuery
     */
    public function getPromotion(): ActiveQuery
    {
        return $this->hasOne(Promotion::className(), ['id' => 'promotion_id']);
    }

    /**
     * @return array
     */
    public static function getProductPrices(): array
    {
        return ArrayHelper::map(Product::find()->asArray()->all(), 'id', 'price');
    }
}
