<?php

namespace common\models;

use Yii;
use yii\base\InvalidConfigException;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "queues".
 *
 * @property int $id
 * @property string $name
 * @property int $priority
 * @property int $attempts
 * @property int $interval
 * @property string $created_at
 * @property string $update_at
 * @property string|null $delete_at
 *
 * @property Trigger[] $triggers
 */
class Queue extends ActiveRecord
{

    /**
     * @return string
     */
    public static function tableName(): string
    {
        return 'queues';
    }


    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            [['name', 'interval', 'priority', 'attempts'], 'required'],
            [['interval'], 'integer', 'min' => 1, 'max' => PHP_INT_MAX],
            [['created_at', 'update_at', 'delete_at'], 'safe'],
            [['name'], 'string', 'max' => 255],
            [['priority'], 'integer', 'max' => 127, 'min' => -127],
            [['attempts'], 'integer', 'max' => 127, 'min' => 1],
        ];
    }


    /**
     * @return array
     */
    public function attributeLabels(): array
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'name' => Yii::t('app', 'Name'),
            'priority' => Yii::t('app', 'Priority'),
            'attempts' => Yii::t('app', 'Attempts'),
            'interval' => Yii::t('app', 'Interval'),
            'triggers' => Yii::t('app', 'Triggers'),
            'created_at' => Yii::t('app', 'Created At'),
            'update_at' => Yii::t('app', 'Update At'),
            'delete_at' => Yii::t('app', 'Delete At'),
        ];
    }

    /**
     * Gets query for [[Trigger]].
     *
     * @return ActiveQuery
     */
    public function getTriggers(): ActiveQuery
    {
        return $this->hasMany(Trigger::className(), ['queue_id' => 'id']);
    }

    /**
     * Gets query for [[Trigger]].
     *
     * @return ActiveQuery
     */
    public function getOrders(): ActiveQuery
    {
        return $this->hasMany(Order::className(), ['current_queue_id' => 'id']);
    }

    /**
     *
     * return ActiveQuery
     *
     * @throws InvalidConfigException
     */
    public function getOperators(): ActiveQuery
    {
        return $this->hasMany(User::classname(), ['id' => 'user_id'])
            ->viaTable('user_queue', ['queue_id' => 'id']);
    }

    /**
     * Checks if there are orders in the queue.
     *
     * @return bool Returns true if there are orders in the queue.
     */
    public function hasOrders(): bool
    {
        return $this->getOrders()->exists();
    }

    /**
     * Returns the number of orders in this queue.
     *
     * @return int Number of orders in the queue.
     */
    public function getOrderCount(): int
    {
        return $this->getOrders()->count();
    }

}
