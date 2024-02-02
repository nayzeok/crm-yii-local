<?php

namespace common\models;

use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * OrderSearch represents the model behind the search form of `common\models\Order`.
 */
class OrderSearch extends Order
{
    /**
     * @var mixed|null
     */

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['id', 'lead_product_id', 'status', 'foreign_id', 'current_queue_id', 'current_operator_id'], 'integer'],
            [[ 'created_at', 'blocked_at', 'update_at', 'delete_at', 'response_data', 'lead_site', 'lead_web_id', 'lead_partner_id'], 'safe'],
            [['total_price', 'lead_price', 'lead_revenue'], 'number'],
            ['status', 'in', 'range' => array_keys(self::STATUS_LABELS)],
            [['current_queue_id'], 'exist', 'skipOnError' => true, 'targetClass' => Queue::className(), 'targetAttribute' => ['current_queue_id' => 'id']],
            [['current_operator_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::className(), 'targetAttribute' => ['current_operator_id' => 'id']],
            [['lead_product_id'], 'exist', 'skipOnError' => true, 'targetClass' => Product::className(), 'targetAttribute' => ['lead_product_id' => 'id']]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function scenarios(): array
    {
        // bypass scenarios() implementation in the parent class
        return Model::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     * @throws InvalidConfigException
     */
    public function search(array $params): ActiveDataProvider
    {
        $query = Order::find();

        $customerInfo = $this->customerInfo ?? new CustomerInfo();
        $customerInfo->load($params);
        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'orders.id' => $this->id,
            'total_price' => $this->total_price,
            'status' => $this->status,
            'foreign_id' => $this->foreign_id,
            'current_queue_id' => $this->current_queue_id,
            'current_operator_id' => $this->current_operator_id,
            'update_at' => $this->update_at,
            'delete_at' => $this->delete_at,
            'lead_product_id' => $this->lead_product_id,
            'lead_price' => $this->lead_price,
            'lead_revenue' => $this->lead_revenue,
        ]);

        //TODO починить поиск имени и телефона
        $query->joinWith('customerInfo')
            ->andFilterWhere(['like', 'customers_info.name', $customerInfo->name])
            ->andFilterWhere(['like', 'customers_info.phone', $customerInfo->phone]);

        $query->andFilterWhere(['like', 'response_data', $this->response_data])
            ->andFilterWhere(['like', 'lead_site', $this->lead_site])
            ->andFilterWhere(['like', 'lead_web_id', $this->lead_web_id])
            ->andFilterWhere(['like', 'lead_partner_id', $this->lead_partner_id]);

        $this->filterDate($query, 'orders.created_at', $this->created_at);
        $this->filterDate($query, 'orders.blocked_at', $this->blocked_at);

        return $dataProvider;
    }

    /**
     * Filters query by date using various formats (YYYY, YYYY-MM, YYYY-MM-DD).
     *
     * @param \yii\db\ActiveQuery $query     Query to apply filter to.
     * @param string              $attribute Date attribute.
     * @param string              $value     Date value for filtering.
     */
    private function filterDate($query, $attribute, $value): void
    {
        if (!empty($value)) {
            if (preg_match('/^\d{4}$/', $value)) {
                // Фильтрация по году
                $query->andFilterWhere(['YEAR(' . $attribute . ')' => $value]);
            } elseif (preg_match('/^\d{4}-\d{2}$/', $value)) {
                // Фильтрация по году и месяцу
                $query->andFilterWhere(['like', 'DATE_FORMAT(' . $attribute . ', "%Y-%m")', $value]);
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                // Фильтрация по конкретной дате
                $query->andFilterWhere(['like', 'DATE_FORMAT(' . $attribute . ', "%Y-%m-%d")', $value]);
            }
        }
    }
}