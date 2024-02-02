<?php

namespace common\models;

use Yii;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use yii\web\IdentityInterface;

/**
 * User model
 *
 * @property integer $id
 * @property string $username
 * @property string $password_hash
 * @property string $password_reset_token
 * @property string $verification_token
 * @property string $email
 * @property string $auth_key
 * @property integer $status
 * @property integer $created_at
 * @property integer $updated_at
 * @property string $password write-only password
 * @property string $role
 */
class User extends ActiveRecord implements IdentityInterface
{
    const STATUS_DELETED = 0;
    const STATUS_INACTIVE = 9;
    const STATUS_ACTIVE = 10;

    const STATUS_LABELS = [
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_INACTIVE => 'Inactive',
        self::STATUS_DELETED => 'Deleted'
    ];

    const ROLE_ADMIN = 'admin';
    const ROLE_SUPERVISOR = 'supervisor';
    const ROLE_OPERATOR = 'operator';

    const ROLE_LABELS = [
        self::ROLE_ADMIN => 'Admin',
        self::ROLE_OPERATOR => 'Operator',
        self::ROLE_SUPERVISOR => 'Supervisor',
    ];

    public $lead_web_id;

    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return '{{%user}}';
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors(): array
    {
        return [
            TimestampBehavior::class,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            ['status', 'default', 'value' => self::STATUS_INACTIVE],
            ['status', 'in', 'range' => array_keys(self::STATUS_LABELS)],
            [['username', 'email'], 'string', 'max' => 255],
            [['username', 'email', 'status'], 'required'],
            [['username'], 'unique', 'message' => 'This username has already been taken.'],
            [['email'], 'unique', 'message' => 'This e-mail has already been taken.'],
            [['email'], 'email'],
            [['lead_web_id'], 'safe'],
            ['role', 'string'],
            ['role', 'default', 'value' => self::ROLE_OPERATOR],
            ['role', 'in', 'range' => array_keys(self::ROLE_LABELS), 'message' => 'Role should be either "admin", "supervisor" or "operator".'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels(): array
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'status' => Yii::t('app', 'Status'),
            'username' => Yii::t('app', 'Username'),
            'email' => Yii::t('app', 'E-mail'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function findIdentity($id)
    {
        return static::findOne(['id' => $id, 'status' => self::STATUS_ACTIVE]);
    }

    /**
     * {@inheritdoc}
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        return static::findOne(['auth_key' => $token]);
    }

    /**
     * Finds user by username
     *
     * @param string $username
     * @return static|null
     */
    public static function findByUsername(string $username): ?User
    {
        return static::findOne(['username' => $username, 'status' => self::STATUS_ACTIVE]);
    }

    /**
     * Finds user by password reset token
     *
     * @param string $token password reset token
     * @return static|null
     */
    public static function findByPasswordResetToken(string $token): ?User
    {
        if (!static::isPasswordResetTokenValid($token)) {
            return null;
        }

        return static::findOne([
            'password_reset_token' => $token,
            'status' => self::STATUS_ACTIVE,
        ]);
    }

    /**
     * Finds user by verification email token
     *
     * @param string $token verify email token
     * @return static|null
     */
    public static function findByVerificationToken(string $token): ?User
    {
        return static::findOne([
            'verification_token' => $token,
            'status' => self::STATUS_INACTIVE
        ]);
    }

    /**
     * Finds out if password reset token is valid
     *
     * @param string $token password reset token
     * @return bool
     */
    public static function isPasswordResetTokenValid(string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        $timestamp = (int)substr($token, strrpos($token, '_') + 1);
        $expire = Yii::$app->params['user.passwordResetTokenExpire'];
        return $timestamp + $expire >= time();
    }

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return $this->getPrimaryKey();
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthKey(): ?string
    {
        return $this->auth_key;
    }

    /**
     * {@inheritdoc}
     */
    public function validateAuthKey($authKey): ?bool
    {
        return $this->getAuthKey() === $authKey;
    }

    /**
     * Validates password
     *
     * @param string $password password to validate
     * @return bool if password provided is valid for current user
     */
    public function validatePassword(string $password): bool
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    /**
     * Generates password hash from password and sets it to the model
     *
     * @param string $password
     * @return User
     * @throws Exception
     */
    public function setPassword(string $password): User
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
        return $this;
    }

    /**
     * Generates "remember me" authentication key
     * @return User
     * @throws Exception
     */
    public function generateAuthKey(): User
    {
        $this->auth_key = Yii::$app->security->generateRandomString();
        return $this;
    }

    /**
     * Generates new password reset token
     */
    public function generatePasswordResetToken(): void
    {
        $this->password_reset_token = Yii::$app->security->generateRandomString() . '_' . time();
    }

    /**
     * Generates new token for email verification
     */
    public function generateEmailVerificationToken(): static
    {
        $this->verification_token = Yii::$app->security->generateRandomString() . '_' . time();
        return $this;
    }

    /**
     * Removes password reset token
     */
    public function removePasswordResetToken()
    {
        $this->password_reset_token = null;
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
            ->viaTable('user_queue', ['user_id' => 'id']);
    }

    /**
     * Gets query for [[Products]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getProducts(): ActiveQuery
    {
        return $this->hasMany(Product::className(), ['id' => 'product_id'])
            ->viaTable('user_product', ['user_id' => 'id']);
    }

    /**
     * Returns the user's associated `lead_web_id` from the `triggers` table.
     *
     * @return ActiveQuery
     */
    public function getLeadWebIds(): ActiveQuery
    {
        return $this->hasMany(Trigger::className(), ['id' => 'web_id'])
            ->viaTable('user_web_id', ['user_id' => 'id']);
    }

    /**
     * Deleting a user, setting the status to `STATUS_DELETED`.
     * @return bool If the user is successfully "deleted" (status changed).
     */
    public function delete(): bool
    {
        $this->status = self::STATUS_DELETED;
        return $this->save();
    }

    /**
     * @return Order|null
     */
    public function getAvailableOrder(): ?Order
    {
        $userId = Yii::$app->user->id;
        $queueIds = ArrayHelper::map($this->queues, 'id', 'id');

        return Order::find()
            ->joinWith(['currentQueue', 'orderGoods'])
            ->innerJoin('user_product', 'user_product.product_id = orders_products.product_id AND user_product.user_id = :userId', [':userId' => $userId])
            ->innerJoin('user_web_id', 'user_web_id.web_id = orders.lead_web_id AND user_web_id.user_id = :userId', [':userId' => $userId])
            ->andWhere([
                'and',
                ['current_operator_id' => null],
                [
                    'or',
                    ['<=', 'blocked_at', new Expression('NOW()')],
                    ['blocked_at' => null]
                ],
                ['status' => [Order::STATUS_NEW, Order::STATUS_NO_ANSWER, Order::STATUS_RECALL]]
            ])
            ->orWhere(['current_operator_id' => $userId])
            ->andWhere(['current_queue_id' => $queueIds])
            ->orderBy(['queues.priority' => SORT_DESC, 'orders.created_at' => SORT_ASC])
            ->one();
    }
}
