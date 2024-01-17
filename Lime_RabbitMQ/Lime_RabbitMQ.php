<?php

require_once(__DIR__ . "/vendor/autoload.php");

use LimeSurvey\PluginManager\PluginManager;

// RabbitMQ
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Class LSNextcloud
 */
class Lime_RabbitMQ extends PluginBase
{
    /**
     * @var string
     */
    static protected $description = 'RabbitMQ Plugin';

    /**
     * @var string
     */
    static protected $name = 'Lime_RabbitMQ';

    /**
     * @var string
     */
    protected $storage = 'DbStorage';

    /**
     * @var string[][]
     */
    protected $settings = [];

    public function __construct(PluginManager $manager, $id)
    {
        $this->settings = [
            // General
            // 'path' => array(
            //     'type' => 'string',
            //     'label' => $this->gT('Path to save'),
            //     'default' => 'LimeSurvey',
            //     'help' => $this->gT('The folders with the files for the survey will be saved in this folder.')
            // ),
            /*
             *      ANSWERS
             */
            'heading_answers' => array(
                'type' => 'info',
                'content' => '<h1>' . $this->gT('Answers') . '</h1><p>' . $this->gT('Please provide settings in case you want to export answer files.') . '</p>'
            ),
            'exportAnswers' => array(
                'type' => 'boolean',
                'label' => $this->gT('Export answers'),
                'default' => true,
                'help' => $this->gT('Export answers to a file.')
            ),
            'filename_answers' => array(
                'type' => 'string',
                'label' => $this->gT('Filename Answers'),
                'default' => 'answers',
                'help' => $this->gT('The filename will be used to save the file.')
            ),
            'filetypeArray_answers' => array(
                'type' => 'select',
                'htmlOptions' => array(
                    'multiple' => true,
                    'placeholder' => $this->gT("None"),
                    'unselectValue' => "",
                ),
                'selectOptions' => array(
                    'placeholder' => $this->gT("Select all exported file types."),
                ),
                'options' => array(
                    'pdf' => 'PDF',
                    'csv' => 'CSV',
                    'xls' => 'XLS',
                    'doc' => 'DOC',
                    'json' => 'JSON',
                ),
                'label' => $this->gT('Filetypes Answers'),
                'default' => array('xls'),
            ),
            /*
             *      TEXTOVERVIEW
             */
            'heading_textoverview' => array(
                'type' => 'info',
                'content' => '<h1>' . $this->gT('Textoverview') . '</h1><p>' . $this->gT('Please provide settings in case you want to export textoverview files.') . '</p>'
            ),
            'exportTextoverview' => array(
                'type' => 'boolean',
                'label' => $this->gT('Export textoverview'),
                'default' => false,
                'help' => $this->gT('Export textoverview to a file.')
            ),
            'filename_textoverview' => array(
                'type' => 'string',
                'label' => $this->gT('Filename Textoverview'),
                'default' => 'overview',
                'help' => $this->gT('The filename will be used to save the file.')
            ),
            /*
             *      STATISTICS
             */
            'heading_statistics' => array(
                'type' => 'info',
                'content' => '<h1>' . $this->gT('Statistics') . '</h1><p>' . $this->gT('Please provide settings in case you want to export statistics files.') . '</p>'
            ),
            'exportStatistics' => array(
                'type' => 'boolean',
                'label' => $this->gT('Export statistics'),
                'default' => false,
                'help' => $this->gT('Export statistics to a file.')
            ),
            'filename_statistics' => array(
                'type' => 'string',
                'label' => $this->gT('Filename Statistics'),
                'default' => 'statistics',
                'help' => $this->gT('The filename will be used to save the file.')
            ),
            'filetypeArray_statistics' => array(
                'type' => 'select',
                'htmlOptions' => array(
                    'multiple' => true,
                    'placeholder' => $this->gT("None"),
                    'unselectValue' => "",
                ),
                'selectOptions' => array(
                    'placeholder' => $this->gT("Select all exported file types."),
                ),
                'options' => array(
                    'pdf' => 'PDF',
                    'xls' => 'XLS',
                    'html' => 'HTML',
                ),
                'label' => $this->gT('Filetypes Statistics'),
                'default' => array('pdf'),
            ),
            'graph_statistics' => array(
                'type' => 'boolean',
                'label' => $this->gT('Statistics Graphs'),
                'default' => true,
                'help' => $this->gT('Draw graphs inside statistics files.')
            ),
            /*
             *      RABBITMQ
             */
            'heading_rabbitmq' => array(
                'type' => 'info',
                'content' => '<h1>' . $this->gT('RabbitMQ') . '</h1><p>' . $this->gT('Please provide the following settings.') . '</p>'
            ),
            'mq_host' => array(
                'type' => 'string',
                'label' => $this->gT('RabbitMQ host'),
                'default' => 'rabbitmq',
            ),
            'mq_port' => array(
                'type' => 'string',
                'label' => $this->gT('RabbitMQ port'),
                'default' => 5672,
            ),
            'mq_user' => array(
                'type' => 'string',
                'label' => $this->gT('RabbitMQ user'),
                'default' => 'user',
            ),
            'mq_password' => array(
                'type' => 'password',
                'label' => $this->gT('RabbitMQ Password'),
                'default' => 'password',
            ),
            'info_save' => array(
                'type' => 'info',
                'content' => $this->gT('You need to click save, even if you want to use the default settings.')
            ),
        ];
        parent::__construct($manager, $id);
    }

    /**
     * @return void
     */
    public function init()
    {
        $this->subscribe('newSurveySettings');
        $this->subscribe('afterSurveyComplete');
        $this->subscribe('beforeSurveySettings');

        $this->subscribe('newDirectRequest');
    }

    /**
     * @return void
     */
    public function newDirectRequest()
    {
        if (!$this->getEvent()) {
            throw new CHttpException(403);
        }
        if ($this->getEvent()->get('target') != get_class($this)) {
            return;
        }
        if (!Permission::model()->hasGlobalPermission('superadmin')) {
            throw new CHttpException(403);
        }
        $surveyId = App()->getRequest()->getQuery('sid', App()->getRequest()->getQuery('surveyId'));
        if ($surveyId && App()->getRequest()->getQuery('method') == 'updateTable') {
            $this->updateTable($surveyId);
            App()->end(); // Not needed but more clear
        }
    }

    private function _getSurveyField($surveyId, $fieldName, $withFallback = true)
    {
        return $this->get(
            $fieldName,
            'Survey',
            $surveyId,
            $withFallback ? $this->get($fieldName) : null
        );
    }

    private function _buildMessage($surveyId)
    {
        $data = new stdClass();
        $data->id = $surveyId;

        // $path = $this->get('path');
        // $data->path = $path;

        $exportAnswers = $this->_getSurveyField($surveyId, 'exportAnswers');
        if ($exportAnswers) {
            $answers = new stdClass();
            $answers->filename = $this->_getSurveyField($surveyId, 'filename_answers');
            $answers->filetypeArray = $this->_getSurveyField($surveyId, 'filetypeArray_answers');
            $answers->questionArray = $this->get('questionArray', 'Survey', $surveyId, array());
            $data->answers = $answers;
        }

        $exportTextoverview = $this->_getSurveyField($surveyId, 'exportTextoverview');
        if ($exportTextoverview) {
            $textoverview = new stdClass();
            $textoverview->filename = $this->_getSurveyField($surveyId, 'filename_textoverview');
            $textoverview->questionArray = $this->get('questionArray_textoverview', 'Survey', $surveyId, array());
            $data->textoverview = $textoverview;
        }

        $exportStatistics = $this->_getSurveyField($surveyId, 'exportStatistics');
        if ($exportStatistics) {
            $statistics = new stdClass();
            $statistics->filename = $this->_getSurveyField($surveyId, 'filename_statistics');
            $statistics->filetypeArray = $this->_getSurveyField($surveyId, 'filetypeArray_statistics');
            $statistics->graph = $this->_getSurveyField($surveyId, 'graph_statistics');
            $data->statistics = $statistics;
        }

        return $data;
    }

    public function updateTable($surveyId)
    {
        // SEND DATA
        try {
            $exchange = 'router';
            $queue = 'msgs';
            $connection = new AMQPStreamConnection($this->get('mq_host'), $this->get('mq_port'), $this->get('mq_user'), $this->get('mq_password'));
            $channel = $connection->channel();
            $channel->queue_declare($queue, false, true, false, false);
            $channel->exchange_declare($exchange, AMQPExchangeType::DIRECT, false, true, false);
            $channel->queue_bind($queue, $exchange);
            $data = $this->_buildMessage($surveyId);
            $message = new AMQPMessage(json_encode($data), array('content_type' => 'application/json', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT));
            $channel->basic_publish($message, $exchange);
        } catch (Exception $e) {
            $this->log($e);
        }
    }

    /**
     * @return void
     */
    public function afterSurveyComplete()
    {
        $surveyId = $this->getEvent()->get('surveyId');
        $enable = $this->_getSurveyField($surveyId, 'enable', false);
        if (!$enable) {
            return;
        }
        $exportAnswers = $this->_getSurveyField($surveyId, 'exportAnswers');
        $exportTextoverview = $this->_getSurveyField($surveyId, 'exportTextoverview');
        $exportStatistics = $this->_getSurveyField($surveyId, 'exportStatistics');
        if (!$exportAnswers && !$exportStatistics && !$exportTextoverview) {
            return;
        }
        $this->updateTable($surveyId);
    }

    private function _getFieldName($question)
    {
        $fieldName = $question->GetBasicFieldName();
        if ($question->parent_qid != 0) {
            $fieldName .= $question->title;
        }
        return $fieldName;
    }

    /**
     * @return void
     */
    public function beforeSurveySettings()
    {
        $event = $this->getEvent();
        $url = Yii::app()->createUrl(
            'plugins/direct',
            array(
                'plugin' => get_class($this),
                'method' => 'updateTable',
                'surveyId' => $event->get('survey')
            )
        );
        $surveyId = $event->get('survey');
        $oSurvey = Survey::model()->findByPk($surveyId);
        $sLanguage = $oSurvey->language;

        $questionArrayBasics = array(
            "id" => $this->gT("ID"),
            "submitdate" => $this->gT("Submitdate"),
            "lastpage" => $this->gT("Lastpage"),
            "startlanguage" => $this->gT("Startlanguage"),
            "seed" => $this->gT("Seed"),
            "startdate" => $this->gT("Startdate"),
            "datestamp" => $this->gT("Datestamp"),
        );
        $questionArrayFields = array();
        $questions = $oSurvey->getAllQuestions();
        foreach ($questions as $q) {
            $fieldName = $this->_getFieldName($q);
            $title = $q->title;
            if (isset($q->questionl10ns) && isset($q->questionl10ns[$sLanguage])) {
                $title = $q->questionl10ns[$sLanguage]->question;
            }
            // Dont add this field if its type F (Array) type Q (Multiple Short Text)
            if ($q->type != "F" && $q->type != "Q") {
                $questionArrayFields[$fieldName] = $title;
            }
            // If there is an other field -> add one more for the other field specification
            if ($q->other == 'Y') {
                $questionArrayFields[$fieldName . 'other'] = $title . ' - ' . $this->gT('Other');
            }
        }
        ksort($questionArrayFields);
        $questionArray = array_merge($questionArrayBasics, $questionArrayFields);

        $event->set(
            "surveysettings.{$this->id}",
            [
                'name' => get_class($this),
                'settings' => [
                    'heading_plugin' => [
                        'type' => 'info',
                        'content' => '<h1>' . $this->gT('Plugin Settings') . '</h1>'
                    ],
                    'enable' => [
                        'type' => 'checkbox',
                        'label' => $this->gT('Enable plugin'),
                        'current' => $this->get(
                            'enable',
                            'Survey',
                            $surveyId,
                            null
                        ),
                        'help' => $this->gT('Send the SurveyID to RabbitMQ after completion.')
                    ],
                    'send_now' => [
                        'type' => 'link',
                        'label' => $this->gT('Send Update'),
                        'link' => $url,
                        'help' => $this->gT('Send the SurveyID to RabbitMQ now. This updates the table inside of nextcloud.')
                    ],
                    /*
                     *      ANSWERS
                     */
                    'heading_answers' => array(
                        'type' => 'info',
                        'content' => '<h1>' . $this->gT('Answers') . '</h1><p>' . $this->gT('Please provide settings in case you want to export answer files.') . '</p>'
                    ),
                    'exportAnswers' => array(
                        'type' => 'boolean',
                        'label' => $this->gT('Export answers'),
                        'current' => $this->get(
                            'exportAnswers',
                            'Survey',
                            $surveyId,
                            $this->get('exportAnswers')
                        ),
                        'help' => $this->gT('Export answers to a file.')
                    ),
                    'filename_answers' => array(
                        'type' => 'string',
                        'label' => $this->gT('Filename Answers'),
                        'current' => $this->get(
                            'filename_answers',
                            'Survey',
                            $surveyId,
                            $this->get('filename_answers')
                        ),
                        'help' => $this->gT('The filename will be used to save the file.')
                    ),
                    'filetypeArray_answers' => array(
                        'type' => 'select',
                        'htmlOptions' => array(
                            'multiple' => true,
                            'placeholder' => $this->gT("None"),
                            'unselectValue' => "",
                        ),
                        'selectOptions' => array(
                            'placeholder' => $this->gT("Select all exported file types."),
                        ),
                        'options' => array(
                            'pdf' => 'PDF',
                            'csv' => 'CSV',
                            'xls' => 'XLS',
                            'doc' => 'DOC',
                            'json' => 'JSON',
                        ),
                        'label' => $this->gT('Filetypes Answers'),
                        'current' => $this->get(
                            'filetypeArray_answers',
                            'Survey',
                            $surveyId,
                            $this->get('filetypeArray_answers')
                        ),
                    ),
                    'questionArray' => array(
                        'type' => 'select',
                        'options' => $questionArray,
                        'htmlOptions' => array(
                            'multiple' => true,
                            'placeholder' => $this->gT("None"),
                            'unselectValue' => "",
                        ),
                        'selectOptions' => array(
                            'placeholder' => $this->gT("None"),
                        ),
                        'label' => $this->gT('All Question / Table Fields that should be present.'),
                        'current' => $this->get('questionArray', 'Survey', $surveyId, array_keys($questionArray)),
                    ),
                    /*
                     *      TEXTOVERVIEW
                     */
                    'heading_textoverview' => array(
                        'type' => 'info',
                        'content' => '<h1>' . $this->gT('Textoverview') . '</h1><p>' . $this->gT('Please provide settings in case you want to export textoverview files.') . '</p>'
                    ),
                    'exportTextoverview' => array(
                        'type' => 'boolean',
                        'label' => $this->gT('Export textoverview'),
                        'current' => $this->get(
                            'exportTextoverview',
                            'Survey',
                            $surveyId,
                            $this->get('exportTextoverview')
                        ),
                        'help' => $this->gT('Export textoverview to a file.')
                    ),
                    'filename_textoverview' => array(
                        'type' => 'string',
                        'label' => $this->gT('Filename Textoverview'),
                        'current' => $this->get(
                            'filename_textoverview',
                            'Survey',
                            $surveyId,
                            $this->get('filename_textoverview')
                        ),
                        'help' => $this->gT('The filename will be used to save the file.')
                    ),
                    'questionArray_textoverview' => array(
                        'type' => 'select',
                        'options' => $questionArrayFields,
                        'htmlOptions' => array(
                            'multiple' => true,
                            'placeholder' => $this->gT("None"),
                            'unselectValue' => "",
                        ),
                        'selectOptions' => array(
                            'placeholder' => $this->gT("None"),
                        ),
                        'label' => $this->gT('All Question / Table Fields that should get an text overview generated.'),
                        'current' => $this->get('questionArray_textoverview', 'Survey', $surveyId, array_keys($questionArrayFields)),
                    ),
                    /*
                     *      STATISTICS
                     */
                    'heading_statistics' => array(
                        'type' => 'info',
                        'content' => '<h1>' . $this->gT('Statistics') . '</h1><p>' . $this->gT('Please provide settings in case you want to export statistics files.') . '</p>'
                    ),
                    'exportStatistics' => array(
                        'type' => 'boolean',
                        'label' => $this->gT('Export statistics'),
                        'current' => $this->get(
                            'exportStatistics',
                            'Survey',
                            $surveyId,
                            $this->get('exportStatistics')
                        ),
                        'help' => $this->gT('Export statistics to a file.')
                    ),
                    'filename_statistics' => array(
                        'type' => 'string',
                        'label' => $this->gT('Filename Statistics'),
                        'current' => $this->get(
                            'filename_statistics',
                            'Survey',
                            $surveyId,
                            $this->get('filename_statistics')
                        ),
                        'help' => $this->gT('The filename will be used to save the file.')
                    ),
                    'filetypeArray_statistics' => array(
                        'type' => 'select',
                        'htmlOptions' => array(
                            'multiple' => true,
                            'placeholder' => $this->gT("None"),
                            'unselectValue' => "",
                        ),
                        'selectOptions' => array(
                            'placeholder' => $this->gT("Select all exported file types."),
                        ),
                        'options' => array(
                            'pdf' => 'PDF',
                            'xls' => 'XLS',
                            'html' => 'HTML',
                        ),
                        'label' => $this->gT('Filetypes Statistics'),
                        'current' => $this->get(
                            'filetypeArray_statistics',
                            'Survey',
                            $surveyId,
                            $this->get('filetypeArray_statistics')
                        ),
                    ),
                    'graph_statistics' => array(
                        'type' => 'boolean',
                        'label' => $this->gT('Statistics Graphs'),
                        'current' => $this->get(
                            'graph_statistics',
                            'Survey',
                            $surveyId,
                            $this->get('graph_statistics')
                        ),
                        'help' => $this->gT('Draw graphs inside statistics files.')
                    ),
                ]
            ]
        );
    }

    /**
     * @return void
     */
    public function newSurveySettings()
    {
        $event = $this->getEvent();

        foreach ($event->get('settings') as $name => $value) {
            $this->set($name, $value, 'Survey', $event->get('survey'));
        }
    }
}
