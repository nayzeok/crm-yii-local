<?php

use common\models\Product;
use common\models\Queue;
use common\models\Trigger;
use yii\base\DynamicModel;
use yii\bootstrap5\Modal;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\GridView;
use yii\widgets\ActiveForm;
use kartik\select2\Select2;

/* @var $this yii\web\View */
/* @var $searchModel common\models\UserSearch */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = Yii::t('app', 'Operators');
$this->params['breadcrumbs'][] = $this->title;

//Получаем списки доступных очередей, товаров и web ID
$queueList = ArrayHelper::map(Queue::find()->all(), 'id', 'name');
$productList = ArrayHelper::map(Product::find()->all(), 'id', 'name');
$webIdList = ArrayHelper::map(Trigger::find()->select(['lead_web_id'])->distinct()->all(), 'lead_web_id', 'lead_web_id');

?>
<div class="operator-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
    <div class="dropdown">
        <button class="btn btn-primary dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown"
                aria-haspopup="true" aria-expanded="false">
            Group Management
        </button>
        <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
            <a class="dropdown-item" href="#" data-action="queue">Queues Management</a>
            <a class="dropdown-item" href="#" data-action="good">Goods Management</a>
            <a class="dropdown-item" href="#" data-action="web-id">Web ID Management</a>
        </div>
    </div>
    </p>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],
            [
                'class' => 'yii\grid\CheckboxColumn',
                'checkboxOptions' => function ($model) {
                    return ['value' => $model->id];
                },
            ],
            [
                'class' => 'yii\grid\ActionColumn',
                'template' => '{actions}',
                'buttons' => [
                    'actions' => function ($url, $model, $key) {
                        return '<div class="dropdown">
                         <button class="btn custom-btn dropdown-toggle" id="dropdownActionsMenuButton' . $key . '" data-bs-toggle="dropdown" aria-expanded="false" " style="background-color: #0055A8FF; color: #ffffff;">
                            Actions
                        </button>
                        <div class="dropdown-menu" aria-labelledby="actionsDropdown">
                            <a class="dropdown-item" href="' . Url::to(['queues-management', 'id' => $model->id]) . '">Queues Management</a>
                            <a class="dropdown-item" href="' . Url::to(['goods-management', 'id' => $model->id]) . '">Goods Management</a>
                            <a class="dropdown-item" href="' . Url::to(['web-id-management', 'id' => $model->id]) . '">Web Id Management</a>
                        </div>
                    </div>';
                    },
                ],
            ],
            'id',
            'username',
            'email',
        ],
    ]); ?>

    <?php
    // Модальное окно для назначения очередей
    Modal::begin([
        'title' => 'Назначение очередей',
        'id' => 'queue-modal',
        'size' => Modal::SIZE_LARGE,
        'toggleButton' => false,
    ]);

    $form = ActiveForm::begin([
        'id' => 'queue-management-form',
        'action' => Url::to(['user/group-queue-management']),
        'method' => 'post',
    ]);

    echo Html::hiddenInput('selectedOperators', '', ['id' => 'selected-operators-for-queues']);

    echo $form->field(new Queue(), 'id[]')->widget(Select2::classname(), [
        'data' => $queueList,
        'options' => ['placeholder' => 'Выберите очереди...', 'multiple' => true],
        'pluginOptions' => ['allowClear' => true],
    ])->label(false);

    echo Html::radioList('action', null, [
        'replace' => 'Replace with selected products',
        'append' => 'Supplement with selected products'
    ], ['item' => function ($index, $label, $name, $checked, $value) {
        $return = "<label class='radio-inline'>";
        $return .= "<input type='radio' name='{$name}' value='{$value}' " . ($checked ? " checked" : "") . "> {$label}";
        $return .= "</label>";
        return $return;
    }]);

    echo Html::submitButton('Save Queues', ['class' => 'btn btn-success', 'style' => 'margin-top: 10px; margin-right: 5px;']);
    ActiveForm::end();
    Modal::end();

    // Модальное окно для назначения очередей
    Modal::begin([
        'title' => 'Product Assignment',
        'id' => 'product-modal',
        'size' => Modal::SIZE_LARGE,
        'toggleButton' => false,
    ]);

    $form = ActiveForm::begin([
        'id' => 'goods-management-form',
        'action' => Url::to(['user/group-goods-management']),
        'method' => 'post',
    ]);

    echo Html::hiddenInput('selectedOperators', '', ['id' => 'selected-operators-for-products']);

    echo $form->field(new Product(), 'id')->widget(Select2::classname(), [
        'data' => $productList,
        'options' => ['placeholder' => 'Choose goods...', 'multiple' => true, 'name' => 'products[id]'],
        'pluginOptions' => ['allowClear' => true],
    ])->label(false);

    echo Html::radioList('action', null, [
        'replace' => 'Replace with selected products',
        'append' => 'Supplement with selected products'
    ], ['item' => function ($index, $label, $name, $checked, $value) {
        $return = "<label class='radio-inline'>";
        $return .= "<input type='radio' name='{$name}' value='{$value}' " . ($checked ? " checked" : "") . "> {$label}";
        $return .= "</label>";
        return $return;
    }]);

    echo Html::submitButton('Save products', ['class' => 'btn btn-success', 'style' => 'margin-top: 10px; margin-right: 5px;']);
    ActiveForm::end();
    Modal::end();

    // Модальное окно для назначения Web ID
    Modal::begin([
        'title' => 'Web ID Assignment',
        'id' => 'web-id-modal',
        'size' => Modal::SIZE_LARGE,
        'toggleButton' => false,
    ]);

    $form = ActiveForm::begin([
        'id' => 'web-id-management-form',
        'action' => Url::to(['user/group-web-id-management']),
        'method' => 'post',
    ]);

    echo Html::hiddenInput('selectedOperators', '', ['id' => 'selected-operators-for-web-id']);

    echo $form->field(new DynamicModel(['web_id']), 'web_id')->widget(Select2::classname(), [
        'data' => $webIdList,
        'options' => ['placeholder' => 'Choose Web ID...', 'multiple' => true, 'name' => 'WebId[id]'],
        'pluginOptions' => ['allowClear' => true],
    ])->label(false);

    echo Html::radioList('action', null, [
        'replace' => 'Replace with selected Web ID',
        'append' => 'Supplement with selected Web ID'
    ], ['item' => function ($index, $label, $name, $checked, $value) {
        $return = "<label class='radio-inline'>";
        $return .= "<input type='radio' name='{$name}' value='{$value}' " . ($checked ? " checked" : "") . "> {$label}";
        $return .= "</label>";
        return $return;
    }]);

    echo Html::submitButton('Save Web ID', ['class' => 'btn btn-success', 'style' => 'margin-top: 10px; margin-right: 5px;']);
    ActiveForm::end();
    Modal::end();

    ?>

    <?php
    $script = <<< JS
    $(document).on('click', '#dropdownMenuButton + .dropdown-menu .dropdown-item', function(e) {
    e.preventDefault();

    var action = $(this).data('action');
    var keys = $('.grid-view').yiiGridView('getSelectedRows');

    if (keys.length === 0) {
        alert('Operators not selected');
        return;
    }

    var selectedOperatorsStr = keys.join(',');

    if (action === 'queue') {
        $('#queue-modal').modal('show');
        $('#selected-operators-for-queues').val(selectedOperatorsStr);
    } else if (action === 'good') {
        $('#product-modal').modal('show');
        $('#selected-operators-for-products').val(selectedOperatorsStr);
    } else if (action === 'web-id') {
        $('#web-id-modal').modal('show');
        $('#selected-operators-for-web-id').val(selectedOperatorsStr);
    }
    });
    JS;
    $this->registerJs($script);
    ?>
</div>

