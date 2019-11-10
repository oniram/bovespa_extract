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
use yii\queue\Queue;
use yii\mongodb\Query;
use ZipArchive;
use Datetime;
use DateInterval;
use DatePeriod;
use yii\helpers\ArrayHelper;

class SiteController extends Controller
{
    
   

    public function actionImportToDatabase()
    {
         ini_set('max_execution_time', 0); //300 seconds = 5 minutes

        Yii::debug("[IMPORT] start");
      
        try{
            $this->parseDataAndSaveInDatabase(null, null);
        } catch(\Exception $e) {
            Yii::debug("[IMPORT] falha");
        }
    }

    public function actionDownloadAndExtract($startDate, $endDate, $type = 'day')
    {
        
        ini_set('max_execution_time', 0); //300 seconds = 5 minutes

        Yii::debug("[IMPORT] start");
        
        $begin =  DateTime::createFromFormat('dmY',$startDate);
        $end =  DateTime::createFromFormat('dmY',$endDate);

        if($type == 'day') {
            $format = 'dmY';
            $typeFromDownload = 'D';
        } else {
            $format = 'Y';
            $typeFromDownload = 'A';
        }
       
        
        for($i = $begin; $i <= $end; $i->modify('+1 ' . $type)){

            $dateFormatted = $i->format($format);

            try {
                   $this->downloadData($dateFormatted, $typeFromDownload);
                   $this->extractData($dateFormatted);
                    Yii::debug("[IMPORT]sucesso na data " . $dateFormatted);    
                    
            } catch(\Exception $e) {
                Yii::debug("[IMPORT]falha na data " . $dateFormatted . " " . $e->getMessage());
                
            }

            
        }

        return "importado " ;
    }



     public function downloadData($date, $type) {
        Yii::debug("[IMPORT] start download data from " . $date);
        
        
        $file_path = '/tmp/' . $date . '.zip';
        $fh = fopen($file_path, 'w');
        $client = new Client([
            'transport' => 'yii\httpclient\CurlTransport'
        ]);
        
        $response = $client->createRequest()
            ->setMethod('GET')
            ->setUrl('http://bvmf.bmfbovespa.com.br/InstDados/SerHist/COTAHIST_' . $type . $date . '.zip')
            ->setOutputFile($fh)
            ->send();
        Yii::debug("[IMPORT] end download data from " . $date);
        
    }    

    public function extractData($date) {
        Yii::debug("[IMPORT] start extractData data from " . $date);
        
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
        Yii::debug("[IMPORT] end extractData data from " . $date);
        
    }


    public function parseDataAndSaveInDatabase($date, $type) {
        Yii::debug("[IMPORT] start parseDataAndSaveInDatabase data from " . $date);
        
        $papers = [];
        $i = 0;
        $batch_size = 10000;
    
        $files = glob('/home/oniram/workspace/bovespa_extract/data/splited/*.txt');
        foreach($files as $filepath) {
            Yii::debug("[IMPORT] start file  " . $filepath);
          $file = fopen($filepath, 'r');
          if ($file) {

            while (($line = fgets($file)) !== false) {
                if($i == $batch_size) {

                    $rows = ArrayHelper::getColumn($papers, 'attributes');

                    $collection = Yii::$app->mongodb->getCollection('papers');

                    $insertedRows = $collection->batchInsert($rows);
                    $i = 0;
                    $rows = [];
                    $papers = [];

                }
               

                try {

                    if(strpos($line, 'COTAHIST') !== false) {
                        continue;
                    }

                    $line = mb_convert_encoding($line, 'US-ASCII', 'UTF-8');
                    $paper = new Paper();

                    // 2 parametro do substr - posicao inicial a ser lida na linha
                    //(1 numero a menos do que especidicado no manual)
                    // 3 parametro do substr - qtd caracteres a ser lido a partir da posicao inicial

                    
                    //DATA DO PREGÃO
                    $dateTime = \DateTime::createFromFormat('YmdHis', substr($line,2,8).'000000');
                    
                    
                    
                    $paper->date = new \MongoDB\BSON\UTCDateTime($dateTime);

                    //CODBDI - CÓDIGO BDI
                    //UTILIZADO PARA CLASSIFICAR OS PAPÉIS NA EMISSÃO DO BOLETIM DIÁRIO DE INFORMAÇÕES
                    $paper->codbdi = str_replace(' ', '', substr($line,10,2));

                    //CODNEG - CÓDIGO DE NEGOCIAÇÃO DO PAPEL
                    $paper->codneg = str_replace(' ', '', substr($line,12,12));

                    //TPMERC - TIPO DE MERCADO
                    //CÓD. DO MERCADO EM QUE O PAPEL ESTÁ CADASTRADO
                    $paper->tpmerc = str_replace(' ', '', substr($line,24,03));

                    //NOMRES - NOME RESUMIDO DA EMPRESA EMISSORA DO PAPEL
                    $paper->nomres = str_replace(' ', '', substr($line,27,12));

                    //ESPECI - ESPECIFICAÇÃO DO PAPEL
                    // TODO remover espacos apenas do inicio e no final da string
                    $paper->especi = str_replace(' ', '', substr($line,39,10));

                    //PRAZOT - PRAZO EM DIAS DO MERCADO A TERMO
                    $paper->prazot = str_replace(' ', '', substr($line,49,3));

                    //MODREF - MOEDA DE REFERÊNCIA
                    $paper->modref = str_replace(' ', '', substr($line,52,4));

                    //PREABE - PREÇO DE ABERTURA DO PAPEL- MERCADO NO PREGÃO
                    $paper->preab = (float) substr_replace(substr($line,56,13), ".", 11, 0 );

                    //PREMAX - PREÇO MÁXIMO DO PAPEL- MERCADO NO PREGÃO
                    $paper->premax = (float) substr_replace(substr($line,69,13), ".", 11, 0 );

                    //PREMIN - PREÇO MÍNIMO DO PAPEL- MERCADO NO PREGÃO
                    $paper->premin = (float) substr_replace(substr($line,82,13), ".", 11, 0 );           

                    //PREMED - PREÇO MÉDIO DO PAPEL- MERCADO NO PREGÃO
                    $paper->premed = (float) substr_replace(substr($line,95,13), ".", 11, 0 );                           
                    //PREULT - PREÇO DO ÚLTIMO NEGÓCIO DO PAPEL-MERCADO NO PREGÃO
                    $paper->preult = (float) substr_replace(substr($line,108,13), ".", 11, 0 );

                    //PREOFC - PREÇO DA MELHOR OFERTA DE COMPRA DO PAPEL- MERCADO
                    $paper->preofc = (float) substr_replace(substr($line,121,13), ".", 11, 0 );

                    //PREOFV - PREÇO DA MELHOR OFERTA DE VENDA DO PAPEL- MERCADO
                    $paper->preofv = (float) substr_replace(substr($line,134,13), ".", 11, 0 );            

                    //TOTNEG - NEG. -NÚMERO DE NEGÓCIOS EFETUADOS COM O PAPEL- MERCADO NO PREGÃO
                    $paper->totneg = str_replace(' ', '', substr($line,147,05));

                    //QUATOT -QUANTIDADE TOTAL DE TÍTULOS NEGOCIADOS NESTE PAPEL- MERCADO                                
                    $paper->quatot = str_replace(' ', '', substr($line,152,18));

                    //VOLTOT - VOLUME TOTAL DE TÍTULOS NEGOCIADOS NESTE PAPEL- MERCADO
                    $paper->voltot = str_replace(' ', '', substr($line,170,16));

                    //PREEXE - PREÇO DE EXERCÍCIO PARA O MERCADO DE OPÇÕES OU VALOR DO CONTRATO PARA O MERCADO DE TERMO SECUNDÁRIO
                    $paper->preexe = str_replace(' ', '', substr($line,188,11));

                    //INDOPC - INDICADOR DE CORREÇÃO DE PREÇOS DE EXERCÍCIOS OU VALORES  E CONTRATO PARA OS MERCADOS DE OPÇÕES OU TERMO SECUNDÁRIO
                    $paper->indopc = str_replace(' ', '', substr($line,201,1));                

                    //DATVEN - DATA DO VENCIMENTO PARA OS MERCADOS DE OPÇÕES OU TERMO SECUNDÁRIO
                    $paper->datven = str_replace(' ', '', substr($line,202,8));                

                    //FATCOT - FATOR DE COTAÇÃO DO PAPEL
                    $paper->fatcot = str_replace(' ', '', substr($line,210,7));                

                    //PTOEXE - PREÇO DE EXERCÍCIO EM PONTOS PARA OPÇÕES REFERENCIADAS EM DÓLAR OU VALOR DE CONTRATO EM PONTOS PARA TERMO SECUNDÁRIO
                    $paper->ptoexe = str_replace(' ', '', substr($line,217,7));              

                    //CODISI - CÓDIGO DO PAPEL NO SISTEMA ISIN OU CÓDIGO INTERNO DO PAPEL
                    $paper->codisi = str_replace(' ', '', substr($line,230,12));              

                    //DISMES - NÚMERO DE DISTRIBUIÇÃO DO PAPEL
                    $paper->dismes = str_replace(' ', '', substr($line,242,3));              

                    $paper->created_at = new \MongoDB\BSON\UTCDateTime(new DateTime());
                        
                    $papers[$i] = $paper;
                    $i = $i+1;

                } catch(Exception $e) {
                    Yii::debug("[IMPORT] error");
                }
                

            }
            
            fclose($file);
             
             
            $rows = ArrayHelper::getColumn($papers, 'attributes');
            
            $collection = Yii::$app->mongodb->getCollection('papers');
            
            $insertedRows = $collection->batchInsert($rows);
            $i = 0;
            $rows = [];
            $papers = [];
        } else {
            Yii::debug("[IMPORT] file not found " . $date);
            
        }
        }
        
        
        return "OK"; 
    }

    public function actionFind() {
        //nomeres
        $rows = [];

        $startDay = \DateTime::createFromFormat('YmdHis', '20181016000000');
        $endDay = \DateTime::createFromFormat('YmdHis', '20191015000000');

        $d1=$this->toIsoDate($startDay->getTimestamp());
        $d2=$this->toIsoDate($endDay->getTimestamp());  
        
        //ITUB4
        //02/01/2013  a 15/10/2019
        // menor preco 23 no dia 15/01/2016
        // maior preco 53 no dia 26/01/2018

        //16/10/2018 a 15/10/2019
        //maior preco em 16/11/2018 - 52,75
        //menor preco em 16/05/2019 - 31,44
        //dividir em 6 intervalos de 3,56 (arredondei em 0,01)
        //ex: 31  <= x < 34,5
        $papers = Paper::find()->where(["codneg"=>"ITUB4", "tpmerc" => "010"])
        ->andWhere(['>=', 'date', $d1])->andWhere(['<=', 'date', $d2])
        ->orderBy(["preult" => SORT_ASC])->all()
        ;

        $states = ["0" => 0, "1" => 0, "2" => 0, "3" => 0, "4" => 0, "5" => 0];

        foreach($papers as $paper) {
            if($paper->preult < 35) {
                $paper->state = 0;    
            }
            if($paper->preult >= 35 && $paper->preult < 38.56) {
                $paper->state = 1;    
            }
            if($paper->preult >= 38.56 && $paper->preult < 42.12) {
                $paper->state = 2;    
            }
            if($paper->preult >= 42.12 && $paper->preult < 45.68) {
                $paper->state = 3;    
            }
            if($paper->preult >= 45.68 && $paper->preult < 49.24) {
                $paper->state = 4;    
            }
            if($paper->preult >= 49.24) {
                $paper->state = 5;    
            }
            $states[$paper->state] += 1;
        }

        $matrix = array_fill(0, 6, array_fill(0, 6, 0));
        $count = 0;
        foreach($papers as $paper) {
            if($count != 0) {
                $matrix[$oldPaper->state][$paper->state] += 1;    
            } 
            $oldPaper = $paper;
            $count += 1;
        }

        
        return $this->render('find', [
            'matrix' => $matrix,
            'states' => $states
        ]);

    
    }

    public function toIsoDate($timestamp){
        return new \MongoDB\BSON\UTCDateTime($timestamp * 1000);
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
