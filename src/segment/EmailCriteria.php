<?php

namespace Crm\UsersModule\Segment;

use Crm\SegmentModule\Params\ParamsBag;
use Crm\ApplicationModule\Criteria\CriteriaInterface;
use Crm\SegmentModule\Params\StringArrayParam;

class EmailCriteria implements CriteriaInterface
{
    public function label(): string
    {
        return "Email";
    }

    public function category(): string
    {
        return "Users";
    }

    public function params(): array
    {
        return [
            new StringArrayParam('email', true, null),
        ];
    }

    public function join(ParamsBag $params): string
    {
        $values = $params->stringArray('email')->escapedString();
        return "SELECT id FROM users WHERE email IN ($values)";
    }

    public function title(ParamsBag $params): string
    {
        return "with email {$params->stringArray('email')->escapedString()}";
    }

    public function fields(): array
    {
        return [];
    }
}
