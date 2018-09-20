<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Params\ParamsProcessor;
use Crm\UsersModule\Auth\UserManager;
use Nette\Http\Response;

class UsersConfirmApiHandler extends ApiHandler
{
    private $userManager;

    public function __construct(
        UserManager $userManager
    ) {
        $this->userManager = $userManager;
    }

    public function params()
    {
        return [
            new InputParam(InputParam::TYPE_POST, 'email', InputParam::REQUIRED),
        ];
    }

    public function handle(ApiAuthorizationInterface $authorization)
    {
        $paramsProcessor = new ParamsProcessor($this->params());
        if ($err = $paramsProcessor->isError()) {
            $response = new JsonResponse(['status' => 'error', 'message' => 'wrong request parameters: ' . $err]);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        }

        $params = $paramsProcessor->getValues();
        $user = $this->userManager->loadUserByEmail($params['email']);

        if (!$user) {
            $response = new JsonResponse(['status' => 'ok']);
            $response->setHttpCode(Response::S200_OK);
            return $response;
        }

        $this->userManager->confirmUser($user);

        $response = new JsonResponse(['status' => 'ok']);
        $response->setHttpCode(Response::S200_OK);
        return $response;
    }
}
