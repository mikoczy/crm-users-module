<?php

namespace Crm\UsersModule\Auth\Access;

use Crm\ApplicationModule\Access\AccessManager;
use Crm\ApplicationModule\Request as CrmRequest;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Http\IRequest;
use Nette\Http\Request;
use Nette\Http\Response;

class AccessToken
{
    private $version = 3;

    private $accessTokenRepository;

    private $usersRepository;

    private $accessManager;

    protected $cookieName = 'n_token';

    public function __construct(
        AccessTokensRepository $accessTokensRepository,
        UsersRepository $usersRepository,
        AccessManager $accessManager
    ) {
        $this->accessTokenRepository = $accessTokensRepository;
        $this->usersRepository = $usersRepository;
        $this->accessManager = $accessManager;
    }

    public function addUserToken($user, Request $request = null, Response $response = null)
    {
        $userRow = $this->usersRepository->find($user->id);

        // remove old token if exists
        if ($request) {
            $cookieToken = $request->getCookie($this->cookieName);
            $token = $this->accessTokenRepository->loadToken($cookieToken);
            if ($token && $token->user_id == $userRow->id) {
                $this->accessTokenRepository->remove($token->token);
            }
        }

        $token = $this->accessTokenRepository->add($userRow, $this->version);

        if ($response/* && !\Crm\ApplicationModule\Request::isApi()*/) {
            $response->setCookie(
                $this->cookieName,
                $token->token,
                strtotime('+10 years'),
                '/',
                CrmRequest::getDomain(),
                null,
                false
            );

            $response->setCookie(
                'n_version',
                $this->version,
                strtotime('+10 years'),
                '/',
                CrmRequest::getDomain(),
                null,
                false
            );
        }

        if ($this->accessManager->hasAccess($userRow->id, 'web')) {
            $this->accessTokenRepository->grantUserAccess($userRow);
        }

        return $userRow;
    }

    public function deleteActualUserToken($user, Request $request, Response $response)
    {
        $cookieToken = $request->getCookie($this->cookieName);
        $token = $this->accessTokenRepository->loadToken($cookieToken);
        if ($token && $token->user_id == $user->id) {
            $this->accessTokenRepository->remove($token->token);
        }

        $response->deleteCookie($this->cookieName, '/', CrmRequest::getDomain());
        $response->deleteCookie('n_version', '/', CrmRequest::getDomain());
    }

    public function getToken(IRequest $request)
    {
        return $request->getCookie($this->cookieName);
    }

    public function lastVersion()
    {
        return $this->version;
    }
}
