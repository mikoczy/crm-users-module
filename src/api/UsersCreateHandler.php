<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Params\ParamsProcessor;
use Crm\UsersModule\Auth\InvalidEmailException;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Crm\UsersModule\Repository\GroupsRepository;
use Crm\UsersModule\Repository\UserAlreadyExistsException;
use Crm\UsersModule\Repository\UserGroupsRepository;
use Nette\Http\Response;
use Nette\Utils\Validators;

class UsersCreateHandler extends ApiHandler
{
    private $userManager;

    private $accessTokensRepository;

    private $userGroupsRepository;

    private $groupsRepository;

    /** @var int */
    private $newsletterGroupId = 10;

    /** @var int */
    private $mofaAppUserGroupId = 11;

    /** @var int */
    private $freeClanokGroupId = 12;

    public function __construct(
        UserManager $userManager,
        AccessTokensRepository $accessTokensRepository,
        UserGroupsRepository $userGroupsRepository,
        GroupsRepository $groupsRepository
    ) {
        $this->userManager = $userManager;
        $this->accessTokensRepository = $accessTokensRepository;
        $this->userGroupsRepository = $userGroupsRepository;
        $this->groupsRepository = $groupsRepository;
    }

    public function params()
    {
        return [
            new InputParam(InputParam::TYPE_POST, 'email', InputParam::REQUIRED),
            new InputParam(InputParam::TYPE_POST, 'source', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'referer', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'send_email', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'disable_email_validation', InputParam::OPTIONAL),
        ];
    }

    public function handle(ApiAuthorizationInterface $authorization)
    {
        $paramsProcessor = new ParamsProcessor($this->params());
        $params = $paramsProcessor->getValues();

        if (!isset($params['source']) && isset($_GET['source'])) {
            $params['source'] = $_GET['source'];
        }

        $email = $params['email'];
        if (!$email) {
            $response = new JsonResponse(['status' => 'error', 'message' => 'Invalid email', 'code' => 'invalid_email']);
            $response->setHttpCode(Response::S404_NOT_FOUND);
            return $response;
        }
        if (!Validators::isEmail($email)) {
            $response = new JsonResponse(['status' => 'error', 'message' => 'Invalid email', 'code' => 'invalid_email']);
            $response->setHttpCode(Response::S404_NOT_FOUND);
            return $response;
        }

        $user = $this->userManager->loadUserByEmail($email);
        if ($user) {
            $response = new JsonResponse(['status' => 'error', 'message' => 'Email is already taken', 'code' => 'email_taken']);
            $response->setHttpCode(Response::S404_NOT_FOUND);
            return $response;
        }

        $source = 'api';
        if ($params['source'] && strlen($params['source']) > 0) {
            $source = $params['source'];
        }

        // specialny hack
        if ($source == 'api') {
            $source = 'freeclanok';
        }
        $referer = null;
        if (isset($params['referer']) && $params['referer']) {
            $referer = $params['referer'];
        }

        $sendEmail = true;
        if ($params['send_email']) {
            $sendEmail = filter_var($params['send_email'], FILTER_VALIDATE_BOOLEAN);
        }

        $checkEmail = true;
        if (isset($params['disable_email_validation']) && ($params['disable_email_validation'] == '1' || $params['disable_email_validation'] == 'true')) {
            $checkEmail = false;
        }

        try {
            $user = $this->userManager->addNewUser($email, $sendEmail, $source, $referer, $checkEmail);
        } catch (InvalidEmailException $e) {
            $response = new JsonResponse(['status' => 'error', 'message' => 'Invalid email', 'code' => 'invalid_email']);
            $response->setHttpCode(Response::S404_NOT_FOUND);
            return $response;
        } catch (UserAlreadyExistsException $e) {
            $response = new JsonResponse(['status' => 'error', 'message' => 'Email is already taken', 'code' => 'email_taken']);
            $response->setHttpCode(Response::S404_NOT_FOUND);
            return $response;
        }

        if ($source == 'newsletter') {
            $group = $this->groupsRepository->find($this->newsletterGroupId);
            if ($group) {
                $this->userGroupsRepository->addToGroup($group, $user);
            }
        }

        if ($source == 'freeclanok') {
            $group = $this->groupsRepository->find($this->freeClanokGroupId);
            if ($group) {
                $this->userGroupsRepository->addToGroup($group, $user);
            }
        }

        // pouzivatelov registrovanych cez appku priradime to samostatnej skupiny
        if ($source == 'dennikn_mobile_app') {
            $group = $this->groupsRepository->find($this->mofaAppUserGroupId);
            if ($group) {
                $this->userGroupsRepository->addToGroup($group, $user);
            }
        }

        $result = [
            'status' => 'ok',
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
            ],
        ];

        $lastToken = $this->accessTokensRepository->allUserTokens($user->id)->limit(1)->fetch();

        if ($lastToken) {
            $result['access']['token'] = $lastToken->token;
        }

        $response = new JsonResponse($result);
        $response->setHttpCode(Response::S200_OK);
        return $response;
    }
}
