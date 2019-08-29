<?php

namespace Feng\Utils\Queue\CMQ;

use Log;

require_once 'cmq/cmq_api.php';
require_once CMQAPI_ROOT_PATH.'/account.php';
require_once CMQAPI_ROOT_PATH.'/topic.php';
require_once CMQAPI_ROOT_PATH.'/subscription.php';
require_once CMQAPI_ROOT_PATH.'/cmq_exception.php';

class CMQUtil
{
    const CMQ_QUEUE_NO_MESSAGE_CODE = 7000;

    const CMQ_TOPIC_TAG_FILTER_NAME     = 'msgtag';
    const CMQ_TOPIC_ROUTING_FILTER_NAME = 'routing';

    //新增操作
    public static $OPERATION_TYPE_ADD = 'add';

    //删除操作
    public static $OPERATION_TYPE_DEL = 'del';

    //修改操作
    public static $OPERATION_TYPE_MOD = 'mod';

    private $topicAccount;

    private $queueAccount;

    /**
     * CMQ工具类
     *
     * @param $topicHost
     * @param $queueHost
     * @param $secredID
     * @param $secretKey
     */
    public function __construct($topicHost, $queueHost, $secretID, $secretKey)
    {
        $this->topicAccount = new \Account($topicHost, $secretID, $secretKey);
        $this->queueAccount = new \Account($queueHost, $secretID, $secretKey);
    }

    ######################################################
    #主题相关
    ######################################################
    /**
     * 根据主题名称获取主题实例
     *
     * @param $topicName
     *
     * @return Topic
     */
    public function getTopic($topicName)
    {

        $topic = $this->topicAccount->get_topic($topicName);

        return $topic;
    }


    ######################################################
    #队列相关
    ######################################################
    /**
     * 根据队列名称获取队列实例
     *
     * @param $queueName
     *
     * @return \CMQQueue
     */
    public function getQueue($queueName)
    {

        $queue = $this->queueAccount->get_queue($queueName);

        return $queue;
    }

    /**
     * 根据队列名称创建队列实例
     *
     * @param $queueName           string
     * @param $maxMsgHeapNum       int
     * @param $pollingWaitSeconds  int
     * @param $visibilityTimeout   int
     * @param $maxMsgSize          int
     * @param $msgRetentionSeconds int
     * @param $rewindSeconds       int
     *
     * @return \CMQQueue
     * @throws \Exception
     */
    public function createQueue(
        $queueName
        , $maxMsgHeapNum = null
        , $pollingWaitSeconds = null
        , $visibilityTimeout = null
        , $maxMsgSize = null
        , $msgRetentionSeconds = null
        , $rewindSeconds = null
    ) {
        try {
            $queue                          = $this->getQueue($queueName);
            $queueMeta                      = new \CMQQueueMeta();
            $queueMeta->maxMsgHeapNum       = $maxMsgHeapNum ?? $queueMeta->maxMsgHeapNum;
            $queueMeta->pollingWaitSeconds  = $pollingWaitSeconds ?? $queueMeta->pollingWaitSeconds;
            $queueMeta->visibilityTimeout   = $visibilityTimeout ?? $queueMeta->visibilityTimeout;
            $queueMeta->maxMsgSize          = $maxMsgSize ?? $queueMeta->maxMsgSize;
            $queueMeta->msgRetentionSeconds = $msgRetentionSeconds ?? $queueMeta->msgRetentionSeconds;
            $queueMeta->rewindSeconds       = $rewindSeconds ?? $queueMeta->rewindSeconds;

            $queue->create($queueMeta);

            Log::info(sprintf('cmq_util_create_queue queue_name %s',
                $queueName));

            return $queue;
        } catch (\Exception $e) {

            $err = sprintf('创建队列失败 队列:%s 错误信息:%s',
                $queueName, $e->getMessage());

            throw new \Exception($err);
        }
    }

    /**
     * 根据队列名称删除队列实例
     *
     * @param $queueName string
     *
     * @return \CMQQueue
     * @throws \Exception
     */
    public function deleteQueue(
        $queueName
    ) {
        try {
            $queue = $this->getQueue($queueName);

            $queue->delete();

            Log::info(sprintf('cmq_util_delete_queue queue_name %s',
                $queueName));

            return $queue;
        } catch (\Exception $e) {

            $err = sprintf('删除队列失败 队列:%s 错误信息:%s',
                $queueName, $e->getMessage());

            throw new \Exception($err);
        }
    }


    ######################################################
    #订阅相关
    ######################################################
    /**
     * 根据主题名称和订阅名称获取订阅实例
     *
     * @param $topicName
     * @param $subscriptionName
     *
     * @return \Subscription $subscription
     */
    public function getSubscription($topicName, $subscriptionName)
    {
        $subscription = $this->queueAccount->get_subscription($topicName, $subscriptionName);

        return $subscription;
    }

    /**
     * 根据主题名称和订阅名称创建订阅实例
     *
     * @param $topicName           string
     * @param $subscriptionName    string
     * @param $protocol            string
     * @param $endpoint            string
     * @param $tag                 array
     * @param $bindingKey          array
     * @param $notifyContentFormat string queue模式只支持SIMPLIFIED
     * @param $notifyStrategy      string
     *
     * @return \Subscription $subscription
     * @throws \Exception
     */
    public function createSubscription(
        $topicName
        , $subscriptionName
        , $protocol
        , $endpoint
        , $tag = null
        , $bindingKey = null
        , $notifyContentFormat = null
        , $notifyStrategy = null
    ) {
        try {
            $subscription = $this->getSubscription($topicName, $subscriptionName);

            $subscriptionMeta                      = new \SubscriptionMeta();
            $subscriptionMeta->Protocol            = $protocol;
            $subscriptionMeta->Endpoint            = $endpoint;
            $subscriptionMeta->FilterTag           = $tag ?? $subscriptionMeta->FilterTag;
            $subscriptionMeta->bindingKey          = $bindingKey ?? $subscriptionMeta->bindingKey;
            $subscriptionMeta->NotifyContentFormat = $notifyContentFormat ?? $subscriptionMeta->NotifyContentFormat;
            $subscriptionMeta->NotifyStrategy      = $notifyStrategy ?? $subscriptionMeta->NotifyStrategy;

            $subscription->create($subscriptionMeta);

            Log::info(sprintf('cmq_util_create_subscription topic_name %s subscription_name %s protocol %s endpoint %s filter_tag %s binding_key %s',
                $topicName, $subscriptionName, $protocol, $endpoint, json_encode($tag), json_encode($bindingKey)));

            return $subscription;
        } catch (\Exception $e) {
            $err = sprintf('创建订阅失败 主题:%s 订阅名:%s 错误信息:%s',
                $topicName, $subscriptionName, $e->getMessage());

            throw new \Exception($err);
        }
    }

    /**
     * 根据主题名称和订阅名称删除订阅实例
     *
     * @param $topicName        string
     * @param $subscriptionName string
     *
     * @return \Subscription $subscription
     * @throws \Exception
     */
    public function deleteSubscription($topicName, $subscriptionName)
    {
        try {
            $subscription = $this->getSubscription($topicName, $subscriptionName);

            $subscription->delete();

            Log::info(sprintf('cmq_util_delete_subscription topic_name %s subscription_name',
                $topicName, $subscriptionName));

            return $subscription;
        } catch (\Exception $e) {
            $err = sprintf('删除订阅失败 主题:%s 订阅名:%s 错误信息:%s',
                $topicName, $subscriptionName, $e->getMessage());

            throw new \Exception($err);
        }
    }

    ######################################################
    #消息相关
    ######################################################
    /**
     * 发消息消息到指定的主题中
     *
     * @param      $operationType 操作类型 add 新增数据  del 删除数据 mod 修改数据
     * @param      $dataID        数据ID
     * @param      $data          数据
     * @param      $topicName     主题名称
     * @param null $vTagList
     * @param null $routingKey
     *
     * @throws \Exception
     */
    public function publish($operationType, $dataID, $data, $topicName, $vTagList = null, $routingKey = null)
    {

        try {
            $topic = $this->getTopic($topicName);

            $msg = json_encode(
                [
                    'operation_type' => $operationType,
                    'id'             => $dataID,
                    'publish_ip'     => get_local_ip(),
                    'timestamp'      => time(),
                    'data'           => $data,
                ]
            );

            $msgInfo = $topic->publish_message($msg, $vTagList, $routingKey);

            Log::info(sprintf('cmq_util_publish msg_id %s operation_type %s id %s data %s topic_name %s tag_list %s routeing_key %s',
                array_get($msgInfo, 'msgId', ''), $operationType, $dataID, json_encode($data), $topicName, json_encode($vTagList), $routingKey));
        } catch (\Exception $e) {

            $err = sprintf('发送消息失败 操作:%s 数据ID:%s 数据:%s 主题:%s 标签:%s 路由:%s 错误信息:%s',
                $operationType, $dataID, json_encode($data), $topicName, json_encode($vTagList), $routingKey, $e->getMessage());

            throw new \Exception($err);
        }
    }

    /**
     * 向队列发送消息
     *
     * @param int    $operationType
     * @param int    $dataID
     * @param array  $data
     * @param string $queueName
     *
     * @throws \Exception
     */
    public function sendMessage($operationType, $dataID, $data, $queueName)
    {

        try {
            $queue = $this->getQueue($queueName);

            $msg = json_encode(
                [
                    'operation_type' => $operationType,
                    'id'             => $dataID,
                    'publish_ip'     => get_local_ip(),
                    'timestamp'      => time(),
                    'data'           => $data,
                ]
            );
            $message = new \CMQMessage();
            $message->msgBody = $msg;

            $msgInfo = $queue->send_message($message);

            Log::info(sprintf('cmq_util_send_message msg_id %s operation_type %s id %s data %s queue_name %s',
                array_get($msgInfo, 'msgId', ''), $operationType, $dataID, json_encode($data), $queueName));
        } catch (\Exception $e) {

            $err = sprintf('发送消息失败 操作:%s 数据ID:%s 数据:%s 队列:%s 错误信息:%s',
                $operationType, $dataID, json_encode($data), $queueName, $e->getMessage());

            throw new \Exception($err);
        }
    }

    /**
     * 接收消息
     *
     * @param $queueName          队列名称
     * @param $handler            消息处理器，回调方法, 该方法返回值为 true 表示消息处理成功，将删除该消息
     * @param $pollingWaitSeconds 每次获取消息的长轮询等待时间, 单位：秒
     *
     * 示例：
     * FengQueue:receive($queueName, function($msgBody){
     *
     *     return true
     * }, 5);
     *
     */
    public function receive($queueName, $handler, $pollingWaitSeconds = 5)
    {

        $queue = $this->getQueue($queueName);

        while (true) {
            try {
                $message = $queue->receive_message($pollingWaitSeconds);

                $msgId   = $message->msgId;
                $msgBody = $message->msgBody;

                Log::info(sprintf('cmq_util_receive queue_name %s msg_id %s msg_body %s polling_wait_seconds %s',
                    $queueName, $msgId, $msgBody, $pollingWaitSeconds));

                $myMsgBody     = (array)json_decode($msgBody, true);
                $operationType = array_get($myMsgBody, 'operation_type');
                $dataID        = array_get($myMsgBody, 'id');
                $data          = array_get($myMsgBody, 'data');

                $result = $handler($operationType, $dataID, $data);

                Log::info(sprintf('cmq_util_receive handler queue_name %s operation_type %s data_id %s data %s result %s',
                    $queueName, $operationType, $dataID, json_encode($data), $result));

                if ($result == true) {
                    $queue->delete_message($message->receiptHandle);

                    Log::info(sprintf('cmq_util_receive delete_message queue_name %s operation_type %s data_id %s data %s result %s',
                        $queueName, $operationType, $dataID, json_encode($data), $result));
                }
            } catch (\CMQServerException $e) {
                if ($e->getCode() == self::CMQ_QUEUE_NO_MESSAGE_CODE) { //ignore no message
                    continue;
                }

                throw $e;
            } catch (\Exception $e) {

                //如果非未能获取到消息,则记录日志
                if ($e->getMessage() != '(10200)no message') {

                    throw new \Exception(sprintf('接收队列 %s 消息失败 %s',
                        $queueName, $e->getMessage()));
                }
            }
        }
    }

}
