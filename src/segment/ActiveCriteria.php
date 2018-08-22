<?php

namespace Crm\UsersModule\Segment;

use Crm\ApplicationModule\Criteria\CriteriaInterface;
use Crm\SegmentModule\Params\BooleanParam;
use Crm\SegmentModule\Params\ParamsBag;

class ActiveCriteria implements CriteriaInterface
{
    public function label(): string
    {
        return "Active";
    }

    public function category(): string
    {
        return "Users";
    }

    public function params(): array
    {
        return [
            new BooleanParam('active', true, true),
        ];
    }

    public function join(ParamsBag $params): string
    {
        return "SELECT id, users.created_at AS user_created_at  FROM users WHERE active = " . $params->boolean('active')->number();
    }

    public function title(ParamsBag $params): string
    {
        if ($params->boolean('active')->isTrue()) {
            return ' active';
        } else {
            return ' inactive';
        }
    }

    public function fields(): array
    {
        return ['users.created_at' => 'user_created_at'];
    }
}
