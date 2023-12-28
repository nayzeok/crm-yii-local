<?php

namespace frontend\controllers;

use common\models\Queue;
use common\models\QueueSearch;
use common\models\Trigger;
use common\models\User;
use common\models\UserSearch;
use Exception;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\Response;

/**
 * QueueController implements the CRUD actions for Queue model.
 */
class QueueController extends Controller
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
                            'allow' => true,
                            'roles' => [User::ROLE_SUPERVISOR]
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
     * Lists all Queue models.
     *
     * @return string
     */
    public function actionIndex(): string
    {
        $query = Queue::find()->with('orders');

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Queue model.
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
     * Creates a new Queue model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return string|Response
     */
    public function actionCreate()
    {
        $model = new Queue();
        if ($this->request->isPost) {
            if ($model->load($this->request->post()) && $model->save()) {
                $this->saveTriggers($model->id);
                Yii::$app->session->setFlash('success', 'Queue Saved.');
                return $this->redirect(['view', 'id' => $model->id]);
            }
        }

        return $this->render('create', [
            'model' => $model
        ]);
    }

    /**
     * Updates an existing Queue model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param int $id ID
     * @return string|Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate(int $id)
    {
        $model = $this->findModel($id);

        if ($this->request->isPost) {
            if ($model->load($this->request->post()) && $model->save()) {
                foreach ($model->triggers as $trigger) {
                    $trigger->delete();
                }
                $this->saveTriggers($model->id);
                Yii::$app->session->setFlash('success', 'Queue Saved.');
                return $this->redirect(['view', 'id' => $model->id]);
            }
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing Queue model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param int $id ID
     * @return Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete(int $id): Response
    {
        $queue = $this->findModel($id);

        // Проверяем, есть ли заказы в очереди
        if ($queue->hasOrders()) {
            Yii::$app->session->setFlash('error', 'It is not possible to delete a queue because it has orders in it.');
            return $this->redirect(['index']);
        }

        // Если заказов нет, удаляем очередь
        $queue->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the Queue model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id ID
     * @return Queue the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel(int $id): Queue
    {
        if (($model = Queue::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException(Yii::t('app', 'The requested page does not exist.'));
    }

    /**
     * @param int $queue_id
     * @return bool
     */
    private function saveTriggers(int $queue_id): bool
    {
        try {
            if ($this->request->post('Trigger') == null) {
                throw new Exception('Trigger no data!');
            }
            foreach ($this->request->post('Trigger') as $triggerPost) {
                $trigger = new Trigger();
                $trigger->load($triggerPost);
                $trigger->queue_id = $queue_id;
                if (!$trigger->save()) {
                    throw new Exception('Trigger error: ' . json_encode($trigger->errors));
                }
            }
        } catch (Exception $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
            return false;
        }
        return true;
    }


    /**
     * @return string|Response
     */
    public function actionQueueUser()
    {
        $dataProvider = new ActiveDataProvider([
            'query' => User::find(),
        ]);
        $queues = Queue::find()->all();
        $queues = ArrayHelper::index($queues, 'id');
        $columnQueues = [];
        foreach ($queues as $queue) {
            $columnQueues[] = [
                'attribute' => $queue->name,
                'format' => 'html',
                'content' => function ($data) use ($queue) {
                    return Html::checkbox("UserQueue[$data->id][]", isset(ArrayHelper::map($data->queues, 'id', 'name')[$queue->id]), ['value' => $queue->id]);
                },
            ];
        }
        if ($this->request->isPost) {
            if ($this->request->post()) {
                $userQueueMap = $this->request->post('UserQueue');
                foreach ($dataProvider->models as $operator) {
                    foreach ($operator->queues as $queue) {
                        $operator->unlink('queues', $queue, true);
                    }
                    if (empty($userQueueMap[$operator->id]) || !is_array($userQueueMap[$operator->id])) {
                        continue;
                    }
                    foreach ($userQueueMap[$operator->id] as $userQueue) {
                        if (empty($queues[$userQueue])) {
                            continue;
                        }
                        $operator->link('queues', $queues[$userQueue]);
                    }
                }
                Yii::$app->session->setFlash('success', 'Queue-Operators Saved.');
                return $this->redirect(['queue-user']);
            }
        }

        return $this->render('queueUser', [
            'dataProvider' => $dataProvider,
            'columnQueues' => $columnQueues ?? []
        ]);
    }
}
