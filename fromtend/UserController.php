<?php

namespace frontend\controllers;

use common\models\User;
use common\models\UserSearch;
use frontend\models\SignupForm;
use Throwable;
use Yii;
use yii\base\Exception;
use yii\db\StaleObjectException;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\Response;

/**
 * UserController implements the CRUD actions for User model.
 */
class UserController extends Controller
{
    /**
     * @return array
     */
    public function behaviors(): array
    {
        return array_merge(
            parent::behaviors(),
            [
                'access' => [
                    'class' => AccessControl::class,
                    'only' => ['*'],
                    'rules' => [
                        [
                            'allow' => true,
                            'roles' => [User::ROLE_ADMIN]
                        ],
                    ],
                ],
                'verbs' => [
                    'class' => VerbFilter::className(),
                    'actions' => [
                        'delete' => ['POST'],
                    ],
                ],
            ]
        );
    }


    /**
     * Lists all User models.
     *
     * @return string
     * @throws ForbiddenHttpException
     */
    public function actionIndex(): string
    {
        $searchModel = new UserSearch();
        $dataProvider = $searchModel->search($this->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single User model.
     * @param int $id
     * @return string
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView(int $id): string
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new User model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * If an error occurs, it will return to the creation page and specify the field with the error.
     * @return string|Response
     * @throws \Exception
     */
    public function actionCreate()
    {
        $model = new User();
        $signupForm = new SignupForm();
        $signupForm->scenario = SignupForm::SCENARIO_BY_ADMIN;

        if (!$this->request->isPost) {
            return $this->render('create', [
                'model' => $model,
                'signupForm' => $signupForm
            ]);
        }

        if ($model->load(Yii::$app->request->post())
            && $signupForm->load(Yii::$app->request->post())
            && (empty($signupForm->password) || $model->setPassword($signupForm->password))
            && $model->generateAuthKey()
                ->generateEmailVerificationToken()
                ->save()) {

            $authManager = Yii::$app->authManager;
            $newRole = $authManager->getRole($model->role ?? Yii::$app->params['defaultUserRole']);
            $authManager->assign($newRole, $model->id);

            Yii::$app->session->setFlash('success', "User created! $model->username should check his inbox for verification email.");
            return $this->redirect(['index']);
        }
        Yii::$app->session->setFlash('error', 'There was an error creating the user.');
        return $this->render('create', [
            'model' => $model,
            'signupForm' => $signupForm
        ]);
    }

    /**
     * Updates an existing User model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * If an error occurs, it will return to the update page and specify the field with the error.
     * @param int $id
     * @return string|Response
     * @throws NotFoundHttpException
     * @throws Exception
     */
    public function actionUpdate(int $id)
    {
        $model = $this->findModel($id);
        $signupForm = new SignupForm();
        $signupForm->scenario = SignupForm::SCENARIO_BY_ADMIN;

        if (!$this->request->isPost) {
            return $this->render('update', [
                'model' => $model,
                'signupForm' => $signupForm
            ]);
        }

        if ($model->load(Yii::$app->request->post())
            && $signupForm->load(Yii::$app->request->post())
            && (empty($signupForm->password) || $model->setPassword($signupForm->password))
            && $model->save()) {

            $authManager = Yii::$app->authManager;
            //удаляем все текущие роли
            $authManager->revokeAll($model->id);
            // Назначаем новую роль
            $newRole = $authManager->getRole($model->role ?? Yii::$app->params['defaultUserRole']);
            $authManager->assign($newRole, $model->id);

            Yii::$app->session->setFlash('success', 'User updated!');
            return $this->redirect(['index']);
        }
        Yii::$app->session->setFlash('error', 'An error occurred while updating user data.');
        return $this->render('create', [
            'model' => $model,
            'signupForm' => $signupForm
        ]);
    }

    /**
     * "Deletes" a user, setting their status as remote.
     *
     * @param int $id User ID.
     * @return Response Redirects to the list of users.
     * @throws NotFoundHttpException If the user is not found.
     */
    public function actionDelete(int $id): Response
    {
        $user = $this->findModel($id);

        if ($user->delete()) {
            Yii::$app->session->setFlash('success', 'User deleted.');
        } else {
            Yii::$app->session->setFlash('error', 'An error occurred when deleting a user.');
        }

        return $this->redirect(['index']);
    }

    /**
     * Finds the User model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id
     * @return User the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel(int $id): User
    {
        if (($model = User::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException(Yii::t('app', 'The requested page does not exist.'));
    }
}
