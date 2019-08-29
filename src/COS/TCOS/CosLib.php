<?php

namespace Feng\Utils\COS\TCOS;

use Log;
use Qcloud\Cos\Client;

class CosLib
{
    private $cosClient;
    public function __construct($cosRegion, $secretId, $secretKey)
    {
        $this->cosClient = new \Qcloud\Cos\Client(
            array(
                'region' => $cosRegion,
                'credentials'=> array(
                    'secretId'    => $secretId,
                    'secretKey' => $secretKey
                )
            )
        );
        return;
    }

    public function getBucketAndKey($remotePath){
        $remotePath = urldecode($remotePath);
        preg_match('{^http[s]{0,1}://([^\.]+)\.[^/]*/(.*)$}', $remotePath, $matches);
        if(empty($matches) || count($matches) != 3 || $matches[1] == "" || $matches[2] == ""){
            Log::warning(sprintf('error file format: %s', $remotePath));
            return null;
        }
        return [
            'bucket' => $matches[1],
            'key' => $matches[2],
        ];
    }

    /**
     * 获取文件大小，单位b
     *
     * @param $remotePath 远程文件链接
     * @return 文件大小，文件不存在或者有错误返回null
     * @throws \Exception
     */
    public function getObjectSize($remotePath)
    {
        $attr = $this->getObjectAttributes($remotePath);
        if($attr){
            $value = array_get($attr, 'ContentLength', null);
            if($value){
                return (int)$value;
            }
        }
        return null;
    }

    /**
     * 获取文件属性
     *
     * @param $remotePath 远程文件链接
     * @return mix，有异常返回[]
     * @throws \Exception
     */
    public function getObjectAttributes($remotePath)
    {
        try{
            $fileInfo = $this->getBucketAndKey($remotePath);
            if(!$fileInfo){
                return [];
            }
            $result = $this->cosClient->headObject(
                array(
                    'Bucket' => $fileInfo['bucket'],
                    'Key' => $fileInfo['key'],
                )
            );
            return $result;
        }catch (\Exception $e){
            return [];
        }
    }

    /**
     * 下载远程文件到本地
     * @param $remotePath 远程文件路径
     * @param $localPath 本地文件绝对路径
     * @return true if success , false when error happens
     */
    public function downloadToLocal($remotePath, $localPath)
    {
        try {
            $fileInfo = $this->getBucketAndKey($remotePath);
            if(!$fileInfo){
                Log::error(sprintf('error file format: %s', $remotePath));
                return false;
            }

            $result = $this->cosClient->getObject(
                array(
                    'Bucket' => $fileInfo['bucket'],
                    'Key' => $fileInfo['key'],
                    'SaveAs' => $localPath
                )
            );
            return true;
        } catch (\Exception $e) {
            Log::error(sprintf('COS download error, remote:%s, local:%s, message: %s', 
                       $remotePath, $localPath, $e->getMessage()));
            return false;
        }
    }

    /**
     * 上传本地文件流到云服务
     *
     * @param $filePath 本地文件绝对路径
     * @param $bucket 存储通名称，格式：BucketName-APPID
     * @param string $key 对象键（Key）是对象在存储桶中的唯一标识。
     *        例如，在对象的访问域名 bucket1-1250000000.cos.ap-beijing.myqcloud.com/doc1/pic1.jpg 中，对象键为 doc1/pic1.jpg
     * @param $isDelOriginFile 上传成功后，是否删除本地的原始文件
     * @return mixed
     * @throws \Exception
     */
    public function uploadFile($bucket, $key, $filePath, $isDelOriginFile = true)
    {
        try {
            $result = $this->cosClient->putObject([
                'Bucket' => $bucket,
                'Key' => $key,
                'Body' => fopen($filePath, 'rb')
            ]);

            if ($result->get('ObjectURL') && $isDelOriginFile) {
                unlink($filePath);
            }

            return $result ? $result->toArray() : [];
        } catch (\Exception $e) {
            Log::error('COS upload file error, Cucket:'.$bucket.' Key:'.$key.' filePath:'.$filePath
            .' error:'.$e->getMessage());
            throw $e;
        }
    }

    /**
     * 获取带签名的下载链接,适用于私有读的对象
     * @param $remotePath 远程文件路径，不带签名的
     * @param $expiredMinutes 签名链接失效时间，单位分钟，默认10分钟
     * @return 失败返回null，否则返回签名链接
     */
    public function getSignedUrlByLink($remotePath, $expiredMinutes = 10)
    {
        $fileInfo = $this->getBucketAndKey($remotePath);
        if(!$fileInfo){
            return null;
        }

        return $this->getSignedUrl($fileInfo['bucket'], $fileInfo['key'], $expiredMinutes);
    }

    /**
     * 获取带签名的下载链接,适用于私有读的对象
     * @param $bucket 存储通名称，格式：BucketName-APPID
     * @param string $key 对象键（Key）是对象在存储桶中的唯一标识。
     *        例如，在对象的访问域名 bucket1-1250000000.cos.ap-beijing.myqcloud.com/doc1/pic1.jpg 中，对象键为 doc1/pic1.jpg
     * @param $expiredMinutes 签名链接失效时间，单位分钟，默认10分钟
     * @return 失败返回null，否则返回签名链接
     */
    public function getSignedUrl($bucket, $key, $expiredMinutes = 10)
    {
        try {
            $expiredDesc = "+$expiredMinutes minutes";
            $signedUrl = $this->cosClient->getObjectUrl($bucket, $key, $expiredDesc);
            return $signedUrl;
        } catch (\Exception $e) {
            Log::error(sprintf('COS getSignedUrl error, bucket:%s, key:%s, message: %s', 
                       $bucket, $key, $e->getMessage()));
            return null;
        }
    }

};
