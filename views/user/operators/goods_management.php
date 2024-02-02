<?php

use kartik\select2\Select2;
use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\helpers\ArrayHelper;
use yii\widgets\DetailView;
use common\models\Product;
use common\models\Order;

/* @var $this yii\web\View */
/* @var $user common\models\User */
/* @var $order Order */
/* @var $form yii\widgets\ActiveForm */

$productList = ArrayHelper::map(Product::find()->all(), 'id', 'name');
$this->title = 'Goods Management for user: ' . $user->username;
$this->params['breadcrumbs'][] = ['label' => Yii::t('app', 'Operators'), 'url' => ['operators']];
$this->params['breadcrumbs'][] = $this->title;

$form = ActiveForm::begin([
    'id' => 'queues-management-form',
    'options' => ['class' => 'form-horizontal'],
]);

// Отображение текущего пользователя
echo DetailView::widget([
    'model' => $user,
    'attributes' => [
        'id',
        'username'
    ]
]);

// Мультиселект для продуктов
echo $form->field($user, 'products')->widget(Select2::classname(), [
    'data' => $productList,
    'language' => 'ru',
    'options' => ['placeholder' => 'Выберите продукты для пользователя...', 'multiple' => true],
    'pluginOptions' => [
        'allowClear' => true,
        'tags' => true,
    ],
]);

echo Html::submitButton('Save Products', ['class' => 'btn btn-success', 'style' => 'margin-top: 10px; margin-right: 5px;']);

ActiveForm::end();
?>
