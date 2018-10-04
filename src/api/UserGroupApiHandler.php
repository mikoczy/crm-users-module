<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Params\ParamsProcessor;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\GroupsRepository;
use Crm\UsersModule\Repository\UserGroupsRepository;
use Nette\Http\Request;
use Nette\Http\Response;

class UserGroupApiHandler extends ApiHandler
{
    /** @var UserManager  */
    private $userManager;

    /** @var GroupsRepository  */
    private $groupsRepository;

    /** @var UserGroupsRepository  */
    private $userGroupsRepository;

    /** @var Request  */
    private $request;

    public function __construct(Request $request, UserManager $userManager, GroupsRepository $groupsRepository, UserGroupsRepository $userGroupsRepository)
    {
        $this->request = $request;
        $this->userManager = $userManager;
        $this->groupsRepository = $groupsRepository;
        $this->userGroupsRepository = $userGroupsRepository;
    }

    public function params()
    {
        return [
            new InputParam(InputParam::TYPE_POST, 'email', InputParam::REQUIRED),
            new InputParam(InputParam::TYPE_POST, 'group_id', InputParam::REQUIRED),
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

        $user = $this->userManager->loadUserByEmail($params['email']);
        if (!$user) {
            $result = [
                'status' => 'error',
                'message' => 'User doesn\'t exists',
            ];
            $response = new JsonResponse($result);
            $response->setHttpCode(Response::S404_NOT_FOUND);
            return $response;
        }
        
        $group = $this->groupsRepository->find($params['group_id']);
        if (!$group) {
            $result = [
                'status' => 'error',
                'message' => 'Group doesn\'t exists',
            ];
            $response = new JsonResponse($result);
            $response->setHttpCode(Response::S404_NOT_FOUND);
            return $response;
        }

        if ($this->getAction() == 'add') {
            $this->userGroupsRepository->addToGroup($group, $user);
        } else {
            $this->userGroupsRepository->removeFromGroup($group, $user);
        }

        $result = [
            'status' => 'ok',
        ];

        $response = new JsonResponse($result);
        $response->setHttpCode(Response::S200_OK);

        return $response;
    }

    private function getAction()
    {
        $parts = explode('/', $this->request->getUrl()->getPath());
        if ($parts[count($parts) - 1] == 'add-to-group') {
            return 'add';
        } else {
            return 'remove';
        }
    }
}
