<?php

/*
 *
 *
 * (c) Allen, Li <morningbuses@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Goodcatch\FXK;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\SeekException;
use GuzzleHttp\Psr7\Message;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Cache;


/**
 * Class FXK
 * @package Goodcatch\FXK
 */
class FXK
{

    const CACHE_KEY_CORPACCESSTOKEN_EXPIRESIN = 'fxiaoke_corpAccessToken_expiresIn';

    /**
     * https://open.fxiaoke.com/cgi/
     *
     * @var string API URL
     */
    private $url;

    /**
     * @var string app key
     */
    private $appId;

    /**
     * @var string app secret
     */
    private $appSecret;

    /**
     * @var string permanent code
     */
    private $permanentCode;

    /**
     * @var string admin user open id
     */
    private $adminUser;

    /**
     * @var Client http client
     */
    private $client;

    /**
     * @var array search criteria
     */
    private $criteria;

    /**
     * @var array corpAccessToken from connection with appId appSecret permanentCode
     */
    private $corpAccessToken = null;

    /**
     * @var Filter filter
     */
    private $filter;
    /**
     * @var mixed
     */
    private $token;
    /**
     * @var mixed
     */
    private $encodingAesKey;

    /**
     * Guanyi constructor.
     * @param array $config guanyi config
     */
    public function __construct(array $config)
    {
        $this->appId = $config ['appId'];
        $this->appSecret = $config ['appSecret'];
        $this->permanentCode = $config ['permanentCode'];
        $this->adminUser = $config ['adminUser'];
        $this->url = $config ['url'];
        $this->token = $config ['token'];
        $this->encodingAesKey = $config ['encodingAesKey'];

        $this->client = new Client([
            'timeout' => $config ['timeout'],
        ]);
    }

    /**
     * @param Client $client
     * @return FXK
     */
    public function setHttpClient (Client $client)
    {
        $this->client = $client;
        return $this;
    }


    public function query ()
    {
        $this->criteria = [];
        return $this;
    }

    /**
     * add criteria
     * @param string $search
     * @param $value
     * @return $this
     */
    public function criteria (string $search, $value)
    {
        if (isset ($this->criteria))
        {
            $this->criteria [$search] = $value;
        }
        return $this;
    }

    /**
     * @param Request $request
     * @param boolean clear criteria, filter
     * @return Model
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function exec(Request $request, $clear = true): Model
    {
        $result = null;
        try {
            $response = $this->client->send($request);
            if (!is_null($response) && !empty ($response) && $response->getStatusCode() === 200) {
                $body = $response->getBody();

                $result = $this->handleResp(\GuzzleHttp\json_decode($body->getContents(), true));
            }
        } catch (RequestException $e) {
            $result = new Model;
            $result->exception = [urldecode(Message::toString($e->getRequest()))];
            if ($e->hasResponse()) {
                $result->exception [] = urldecode(Message::toString($e->getResponse()));
            }
        } finally {
            if ($clear)
            {
                if (isset ($this->criteria))
                {
                    unset ($this->criteria);
                }
                if (isset ($this->filter))
                {
                    unset ($this->filter);
                }
            }
        }

        return $result;
    }

    /**
     * 模型转换
     *
     * @param array $result
     * @return Model
     */
    private function handleResp(array $result): Model
    {
        return new Model($result);
    }

    private function transform (Model $model, $collection):Model
    {
        $transform = new Model;

        $transform->data = $collection ?? \collect ([]);
        $transform->errorCode = $model->errorCode;
        $transform->errorMessage = $model->errorMessage;
        $transform->errorDescription = $model->errorDescription;

        // got error
        if (isset ($model->exception))
        {
            $transform->exception = $model->exception;
        }
        return $transform;
    }

    /**
     * make request
     *
     * @param string $method
     * @param array|null $req
     * @return Request
     */
    private function request(string $method, array $req = []): Request
    {
        return new Request('POST', $this->url . $method, [
            'Content-Type' => 'application/json;charset=utf-8',
        ], \GuzzleHttp\json_encode ($req));
    }

    /**
     * Get model by parameters
     *
     * @param string $method
     * @param array $params
     * @return Model
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function getModel (string $method, array $params = []): Model
    {

        $params = array_merge ($params, $this->getCorpAccessToken ());


        if (isset ($this->criteria) && count ($this->criteria) > 0)
        {
            $params = array_merge ($this->criteria, $params);
        }

        return $this->exec (
            $this->request ($method, $params)
        );
    }

    /**
     * Get model by parameter key-value
     *
     * @param string $method
     * @param string|null $param_key
     * @param string|null $param_val
     * @param array $params
     * @return Model
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function getModelByParameter (string $method, string $param_key = null, string $param_val = null, array $params = []): Model
    {
        if (! is_null($param_val) && ! is_null ($param_key))
        {
            $params [$param_key] = $param_val;
        }
        return $this->getModel ($method, $params);
    }

    /**
     * Get model by parameter key-value
     *
     * @param string $method
     * @param string $currentOpenUserId
     * @param array $params
     * @return Model
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function getModelByAdminUser (string $method, string $currentOpenUserId = '', array $params = []): Model
    {

        if (empty ($currentOpenUserId))
        {
            $currentOpenUserId = $this->adminUser;
        }

        return $this->getModel ($method, $this->withAdminUserOpenId ($currentOpenUserId, $params));
    }


    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function freshCorpAccessToken ()
    {
        if (is_null ($this->corpAccessToken))
        {

            $model = $this->exec (
                $this->request ('corpAccessToken/get/V2', [
                    'appId' => $this->appId,
                    'appSecret' => $this->appSecret,
                    'permanentCode' => $this->permanentCode
                ]),
                false
            );
            if ($model->errorCode == 0)
            {
                $this->corpAccessToken = $model->toArray ();
                Cache::put (self::CACHE_KEY_CORPACCESSTOKEN_EXPIRESIN, $this->corpAccessToken, $this->corpAccessToken ['expiresIn']);
            }
        } else if (! Cache::has (self::CACHE_KEY_CORPACCESSTOKEN_EXPIRESIN)) {
            $this->corpAccessToken = null;
            $this->getCorpAccessToken ();
        }
    }

    /**
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function getCorpAccessToken (): array
    {
        $this->freshCorpAccessToken ();

        if (! is_null ($this->corpAccessToken)) {
            return [
                'corpId' => $this->corpAccessToken ['corpId'],
                'corpAccessToken' => $this->corpAccessToken ['corpAccessToken']
            ];
        }
        return [];
    }

    /**
     * add additional parameter admin user open id
     *
     * @param $currentOpenUserId
     * @param array $params
     * @return array
     */
    private function withAdminUserOpenId ($currentOpenUserId, array $params = [])
    {
        if (! empty ($currentOpenUserId))
        {
            $params ['currentOpenUserId'] = $currentOpenUserId;
        }

        return $params;
    }

    /**
     * @return Filter
     */
    public function filter ()
    {
        if (! isset ($this->filter))
        {
            $this->filter = new Filter ($this);
        }
        return $this->filter;
    }

    /**
     * 通讯录管理-获取部门列表
     * department/list
     *
     * @return Model
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getDepartments (): Model
    {

        $model = $this->getModel('department/list');

        return $this->transform ($model, $model->departments);

    }

    /**
     * @param int $departmentId
     * @param boolean $fetchChild
     * @return Model
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getUsers (int $departmentId = 0, $fetchChild = true): Model
    {

        $this->query()
            ->criteria('fetchChild', $fetchChild)
            ->criteria('departmentId', $departmentId)
        ;

        $model = $this->getModel ('user/list');

        return $this->transform ($model, $model->userList);

    }


    /**
     * @param string $openUserId
     * @return Model
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getUser (string $openUserId = ''): Model
    {

        return $this->getModelByParameter ('user/get', 'openUserId', $openUserId);
    }

    /**
     * @param mixed search
     * @param int $departmentId
     * @return Model
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function findUser (string $search, int $departmentId = 0): Model
    {
        $model = $this->getUsers($departmentId);
        if ($model->data->count() > 0)
        {
            return $model->data
                ->filter (function ($item) use ($search) {

                    return $item->employeeNumber === $search
                        || $item->mobile === $search
                        || $item->email === $search
                        || $item->name === $search
                        || $item->account === $search
                        || $item->nickName === $search
                        || $item->birthDate === $search
                        ;
                })
                ->first ();
        }
        return $model;
    }

    /**
     * @param array $users
     * @param array $msg
     * @param string $msgType
     * @return Model
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function sendMessage ($users, $msg, $msgType='text'): Model
    {

        $this->query ()
            ->criteria ($msgType, $msg)
            ->criteria ('toUser', $users)
            ->criteria ('msgType', $msgType)
        ;

        return $this->getModel ('message/send');

    }




    /**
     *  CRM Object List, depend on admin user
     *
     * @param string $currentOpenUserId
     * @return Model
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getObjectList ($currentOpenUserId = ''): Model
    {
        return $this->getModelByAdminUser ('crm/v2/object/list', $currentOpenUserId);
    }

    /**
     *  CRM Object description, depend on admin user
     *
     * @param string $apiName
     * @param string $currentOpenUserId
     * @return Model
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getObjectDesc ($apiName, $currentOpenUserId = ''): Model
    {

        $this->query ()->criteria ('apiName', $apiName);

        return $this->getModelByAdminUser ('crm/v2/object/describe', $currentOpenUserId);

    }

    /**
     *
     * @param array $data 对象数据map ["object_data"=>[]]
     * @param string $currentOpenUserId
     * @param boolean $triggerWorkFlow 是否需要触发工作流(默认不传时为true)
     * @return Model
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function addCRMCustomObject ($data, $currentOpenUserId = '', $triggerWorkFlow = false): Model
    {

        $this->query ()
            ->criteria ('triggerWorkFlow', $triggerWorkFlow)
            ->criteria ('data', $data)
        ;

        return $this->getModelByAdminUser ('crm/custom/data/create', $currentOpenUserId);

    }

    /**
     *
     * @param string $apiName
     * @param string $currentOpenUserId
     * @param int $offset integer that larger than 0
     * @param int $limit max 100
     * @return Model
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getCRMCustomObject ($apiName, $currentOpenUserId = '', $offset = 1, $limit = 100): Model
    {
        $this->query ()
            ->criteria ('data', [
                'dataObjectApiName' => $apiName,
                'search_query_info' => [
                    'limit' => $limit, // max 100
                    'offset' => $offset, // integer large than 0
                    'filters' => $this->filter ()->build (),
                    'orders' => $this->filter ()->buildOrder ()
                ]
                // , 'fieldProjection' => []

            ])
        ;

        return $this->getModelByAdminUser ('crm/custom/data/query', $currentOpenUserId);

    }

    /**
     * @param $sign
     * @param $timeStamp
     * @param $nonce
     * @param $content
     * @return array
     * 解密
     */
    public function MsgCrypt($sign, $timeStamp, $nonce, $content){
        $mc = new MsgCrypt($this->token, $this->encodingAesKey);
        $msg = $mc->decryptMsg($sign, $timeStamp, $nonce, $content);
        if ($msg == -1) {
           $data=['code'=>-1,'msg'=>'解密失败'];
        } else {
            $data=['code'=>1,'msg'=>$msg];
        }
        return $data;

    }


}