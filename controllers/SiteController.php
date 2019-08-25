<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\httpclient\Client;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use app\models\LoginForm;
use app\models\ContactForm;
use app\models\Paper;
use ZipArchive;

class SiteController extends Controller
{
    
    public function downloadData($date) {
        $file_path = '/tmp/' . $date . '.zip';
        $fh = fopen($file_path, 'w');
        $client = new Client([
            'transport' => 'yii\httpclient\CurlTransport'
        ]);
        
        $response = $client->createRequest()
            ->setMethod('GET')
            ->setUrl('http://bvmf.bmfbovespa.com.br/InstDados/SerHist/COTAHIST_D' . $date . '.zip')
            ->setOutputFile($fh)
            ->send();
    }    

    public function extractData($date) {
        $file_path = '/tmp/' . $date . '.zip';
        $zip = new ZipArchive;
        $res = $zip->open($file_path);
        if ($res === TRUE) {
            echo 'ok';
            $zip->extractTo('/tmp');
            $zip->close();
        } else {
            echo 'failed, code:' . $res;
        }
    }

    public function parseDataAndSaveInDatabase($date, $date_paper) {
        $file = fopen("/tmp/COTAHIST_D". $date . ".TXT","r");
        if ($file) {
            $header = fgets($file);
            while (($line = fgets($file)) !== false) {
                $paper = new Paper();

                // 2 parametro do substr - posicao inicial a ser lida na linha
                //(1 numero a menos do que especidicado no manual)
                // 3 parametro do substr - qtd caracteres a ser lido a partir da posicao inicial

                //NOMRES - NOME RESUMIDO DA EMPRESA EMISSORA DO PAPEL
                $paper->name = substr($line,27,12);
                
                //PREULT - PREÇO DO ÚLTIMO NEGÓCIO DO PAPEL-MERCADO NO PREGÃO
                $paper->price = substr($line,108,11);

                $paper->created_at = date("Y-m-d H:i:s");

                $paper->date = $date_paper; 

                $paper->save();
            }

            fclose($file);
        } else {
            // TODO: add error log
        }
        return "OK"; 
    }

    public function actionImport()
    {
        $date = '05042019';
        
        $this->downloadData($date);
        $this->extractData($date);
        $date_paper = substr_replace($date, "-", 2, 0);
        $date_paper = substr_replace($date_paper, "-", 5, 0);
        $date_paper = date("Y-m-d H:i:s", strtotime($date_paper));
        $register = $this->parseDataAndSaveInDatabase($date, $date_paper);

        return $register;

        
        
    }


    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {
        return $this->render('index');
    }

    /**
     * Login action.
     *
     * @return Response|string
     */
    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack();
        }

        $model->password = '';
        return $this->render('login', [
            'model' => $model,
        ]);
    }

    /**
     * Logout action.
     *
     * @return Response
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    /**
     * Displays contact page.
     *
     * @return Response|string
     */
    public function actionContact()
    {
        $model = new ContactForm();
        if ($model->load(Yii::$app->request->post()) && $model->contact(Yii::$app->params['adminEmail'])) {
            Yii::$app->session->setFlash('contactFormSubmitted');

            return $this->refresh();
        }
        return $this->render('contact', [
            'model' => $model,
        ]);
    }

    /**
     * Displays about page.
     *
     * @return string
     */
    public function actionAbout()
    {
        return $this->render('about');
    }


}
