<?php

use kartik\select2\Select2;
use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\helpers\ArrayHelper;
use common\models\Queue;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $user common\models\User */
/* @var $form yii\widgets\ActiveForm */

$queueList = ArrayHelper::map(Queue::find()->all(), 'id', 'name');
$this->title = 'Queues Management for user: ' . $user->username;
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

// Мультиселект для очередей
echo $form->field($user, 'queues' ,[
    'options' => [
        'class' => 'form-group',
        'style' => 'margin-top: 20px',
]
    ])->widget(Select2::classname(), [
    'data' => $queueList,
    'language' => 'ru',
    'options' => ['placeholder' => ' Choose queues for operator...', 'multiple' => true],
    'pluginOptions' => [
        'allowClear' => true
    ],
]);

echo Html::submitButton('Save Queues', ['class' => 'btn btn-success', 'style' => 'margin-top: 10px; margin-right: 5px;']);

ActiveForm::end();
?>
