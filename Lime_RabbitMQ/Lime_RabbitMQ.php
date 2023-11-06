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
            'path' => [
                'type' => 'string',
                'label' => gT('Path to save'),
                'default' => 'LimeSurvey',
                'help' => gT('The files will be saved in this folder.')
            ],
            'info' => array(
                'type' => 'info',
                'content' => '<h1>' . gT('RabbitMQ') . '</h1><p>' . gT('Please provide the following settings.') . '</p>'
            ),
            'mq_host' => array(
                'type' => 'string',
                'label' => gT('RabbitMQ host'),
                'default' => 'rabbitmq',
            ),
            'mq_port' => array(
                'type' => 'string',
                'label' => gT('RabbitMQ port'),
                'default' => 5672,
            ),
            'mq_user' => array(
                'type' => 'string',
                'label' => gT('RabbitMQ user'),
                'default' => 'user',
            ),
            'mq_password' => array(
                'type' => 'password',
                'label' => gT('RabbitMQ Password'),
                'default' => 'password',
            ),
            'info' => array(
                'type' => 'info',
                'content' => gT('You need to click save, even if you want to use the default settings.')
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
    }

    /**
     * @return void
     */
    public function afterSurveyComplete()
    {
        $enable =
            $this->get(
                'enable',
                'Survey',
                $this->getEvent()->get('surveyId'),
                null
            );
        if (!$enable) {
            return;
        }

        // SEND DATA
        try {
            $exchange = 'router';
            $queue = 'msgs';
            $connection = new AMQPStreamConnection($this->get('mq_host'), $this->get('mq_port'), $this->get('mq_user'), $this->get('mq_password'));
            $channel = $connection->channel();
            $channel->queue_declare($queue, false, true, false, false);
            $channel->exchange_declare($exchange, AMQPExchangeType::DIRECT, false, true, false);
            $channel->queue_bind($queue, $exchange);
            $data = new stdClass();
            $data->id = $this->getEvent()->get('surveyId');
            $message = new AMQPMessage(json_encode($data), array('content_type' => 'application/json', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT));
            $channel->basic_publish($message, $exchange);
        } catch (Exception $e) {
            $this->log($e);
        }
    }

    /**
     * @return void
     */
    public function beforeSurveySettings()
    {
        $event = $this->getEvent();
        $event->set(
            "surveysettings.{$this->id}",
            [
                'name' => get_class($this),
                'settings' => [
                    'Info' => [
                        'type' => 'info',
                        'content' => '<h1>' . gT('RabbitMQ') . '</h1>'
                    ],
                    'enable' => [
                        'type' => 'checkbox',
                        'label' => gT('Enable plugin'),
                        'current' => $this->get(
                            'enable',
                            'Survey',
                            $event->get('survey'),
                            null
                        ),
                        'help' => gT('Send the SurveyID to RabbitMQ after completion.')
                    ],
                    // 'qustions' => [
                    //     'type' => 'list',
                    //     'label' => gT('Fragen'),
                    //     'items' => array("a" => array('type' => 'string', 'label' => "Huhu")),
                    // ]
                ]
            ]
        );
        // print_r($event->get('survey') . '<br>');
        // foreach ($this->api->getQuestions($event->get('survey'), 'de-informal') as $q) {
        //     print_r($q->title . ' ' . $q->preg . ' ' . $q->GetBasicFieldName() . '<br>');
        //     print_r($q->getQuestionAttribute('label')->value . '<br>');
        // }
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
