<?php

namespace frontend\controllers;

use common\models\CustomerInfo;
use common\models\Order;
use common\models\OrderDetail;
use common\models\OrderSearch;
use common\models\User;
use common\models\OrderProduct;
use Exception;
use frontend\models\OrderImportForm;
use Yii;
use Yii\db\Exception as dbException;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\Response;
use yii\web\UploadedFile;
use yii2tech\csvgrid\CsvGrid;

/**
 * OrderController implements the CRUD actions for Order model.
 */
class OrderController extends Controller
{
    /**
     * @inheritDoc
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
                            'actions' => ['index', 'create', 'view', 'update', 'delete', 'import'],
                            'allow' => true,
                            'roles' => [User::ROLE_SUPERVISOR],
                        ],
                        [
                            'actions' => ['call', 'create'],
                            'allow' => true,
                            'roles' => [User::ROLE_OPERATOR],
                        ]
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
     * Lists all Order models.
     *
     * @return string
     */
    public function actionIndex(): string
    {
        $searchModel = new OrderSearch();
        $dataProvider = $searchModel->search($this->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Order model.
     * @param int $id ID
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
     * Creates a new Order model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return string|Response
     */
    public function actionCreate(): Response|string
    {
        $model = new Order();
        $customerInfo = $model->customerInfo ?? new CustomerInfo();
        $orderDetail = $model->orderDetail ?? new OrderDetail();
        $productPrices = OrderProduct::getProductPrices();

        if (!$this->request->isPost) {
            $model->loadDefaultValues();
            return $this->render('create', [
                'model' => $model,
                'customerInfo' => $customerInfo,
                'orderDetail' => $orderDetail,
                'productPrices' => $productPrices,
            ]);
        }

        try {
            //TODO тут нужно проверять что с формы оператора быс создан заказ (но есть проблемы в бефосейве)
            $model->saveByRequest($this->request);
            Yii::$app->session->setFlash('success', 'Order Saved!');
        } catch (Exception $e) {
            Yii::error($e->getMessage());
            Yii::$app->session->setFlash('error', $e->getMessage());

            // Загрузка данных из запроса обратно в модели
            $model->load($this->request->post());
            $customerInfo->load($this->request->post());
            $orderDetail->load($this->request->post());

            return $this->render('create', [
                'model' => $model,
                'customerInfo' => $customerInfo,
                'orderDetail' => $orderDetail,
                'productPrices' => $productPrices,
            ]);
        }
        if (Yii::$app->user->can(User::ROLE_OPERATOR)) {
            return $this->redirect(['call']);
        }
        return $this->redirect(['view', 'id' => $model->id]);
    }

    /**
     * Updates an existing Order model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param int $id ID
     * @return string|Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate(int $id): Response|string
    {
        $model = $this->findModel($id);
        $customerInfo = $model->customerInfo ?? new CustomerInfo();
        $orderDetail = $model->orderDetail ?? new OrderDetail();
        $productPrices = OrderProduct::getProductPrices(); // Определение переменной $productPrices

        if (!$this->request->isPost) {
            $model->loadDefaultValues();

            return $this->render('update', [
                'model' => $model,
                'customerInfo' => $customerInfo,
                'orderDetail' => $orderDetail,
                'productPrices' => $productPrices,
            ]);
        }

        try {
            $model->saveByRequest($this->request);

            Yii::$app->session->setFlash('success', 'Order Saved!');

        } catch (Exception $e) {
            Yii::error($e->getMessage());
            Yii::$app->session->setFlash('error', $e->getMessage());

            // Загрузка данных из запроса обратно в модели
            $model->load($this->request->post());
            $customerInfo->load($this->request->post());
            $orderDetail->load($this->request->post());

            return $this->render('update', [
                'model' => $model,
                'customerInfo' => $customerInfo,
                'orderDetail' => $orderDetail,
                'productPrices' => $productPrices,
            ]);
        }
        return $this->redirect(['view', 'id' => $model->id]);
    }

    /**
     * Calling
     *
     * @return string|Response
     */
    public function actionCall(): Response|string
    {
        $productPrices = OrderProduct::getProductPrices();
        /** @var Order $model */
        if (!$model = Yii::$app->user->identity->getAvailableOrder()) {
            Yii::$app->session->setFlash('warning', 'No leads yet');
            return $this->render('no-leads');
        }
        if (!$this->request->isPost) {
            $model->blockOrder();
            return $this->render('update-call', [
                'model' => $model,
                'customerInfo' => $model->customerInfo ?? new CustomerInfo(),
                'orderDetail' => $model->orderDetail ?? new OrderDetail(),
                'productPrices' => $productPrices,
            ]);
        }
        try {
            $model->setFromCallForm()->saveByRequest($this->request);
            Yii::$app->session->setFlash('success', 'Order Saved!');
        } catch (Exception $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        } finally {
            Yii::$app->session->setFlash('warning', 'Next...');
            return $this->redirect(['call']);
        }
    }

    /**
     * Deletes an existing Order model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param int $id ID
     * @return Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete(int $id): Response
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the Order model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id ID
     * @return Order the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel(int $id): Order
    {
        if (($model = Order::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException(Yii::t('app', 'The requested page does not exist.'));
    }

    /**
     * This method handles the import action. It initializes an OrderImportForm object
     * and an DataProvider for the Order model. If the request method is POST, it attempts
     * to upload a CSV file and import orders from it. It sets a flash message based on
     * the success or failure of these operations.
     *
     * @return Response|string If the request is a POST and the file is successfully uploaded, it redirects to the
     * 'import' route, otherwise it renders the 'import' view.
     *
     * @throws Exception If there is a general error during the import of orders.
     * @throws \Throwable
     */
    public function actionImport(): Response|string
    {
        $csvFile = new OrderImportForm();

        if (!Yii::$app->request->isPost) {
            return $this->render('import', ['model' => $csvFile]);
        }

        $csvFile->csvFile = UploadedFile::getInstance($csvFile, 'csvFile');
        if (!$csvFile->upload()) {
            foreach ($csvFile->errors as $error) {
                Yii::$app->session->setFlash('error', $error[0]);
            }
            return $this->redirect(['import']);
        }
        try {
            $orderCount = $csvFile->countOrdersInFile();
            $results = $csvFile->importOrders();
            $importedCount = $results['imported'];
            $failedOrders = $results['failed'];
            Yii::$app->session->setFlash('info', "Total orders on file: $orderCount. Orders ready for import: $importedCount.");
            if (!empty($failedOrders)) {
                throw new Exception("Failed to import orders:<br>" . implode("<br>", $failedOrders));
            }
        } catch (dbException $e) {
            Yii::$app->session->setFlash('error', 'Database error: ' . $e->getMessage());
        } catch (Exception $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        } finally {
            return $this->redirect(['import']);
        }
    }
}
