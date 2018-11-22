<?php

namespace Crm\UsersModule\Auth;

use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\UsersModule\Repository\AccessTokensRepository;
use League\Event\Emitter;
use Nette\Security\IAuthorizator;

class UserTokenAuthorization implements ApiAuthorizationInterface
{
    protected $accessTokensRepository;

    /** @var ApiAuthorizationInterface */
    protected $authorizator = null;

    /** @var ApiAuthorizationInterface[] */
    protected $authorizators = [];

    protected $emitter;

    public function __construct(
        AccessTokensRepository $accessTokensRepository,
        Emitter $emitter
    ) {
        $this->accessTokensRepository = $accessTokensRepository;
        $this->emitter = $emitter;
    }

    public function registerAuthorizator(string $source, ApiAuthorizationInterface $authorizator)
    {
        $this->authorizators[$source] = $authorizator;
    }

    public function authorized($resource = IAuthorizator::ALL)
    {
        if (isset($_GET['source']) && isset($this->authorizators[$_GET['source']])) {
            $this->authorizator = $this->authorizators[$_GET['source']];
        } else {
            $this->authorizator = new DefaultUserTokenAuthorization($this->accessTokensRepository, $this->emitter);
        }

        return $this->authorizator->authorized($resource);
    }

    public function getErrorMessage()
    {
        if (is_null($this->authorizator)) {
            return 'Authorize token first - use `authorized` method.';
        }
        return $this->authorizator->getErrorMessage();
    }

    public function getAuthorizedData()
    {
        if (is_null($this->authorizator)) {
            return 'Authorize token first - use `authorized` method.';
        }
        return $this->authorizator->getAuthorizedData();
    }
}
