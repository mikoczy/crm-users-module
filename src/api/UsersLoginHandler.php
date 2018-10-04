<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Params\ParamsProcessor;
use Crm\UsersModule\Auth\UserAuthenticator;
use Crm\UsersModule\Repository\AccessTokensRepository;
use League\Event\Emitter;
use Nette\Http\Response;
use Nette\Security\AuthenticationException;

class UsersLoginHandler extends ApiHandler
{
    /** @var UserAuthenticator  */
    private $userAuthenticator;

    /** @var AccessTokensRepository  */
    private $accessTokensRepository;

    /** @var Emitter  */
    private $emitter;

    public function __construct(UserAuthenticator $userAuthenticator, AccessTokensRepository $accessTokensRepository, Emitter $emitter)
    {
        $this->userAuthenticator = $userAuthenticator;
        $this->accessTokensRepository = $accessTokensRepository;
        $this->emitter = $emitter;
    }

    public function params()
    {
        return [
            new InputParam(InputParam::TYPE_POST, 'email', InputParam::REQUIRED),
            new InputParam(InputParam::TYPE_POST, 'password', InputParam::REQUIRED),
            new InputParam(InputParam::TYPE_POST, 'source', InputParam::OPTIONAL)
        ];
    }

    public function handle(ApiAuthorizationInterface $authorization)
    {
        $paramsProcessor = new ParamsProcessor($this->params());

        $params = $paramsProcessor->getValues();

        if (!isset($params['source']) && isset($_GET['source'])) {
            $params['source'] = $_GET['source'];
        }

        if (!$params['email']) {
            $response = new JsonResponse(['status' => 'error', 'error' => 'no_email', 'message' => 'No valid email']);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        }

        if (!$params['password']) {
            $response = new JsonResponse(['status' => 'error', 'error' => 'no_password', 'message' => 'No valid password']);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        }

        try {
            $source = 'api';
            if ($params['source'] && $params['source'] != 'api') {
                $source .= '+' . $params['source'];
            }
            $identity = $this->userAuthenticator->authenticate([
                'username' => $params['email'],
                'password' => $params['password'],
                'source' => $source,
            ]);
        } catch (AuthenticationException $authException) {
            $message = $authException->getMessage();
            if ($authException->getCode() == UserAuthenticator::IDENTITY_NOT_FOUND) {
                $message = 'Zadaný e-mail sa nezhoduje s našimi záznamami. Prihláste sa, prosím, tak, ako na webe Denníka N.';
            } elseif ($authException->getCode() == UserAuthenticator::INVALID_CREDENTIAL) {
                $message = 'Zadané heslo sa nezhoduje s našimi záznamami. Prihláste sa, prosím, tak, ako na webe Denníka N.';
            }
            $response = new JsonResponse(['status' => 'error', 'error' => 'auth_failed', 'message' => $message]);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        }

        $result = [
            'status' => 'ok',
            'user' => [
                'id' => $identity->id,
                'email' => $identity->data['email'],
                'first_name' => $identity->data['first_name'],
                'last_name' => $identity->data['last_name'],
            ],
        ];

        if ($identity->getRoles()) {
            $result['user']['roles'] = $identity->getRoles();
        }

        $lastToken = $this->accessTokensRepository->allUserTokens($identity->id)->limit(1)->fetch();

        if ($lastToken) {
            $result['access']['token'] = $lastToken->token;
        }

        $response = new JsonResponse($result);
        $response->setHttpCode(Response::S200_OK);
        return $response;
    }
}
