<?php

namespace Crm\UsersModule\Auth\Rate;

interface RateLimitInterface
{
    public function check(array $credentials): bool;
}
