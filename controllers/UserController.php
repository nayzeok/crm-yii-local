<?php

namespace frontend\controllers;

use common\models\Product;
use common\models\Queue;
use common\models\Trigger;
use common\models\User;
use common\models\UserSearch;
use frontend\models\SignupForm;
use Throwable;
use Yii;
use yii\base\Exception;
use yii\db\StaleObjectException;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;
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
                    'only' => ['operators'], // Указываем, что правила применяются только к 'operators'
                    'rules' => [
                        [
                            'allow' => true,
                            'roles' => [User::ROLE_SUPERVISOR], // Указываем роль 'supervisor'
                        ],
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
                    ]]]
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

    /**
     * Displays a list of operators.
     * This action renders the index view for the operators, filtering them based on the search model criteria.
     *
     * @return string The rendering result of the operators index view.
     */
    public function actionOperators(): string
    {
        $searchModel = new UserSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $dataProvider->query->andWhere(['role' => User::ROLE_OPERATOR, 'status' => User::STATUS_ACTIVE]);

        return $this->render('operators/index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Manages the assignment of queues to a specific user (operator).
     * This action handles the queue management for an operator identified by $id. It includes both the display of the
     * queue management form and the processing of any changes submitted via a POST request.
     *
     * @param int $id The ID of the user (operator) for whom the queues are being managed.
     * @return string|\yii\web\Response The rendering result of the queue management view or a redirection if the queues are successfully updated.
     */
    public function actionQueuesManagement($id): Response|string
    {
        $user = $this->findModel($id);
        $currentQueueIds = ArrayHelper::getColumn($user->queues, 'id');

        if (Yii::$app->request->isPost) {
            $selectedQueueIds = Yii::$app->request->post('User')['queues'];

            $queuesToDelete = array_diff($currentQueueIds, $selectedQueueIds);
            foreach ($queuesToDelete as $queueId) {
                $queue = Queue::findOne($queueId);
                if ($queue) {
                    $user->unlink('queues', $queue, true);
                }
            }

            $queuesToAdd = array_diff($selectedQueueIds, $currentQueueIds);
            foreach ($queuesToAdd as $queueId) {
                $queue = Queue::findOne($queueId);
                if ($queue) {
                    $user->link('queues', $queue);
                }
            }

            if ($user->save()) {
                Yii::$app->session->setFlash('success', 'The queues have been successfully updated.');
                return $this->redirect(['operators', 'id' => $user->id]);
            } else {
                Yii::$app->session->setFlash('error', 'There was an error when updating queues.');
            }
        }

        return $this->render('operators/queues_management', [
            'user' => $user,
            'queueList' => ArrayHelper::map(Queue::find()->all(), 'id', 'name'),
            'selectedQueueIds' => $currentQueueIds,
        ]);
    }

    /**
     * Manages the assignment of products to a user.
     * The method retrieves the current products associated with the user,
     * processes the input from the product assignment form,
     * and updates the user_product associations accordingly.
     *
     * @param integer $id The user ID for which product assignments are managed.
     * @return mixed The rendering result of the goods management page or a redirect to the operator's page.
     * @throws NotFoundHttpException if the user model cannot be found.
     */
    public function actionGoodsManagement($id): mixed
    {
        $user = $this->findModel($id);
        $currentProductIds = ArrayHelper::getColumn($user->products, 'id');

        if (Yii::$app->request->isPost) {
            $selectedProductIds = Yii::$app->request->post('User')['products'];

            if (!is_array($selectedProductIds)) {
                $selectedProductIds = [];
            }

            $productsToDelete = array_diff($currentProductIds, $selectedProductIds);
            foreach ($productsToDelete as $productId) {
                $product = Product::findOne($productId);
                if ($product) {
                    $user->unlink('products', $product, true);
                }
            }

            $productsToAdd = array_diff($selectedProductIds, $currentProductIds);
            foreach ($productsToAdd as $productId) {
                $product = Product::findOne($productId);
                if ($product) {
                    $user->link('products', $product);
                }
            }

            if ($user->save()) {
                Yii::$app->session->setFlash('success', 'The products have been successfully updated.');
                return $this->redirect(['operators', 'id' => $user->id]);
            } else {
                Yii::$app->session->setFlash('error', 'There was an error when updating products.');
            }
        }

        return $this->render('operators/goods_management', [
            'user' => $user,
            'productList' => ArrayHelper::map(Product::find()->all(), 'id', 'name'),
            'selectedProductIds' => $currentProductIds,
        ]);
    }

    /**
     * Controls the assignment of a web ID for a user from the `triggers` table.
     * @param int $id User ID.
     * @return mixed The result of rendering the web ID control page or redirect if the update was successful.
     * @throws NotFoundHttpException If the user model is not found.
     */
    public function actionWebIdManagement($id): mixed
    {
        $user = $this->findModel($id);

        $webIdOptions = ArrayHelper::map(
            Trigger::find()->select('lead_web_id')->distinct()->where(['not', ['lead_web_id' => null]])->all(),
            'lead_web_id',
            'lead_web_id'
        );

        $currentWebIds = ArrayHelper::getColumn($user->getLeadWebIds()->all(), 'lead_web_id');

        if (Yii::$app->request->isPost) {
            $selectedWebIds = Yii::$app->request->post('User')['lead_web_id'] ?? [];

            if (!is_array($selectedWebIds)) {
                $selectedWebIds = [];
            }

            $webIdsToDelete = array_diff($currentWebIds, $selectedWebIds);
            Yii::$app->db->createCommand()->delete('user_web_id', [
                'user_id' => $id,
                'web_id' => $webIdsToDelete
            ])->execute();

            $webIdsToAdd = array_diff($selectedWebIds, $currentWebIds);

            foreach ($webIdsToAdd as $webId) {
                $exists = Yii::$app->db->createCommand('SELECT COUNT(*) FROM user_web_id WHERE user_id=:userId AND web_id=:webId')
                    ->bindValue(':userId', $id)
                    ->bindValue(':webId', $webId)
                    ->queryScalar();

                if (!$exists) {
                    Yii::$app->db->createCommand()->insert('user_web_id', [
                        'user_id' => $id,
                        'web_id' => $webId
                    ])->execute();
                }
            }

            Yii::$app->session->setFlash('success', 'The Web Id have been successfully updated.');
            return $this->redirect(['operators', 'id' => $user->id]);
        }

        return $this->render('operators/web_id_management', [
            'user' => $user,
            'webIdOptions' => $webIdOptions,
            'currentWebIds' => $currentWebIds,
        ]);
    }

    /**
     * Manages group queues for selected operators.
     * This method allows for bulk management of operator queues. It involves selecting operators
     * and queues, and then applying the specified action (e.g., replacing or updating the queues).
     * If no operators or queues are selected, an error message is flashed and the user is redirected.
     * The method handles exceptions and rollbacks in case of errors.
     *
     * @return Response Redirects to the operators management page with a success or error message.
     */
    public function actionGroupQueueManagement(): Response
    {
        $action = Yii::$app->request->post('action');
        $selectedOperators = Yii::$app->request->post('selectedOperators');
        $queueIds = Yii::$app->request->post('Queue')['id'] ?? [];

        if (empty($selectedOperators) || empty($queueIds)) {
            Yii::$app->session->setFlash('error', 'Необходимо выбрать операторов и очереди.');
            return $this->redirect(['operators']);
        }

        $selectedOperators = explode(',', $selectedOperators);
        $transaction = Yii::$app->db->beginTransaction();

        try {
            foreach ($selectedOperators as $operatorId) {
                $operator = User::findOne($operatorId);
                if (!$operator) {
                    throw new NotFoundHttpException("Operator with ID $operatorId не найден.");
                }

                if ($action === 'replace') {
                    $operator->unlinkAll('queues', true);
                }

                foreach ($queueIds as $queueId) {
                    $queue = Queue::findOne($queueId);
                    if ($queue) {
                        $operator->link('queues', $queue);
                    }
                }
            }
            $transaction->commit();
            Yii::$app->session->setFlash('success', 'The queues have been successfully updated.');
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::$app->session->setFlash('error', 'Error when updating queues: ' . $e->getMessage());
        }
        return $this->redirect(['operators']);
    }

    /**
     * Manages group goods for selected operators.
     * This method is responsible for the group management of goods for the selected operators.
     * It includes selecting operators and products, and then performing the specified action
     * (such as replacing or updating the products). If no operators or products are selected,
     * an error message is displayed, and the user is redirected. The method also handles
     * exceptions and rollbacks in case of errors.
     *
     * @return Response Redirects to the operators management page with a success or error message.
     */
    public function actionGroupGoodsManagement(): Response
    {
        $action = Yii::$app->request->post('action');
        $selectedOperators = Yii::$app->request->post('selectedOperators');
        $productIds = Yii::$app->request->post('products')['id'] ?? [];

        if (empty($selectedOperators) || empty($productIds)) {
            Yii::$app->session->setFlash('error', 'Operators and product need to be selected.');
            return $this->redirect(['operators']);
        }

        $selectedOperators = explode(',', $selectedOperators);
        $transaction = Yii::$app->db->beginTransaction();

        try {
            foreach ($selectedOperators as $operatorId) {
                $operator = User::findOne($operatorId);
                if (!$operator) {
                    throw new NotFoundHttpException("Operator with ID $operatorId not found.");
                }

                if ($action === 'replace') {
                    $operator->unlinkAll('products', true);
                }

                foreach ($productIds as $productId) {
                    $product = Product::findOne($productId);
                    if ($product && !$operator->getProducts()->where(['id' => $productId])->exists()) {
                        $operator->link('products', $product);
                    }
                }
            }

            $transaction->commit();
            Yii::$app->session->setFlash('success', 'The products have been successfully updated.');
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::$app->session->setFlash('error', 'Error when updating products: ' . $e->getMessage());
        }
        return $this->redirect(['operators']);
    }

    /**
     * Manages group web IDs for selected operators.
     * This method allows for bulk management of operator web IDs. It involves selecting operators
     * and web IDs, and then applying the specified action (e.g., assigning or updating web IDs).
     * If no operators or web IDs are selected, an error message is flashed and the user is redirected.
     * The method handles exceptions and rollbacks in case of errors.
     *
     * @return Response Redirects to the operators management page with a success or error message.
     */
    public function actionGroupWebIdManagement(): Response
    {
        $action = Yii::$app->request->post('action');
        $selectedOperators = Yii::$app->request->post('selectedOperators');
        $webIds = Yii::$app->request->post('WebId')['id'] ?? [];

        if (empty($selectedOperators) || empty($webIds)) {
            Yii::$app->session->setFlash('error', 'Operators and Web ID need to be selected.');
            return $this->redirect(['operators']);
        }

        $selectedOperators = explode(',', $selectedOperators);
        $transaction = Yii::$app->db->beginTransaction();

        try {
            foreach ($selectedOperators as $operatorId) {
                $operator = User::findOne($operatorId);
                if (!$operator) {
                    throw new NotFoundHttpException("Operator with ID $operatorId not found.");
                }

                if ($action === 'replace') {
                    Yii::$app->db->createCommand()->delete('user_web_id', ['user_id' => $operatorId])->execute();
                }

                foreach ($webIds as $webId) {
                    $exists = Yii::$app->db->createCommand('SELECT COUNT(*) FROM user_web_id WHERE user_id=:userId AND web_id=:webId')
                        ->bindValue(':userId', $operatorId)
                        ->bindValue(':webId', $webId)
                        ->queryScalar();

                    if (!$exists) {
                        Yii::$app->db->createCommand()->insert('user_web_id', [
                            'user_id' => $operatorId,
                            'web_id' => $webId
                        ])->execute();
                    }
                }
            }
            $transaction->commit();
            Yii::$app->session->setFlash('success', 'Web IDs for operators successfully updated.');
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::$app->session->setFlash('error', 'Error when updating web ID: ' . $e->getMessage());
        }
        return $this->redirect(['operators']);
    }
}
