<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\ApiModule\Api\ApiHandler;
use Nette\Http\Response;

class UserInfoHandler extends ApiHandler
{
    public function params()
    {
        return [];
    }

    public function handle(ApiAuthorizationInterface $authorization)
    {
        $data = $authorization->getAuthorizedData();
        if (!isset($data['token'])) {
            $response = new JsonResponse(['status' => 'error', 'message' => 'Cannot authorize user']);
            $response->setHttpCode(Response::S403_FORBIDDEN);
            return $response;
        }

        $user = $data['token']->user;

        $result = [
            'status' => 'ok',
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
            ],
        ];

        $response = new JsonResponse($result);
        $response->setHttpCode(Response::S200_OK);
        return $response;
    }
}
