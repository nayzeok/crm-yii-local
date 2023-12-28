<?php

namespace frontend\controllers;

use common\models\Promotion;
use common\models\PromotionSearch;
use common\models\User;
use ReflectionException;
use Throwable;
use Yii;
use yii\db\StaleObjectException;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\Response;

/**
 * PromotionController implements the CRUD actions for Promotion model.
 */
class PromotionController extends Controller
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
                            'actions' => ['index', 'create', 'update', 'delete', 'view', 'get-promotion-details'],
                            'allow' => true,
                            'roles' => [User::ROLE_SUPERVISOR],
                        ],
                        [
                            'actions' => ['get-promotion-details'],
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
     * Lists all Promotion models.
     *
     * @return string
     */
    public function actionIndex(): string
    {
        $searchModel = new PromotionSearch();
        $dataProvider = $searchModel->search($this->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Promotion model.
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
     * Creates a new Promotion model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return string|Response
     */
    public function actionCreate()
    {
        $model = new Promotion();

        if ($this->request->isPost) {
            if ($model->load($this->request->post()) && $model->save()) {
                return $this->redirect(['index']);
            }
        } else {
            $model->loadDefaultValues();
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing Promotion model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param int $id ID
     * @return string|Response
     * @throws NotFoundHttpException if the model cannot be found
     * @throws ReflectionException
     */
    public function actionUpdate(int $id)
    {
        $model = $this->findModel($id);

        if ($this->request->isPost && $model->load($this->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing Promotion model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param int $id ID
     * @return Response
     * @throws NotFoundHttpException if the model cannot be found
     * @throws Throwable
     * @throws StaleObjectException
     */
    public function actionDelete(int $id): Response
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the Promotion model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id ID
     * @return Promotion the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel(int $id): Promotion
    {
        if (($model = Promotion::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException(Yii::t('promotion', 'The requested page does not exist.'));
    }

    /**
     * Retrieve promotion details by ID.
     *
     * @param int|string $id stock ID.
     * @return array Array with stock details or error.
     */
    public function actionGetPromotionDetails($id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        /** @var Promotion $promotion */
        $promotion = Promotion::findOne($id);

        if (!$promotion) {
            // Если акция не найдена, возвращаем ошибку
            return [
                'error' => 'Promotion not found',
            ];
        }

        // Возвращаем данные о выбранной акции в формате JSON
        return [
            'type' => $promotion->type,
            'params' => $promotion->params,
        ];
    }
}

