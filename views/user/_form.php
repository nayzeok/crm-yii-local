<?php

use common\models\User;
use frontend\models\SignupForm;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var common\models\User $model */
/** @var yii\widgets\ActiveForm $form */
/** @var frontend\models\SignupForm $signupForm */

?>

<div class="user-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'username')->textInput(['autofocus' => true], ['maxlength' => true]) ?>

    <?= $form->field($model, 'email')->textInput(['maxlength' => true]) ?>

    <?= $form->field($signupForm, 'password')->passwordInput() ?>

    <?= $form->field($model, 'status')
        ->dropDownList(User::STATUS_LABELS,
            [
                'prompt' => 'Choose status',
                'value' => $model->status ?? User::STATUS_ACTIVE,
                'class' => 'form-control prompt',
            ]) ?>

    <?= $form->field($model, 'role')
        ->dropDownList(User::ROLE_LABELS,
            [
                'prompt' => 'Choose role',
                'value' => $model->role ?? User::ROLE_OPERATOR
            ]) ?>

    <br>
    <div class="form-group">
        <?= Html::submitButton(Yii::t('app', 'Save'), ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>