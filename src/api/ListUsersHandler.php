<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Params\ParamsProcessor;
use Crm\ApiModule\Api\ApiHandler;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Utils\Json;
use Nette\Utils\JsonException;

class ListUsersHandler extends ApiHandler
{
    const PAGE_SIZE = 1000;

    private $request;

    private $usersRepository;

    public function __construct(Request $request, UsersRepository $usersRepository)
    {
        $this->request = $request;
        $this->usersRepository = $usersRepository;
    }

    public function params()
    {
        return [
            new InputParam(InputParam::TYPE_POST, 'user_ids', InputParam::REQUIRED),
            new InputParam(InputParam::TYPE_POST, 'page', InputParam::REQUIRED),
        ];
    }

    /**
     * @param ApiAuthorizationInterface $authorization
     * @return \Nette\Application\IResponse
     */
    public function handle(ApiAuthorizationInterface $authorization)
    {
        $paramsProcessor = new ParamsProcessor($this->params());
        $params = $paramsProcessor->getValues();

        if (!$params['user_ids']) {
            $response = new JsonResponse(['status' => 'error', 'error' => 'missing_param', 'message' => 'missing required parameter: user_ids']);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        }

        if (!$params['page']) {
            $response = new JsonResponse(['status' => 'error', 'error' => 'missing_param', 'message' => 'missing required parameter: page']);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        }

        $query = $this->usersRepository->getTable()
            ->select('id, email')
            ->order('id ASC');

        try {
            $userIds = Json::decode($params['user_ids'], Json::FORCE_ARRAY);
            if (!empty($userIds)) {
                $query->where(['id' => $userIds]);
            }
        } catch (JsonException $e) {
            $response = new JsonResponse(['status' => 'error', 'message' => 'user_ids should be valid JSON array']);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        }

        $users = (clone($query))
            ->limit(self::PAGE_SIZE, ($params['page']-1) * self::PAGE_SIZE);
        $count = (clone($query))
            ->count('*');
        $totalPages = ceil((float)$count / (float)self::PAGE_SIZE);

        $resultArr = [];
        /** @var ActiveRow $user */
        foreach ($users as $user) {
            $resultArr[$user->id] = [
                'id' => $user->id,
                'email' => $user->email,
            ];
        }

        $result = [
            'status' => 'ok',
            'page' => $params['page'],
            'totalPages' => $totalPages,
            'totalCount' => $count,
            'users' => $resultArr,
        ];

        $response = new JsonResponse($result);
        $response->setHttpCode(Response::S200_OK);
        return $response;
    }
}
