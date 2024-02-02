<?php

namespace common\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\User;

/**
 * UserSearch represents the model behind the search form of `common\models\User`.
 */
class UserSearch extends User
{
    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['id', 'status', 'updated_at'], 'integer'],
            [['username', 'email'], 'string', 'max' => 255],
            [['created_at'], 'safe']
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
     */
    public function search(array $params): ActiveDataProvider
    {
        $query = User::find();

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
            'id' => $this->id,
            'status' => $this->status,
            'updated_at' => $this->updated_at,
        ]);

        $query->andFilterWhere(['like', 'username', $this->username])
            ->andFilterWhere(['like', 'email', $this->email]);

        if (!empty($this->created_at)) {
            if (preg_match('/^\d{4}$/', $this->created_at)) {
                // Фильтрация по году
                $query->andFilterWhere(['YEAR(FROM_UNIXTIME(created_at))' => $this->created_at]);
            } elseif (preg_match('/^\d{4}-\d{2}$/', $this->created_at)) {
                // Фильтрация по году и месяцу
                $query->andFilterWhere(['like', 'DATE_FORMAT(FROM_UNIXTIME(created_at), "%Y-%m")', $this->created_at]);
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->created_at)) {
                // Фильтрация по конкретной дате
                $query->andFilterWhere(['like', 'DATE_FORMAT(FROM_UNIXTIME(created_at), "%Y-%m-%d")', $this->created_at]);
            }
        }

        return $dataProvider;
    }
}
