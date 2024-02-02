<?php

use kartik\select2\Select2;
use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\helpers\ArrayHelper;
use common\models\Trigger;
use yii\widgets\DetailView;


/* @var $this yii\web\View */
/* @var $user common\models\User */
/* @var $form yii\widgets\ActiveForm */

// Получаем список lead_web_id из таблицы triggers
$webIdList = ArrayHelper::map(Trigger::find()->select(['lead_web_id'])->distinct()->all(), 'lead_web_id', 'lead_web_id');

$this->title = 'Management Web ID for user: ' . $user->username;
$this->params['breadcrumbs'][] = ['label' => Yii::t('app', 'Operators'), 'url' => ['operators']];
$this->params['breadcrumbs'][] = $this->title;

$form = ActiveForm::begin([
    'id' => 'web-id-management-form',
    'options' => ['class' => 'form-horizontal'],
]);

// Отображение текущего пользователя
echo DetailView::widget([
    'model' => $user,
    'attributes' => [
        'id',
        'username',
    ]
]);

// Мультиселект для lead_web_id
echo $form->field($user, 'lead_web_id')->widget(Select2::classname(), [
    'data' => $webIdList,
    'language' => 'ru',
    'options' => [
        'placeholder' => 'Выберите Web ID...',
        'multiple' => true,
        'value' => $currentWebIds,
    ],
    'pluginOptions' => [
        'allowClear' => true,
        'tags' => false,
    ],
]);

echo Html::submitButton(Yii::t('app', 'Сохранить'), ['class' => 'btn btn-success', 'style' => 'margin-top: 10px; margin-right: 5px;']);

ActiveForm::end();
?>
