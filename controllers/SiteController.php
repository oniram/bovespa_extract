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
use app\models\DownloadJob;
use ZipArchive;
use Datetime;
use DateInterval;
use DatePeriod;

class SiteController extends Controller
{
    
    public function downloadData($date) {
        Yii::info("[IMPORT] start download data from " . $date);
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
        Yii::info("[IMPORT] end download data from " . $date);
    }    

    public function extractData($date) {
        Yii::info("[IMPORT] start extractData data from " . $date);
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
        Yii::info("[IMPORT] end extractData data from " . $date);
    }

    public function parseDataAndSaveInDatabase($date) {
        Yii::info("[IMPORT] start parseDataAndSaveInDatabase data from " . $date);
        $file = fopen("/tmp/COTAHIST_D". $date . ".TXT","r");
        if ($file) {
            $header = fgets($file);
            while (($line = fgets($file)) !== false) {
                if(substr($line,0,10) == "99COTAHIST") {
                    return "FIM";
                }
                $paper = new Paper();

                // 2 parametro do substr - posicao inicial a ser lida na linha
                //(1 numero a menos do que especidicado no manual)
                // 3 parametro do substr - qtd caracteres a ser lido a partir da posicao inicial

                //DATA DO PREGÃO
                $dateTime = DateTime::createFromFormat('Ymd', substr($line,2,8));
                $paper->date = $dateTime->format("Y-m-d");

                //CODBDI - CÓDIGO BDI
                //UTILIZADO PARA CLASSIFICAR OS PAPÉIS NA EMISSÃO DO BOLETIM DIÁRIO DE INFORMAÇÕES
                $paper->codbdi = substr($line,10,2);

                //CODNEG - CÓDIGO DE NEGOCIAÇÃO DO PAPEL
                $paper->codneg = substr($line,12,12);

                //TPMERC - TIPO DE MERCADO
                //CÓD. DO MERCADO EM QUE O PAPEL ESTÁ CADASTRADO
                $paper->tpmerc = substr($line,24,03);

                //NOMRES - NOME RESUMIDO DA EMPRESA EMISSORA DO PAPEL
                $paper->nomres = substr($line,27,12);

                //ESPECI - ESPECIFICAÇÃO DO PAPEL
                $paper->especi = substr($line,39,10);

                //PRAZOT - PRAZO EM DIAS DO MERCADO A TERMO
                $paper->prazot = substr($line,49,3);

                //MODREF - MOEDA DE REFERÊNCIA
                $paper->modref = substr($line,52,4);

                //PREABE - PREÇO DE ABERTURA DO PAPEL- MERCADO NO PREGÃO
                $paper->preab = substr($line,56,11);

                //PREMAX - PREÇO MÁXIMO DO PAPEL- MERCADO NO PREGÃO
                $paper->premax = substr($line,69,11);

                //PREMIN - PREÇO MÍNIMO DO PAPEL- MERCADO NO PREGÃO
                $paper->premin = substr($line,82,11);                

                //PREMED - PREÇO MÉDIO DO PAPEL- MERCADO NO PREGÃO
                $paper->premed = substr($line,95,11);                                
                //PREULT - PREÇO DO ÚLTIMO NEGÓCIO DO PAPEL-MERCADO NO PREGÃO
                $paper->preult = substr($line,108,11);

                //PREOFC - PREÇO DA MELHOR OFERTA DE COMPRA DO PAPEL- MERCADO
                $paper->preofc = substr($line,121,11);                

                //PREOFV - PREÇO DA MELHOR OFERTA DE VENDA DO PAPEL- MERCADO
                $paper->preofv = substr($line,134,11);                

                //TOTNEG - NEG. -NÚMERO DE NEGÓCIOS EFETUADOS COM O PAPEL- MERCADO NO PREGÃO
                $paper->totneg = substr($line,147,05);

                //QUATOT -QUANTIDADE TOTAL DE TÍTULOS NEGOCIADOS NESTE PAPEL- MERCADO                                
                $paper->quatot = substr($line,152,18);

                //VOLTOT - VOLUME TOTAL DE TÍTULOS NEGOCIADOS NESTE PAPEL- MERCADO
                $paper->voltot = substr($line,170,16);

                //PREEXE - PREÇO DE EXERCÍCIO PARA O MERCADO DE OPÇÕES OU VALOR DO CONTRATO PARA O MERCADO DE TERMO SECUNDÁRIO
                $paper->preexe = substr($line,188,11);

                //INDOPC - INDICADOR DE CORREÇÃO DE PREÇOS DE EXERCÍCIOS OU VALORES  E CONTRATO PARA OS MERCADOS DE OPÇÕES OU TERMO SECUNDÁRIO
                $paper->indopc = substr($line,201,1);                

                //DATVEN - DATA DO VENCIMENTO PARA OS MERCADOS DE OPÇÕES OU TERMO SECUNDÁRIO
                $paper->datven = substr($line,202,8);                

                //FATCOT - FATOR DE COTAÇÃO DO PAPEL
                $paper->fatcot = substr($line,210,7);                

                //PTOEXE - PREÇO DE EXERCÍCIO EM PONTOS PARA OPÇÕES REFERENCIADAS EM DÓLAR OU VALOR DE CONTRATO EM PONTOS PARA TERMO SECUNDÁRIO
                $paper->ptoexe = substr($line,217,7);              

                //CODISI - CÓDIGO DO PAPEL NO SISTEMA ISIN OU CÓDIGO INTERNO DO PAPEL
                $paper->codisi = substr($line,230,12);              

                //DISMES - NÚMERO DE DISTRIBUIÇÃO DO PAPEL
                $paper->dismes = substr($line,242,3);              

                $paper->created_at = date("Y-m-d H:i:s");

                $paper->save();
            }

            fclose($file);
        } else {
            Yii::info("[IMPORT] file not found " . $date);
        }
        Yii::info("[IMPORT] end parseDataAndSaveInDatabase data from " . $date);
        return "OK"; 
    }

    public function actionImport($startDate, $endDate)
    {
        $begin =  DateTime::createFromFormat('dmY',$startDate);
        $end =  DateTime::createFromFormat('dmY',$endDate);

        for($i = $begin; $i <= $end; $i->modify('+1 day')){
            $dateFormatted = $i->format("dmY");
            try {
                    $this->downloadData($dateFormatted);
                    $this->extractData($dateFormatted);
                    $this->parseDataAndSaveInDatabase($dateFormatted);
                    echo "\n sucesso na data " . $dateFormatted;    
            } catch(\Exception $e) {
                echo "\n falha na data " . $dateFormatted;
            }

            
        }

        return "importado " ;
    }

    public function actionBackground() {
        Yii::$app->queue->push(new DownloadJob([
            'url' => 'http://example.com/image.jpg2'
        ]));
        return "importado... " ;
    }

    public function actionFind() {
        Yii::info("hi there");
        $paper = Paper::find()->where(["nomres"=>"LOJAS MARISA"])->one();
        return $paper->tpmerc;
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
