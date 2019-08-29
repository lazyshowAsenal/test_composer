<?php

namespace Lzx\Utils\Hbase;

ini_set('display_errors', E_ALL);
$GLOBALS['THRIFT_ROOT'] = __DIR__;
/* Dependencies. In the proper order. */
require_once $GLOBALS['THRIFT_ROOT'] . '/Thrift/Transport/TTransport.php';
require_once $GLOBALS['THRIFT_ROOT'] . '/Thrift/Transport/TSocket.php';
require_once $GLOBALS['THRIFT_ROOT'] . '/Thrift/Protocol/TProtocol.php';
require_once $GLOBALS['THRIFT_ROOT'] . '/Thrift/Protocol/TBinaryProtocol.php';
require_once $GLOBALS['THRIFT_ROOT'] . '/Thrift/Protocol/TBinaryProtocolAccelerated.php';
require_once $GLOBALS['THRIFT_ROOT'] . '/Thrift/Transport/TBufferedTransport.php';
require_once $GLOBALS['THRIFT_ROOT'] . '/Thrift/Type/TMessageType.php';
require_once $GLOBALS['THRIFT_ROOT'] . '/Thrift/Factory/TStringFuncFactory.php';
require_once $GLOBALS['THRIFT_ROOT'] . '/Thrift/StringFunc/TStringFunc.php';
require_once $GLOBALS['THRIFT_ROOT'] . '/Thrift/StringFunc/Core.php';
require_once $GLOBALS['THRIFT_ROOT'] . '/Thrift/Type/TType.php';
require_once $GLOBALS['THRIFT_ROOT'] . '/Thrift/Exception/TException.php';
require_once $GLOBALS['THRIFT_ROOT'] . '/Thrift/Exception/TTransportException.php';
require_once $GLOBALS['THRIFT_ROOT'] . '/Thrift/Exception/TProtocolException.php';
require_once $GLOBALS['THRIFT_ROOT'] . '/Hbase/Types.php';
require_once $GLOBALS['THRIFT_ROOT'] . '/Hbase/Hbase.php';
require_once 'helpers.php';
use Thrift\Protocol\TBinaryProtocol;
use Thrift\Transport\TBufferedTransport;
use Thrift\Transport\TSocket;
use Hbase\HbaseClient;
use Hbase\ColumnDescriptor;
use Hbase\Mutation;

/**
 * Class HbaseUtil
 *
 * Version:
 * aliyun-hbase version: alihbase-2.0.3
 * thrift version: 0.9.0
 *
 * @package Feng\Utils\Hbase
 */
class HbaseUtil
{
    private $host;
    private $port;
    private $socket;
    private $transport;
    private $protocol;
    private $client;

    protected $sendTimeOut = 3000;
    protected $recvTimeOut = 3000;

    private static $instance;

    private function __construct($host, $port)
    {
        $this->host= $host;
        $this->port= $port;
        $this->socket = new TSocket($this->host, $this->port);
        $this->socket->setSendTimeout(config('feng_habse.send_time_out', $this->sendTimeOut)); // 发送超时，单位毫秒
        $this->socket->setRecvTimeout(config('feng_habse.recv_time_out', $this->recvTimeOut)); // 接收超时，单位毫秒
        $this->transport = new TBufferedTransport($this->socket);
        $this->protocol = new TBinaryProtocol($this->transport);
        $this->client = new HbaseClient($this->protocol);
        $this->transport->open();
    }

    private function __clone(){}

    public static function getInstance()
    {
        if (!self::$instance instanceof self) {

            $host = config('feng_hbase.host');
            $port = config('feng_hbase.port');

            self::$instance = new self($host, $port);
        }

        return self::$instance;
    }

    /**
     * list tables
     *
     * @return mixed|null
     */
    public function listTables()
    {
        return $this->client->getTableNames();
    }

    /**
     * get one row
     *
     * @param $tableName
     * @param $rowKey
     * @param array $attributes
     *
     * @return array
     */
    public function getOneByRowKey($tableName, $rowKey, array $attributes = [])
    {
        $rows = $this->client->getRow($tableName, $rowKey, $attributes);

        if (sizeof($rows) > 0){
            return (array) $rows[0];
        }

        return [];
    }

    /**
     * insert one data
     *
     * @param      $tableName
     * @param      $mutations
     * $mutations = array(
     *    new Mutation(array(
     *       'column' => 'info:cn1',
     *        'value' => $value
     *   )),
     *);
     * @param null $rowKey
     * @param array $attributes
     *
     */
    public function insertOne($tableName, $mutations, $rowKey = null, array $attributes = [])
    {
        $this->client->mutateRow($tableName, $rowKey, $mutations, $attributes);
    }

    /**
     * delete by rowKey
     *
     * @param       $tableName
     * @param       $rowKey
     * @param array $attributes
     */
    public function deleteByRowKey($tableName, $rowKey, array $attributes)
    {
        $this->client->deleteAllRow($tableName, $rowKey, $attributes);
    }

    /**
     * scan by prefix
     *
     * @param      $tableName
     * @param      $startAndPrefix
     * @param int $nbRows 最大返回条数
     * @param array $columns
     * @param array $attributes
     *
     * @return array
     */
    public function scannerOpenWithPrefix($tableName, $startAndPrefix, $nbRows = 100, $columns = [], $attributes =
    [])
    {
        $scan = $this->client->scannerOpenWithPrefix($tableName, $startAndPrefix, $columns, $attributes);

        $rows = $this->client->scannerGetList($scan, $nbRows);

        return array_map(function($row){
            return (array) $row;
        }, $rows);
    }

    /**
     * range scan
     * return data does not include $stopRow data , [$startRow, $stopRow)
     *
     * @param $tableName
     * @param $startRow
     * @param $stopRow
     * @param int $nbRows, 返回数据最大条数，类似perpage
     * @param $columns
     * @param $attributes
     *
     * @return array
     */
    public function scannerOpenWithStop($tableName, $startRow, $stopRow, $nbRows = 100, $columns = [], $attributes =
    [])
    {
        $scan = $this->client->scannerOpenWithStop($tableName, $startRow, $stopRow, $columns, $attributes);

        $rows = $this->client->scannerGetList($scan, $nbRows);

        return array_map(function($row){
            return (array) $row;
        }, $rows);
    }

    /**
     * 获取表存储的regions
     *
     * @param $tableName
     *
     * @return mixed|null
     */
    public function getTableRegions($tableName)
    {
        return $this->client->getTableRegions($tableName);
    }

    /**
     * 获取表结构
     *
     * @param $tableName
     *
     * @return mixed|null
     */
    public function getColumnDescriptors($tableName)
    {
        return $this->client->getColumnDescriptors($tableName);
    }

    /**
     * multi get
     *
     * @param       $tableName
     * @param array $rows
     * @param null  $attributes
     *
     * @return mixed|null
     */
    public function getManyByRowKeys($tableName, array $rows, $attributes = null)
    {
        return $this->client->getRows($tableName, $rows, $attributes);
    }

    /**
     * 扫描表
     *
     * @param        $tableName
     * @param string $startRow
     * @param int    $nbRows
     * @param array  $columns
     * @param array  $attributes
     *
     * @return array
     */
    public function scannerOpen($tableName, $startRow = '', $nbRows = 100, $columns = [], $attributes = [])
    {
        $scan = $this->client->scannerOpen($tableName, $startRow, $columns, $attributes);

        $rows = $this->client->scannerGetList($scan, $nbRows);

        return array_map(function($row){
            return (array) $row;
        }, $rows);
    }
}