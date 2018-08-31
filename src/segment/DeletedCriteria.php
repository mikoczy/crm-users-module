<?php

namespace Crm\UsersModule\Segment;

use Crm\ApplicationModule\Criteria\CriteriaInterface;
use Crm\SegmentModule\Params\BooleanParam;
use Crm\SegmentModule\Params\ParamsBag;

class DeletedCriteria implements CriteriaInterface
{
    public function label(): string
    {
        return "Deleted";
    }

    public function category(): string
    {
        return "Users";
    }

    public function params(): array
    {
        return [
            new BooleanParam('deleted', true, false),
        ];
    }

    public function join(ParamsBag $params): string
    {
        $null = 'IS NULL';
        if ($params->boolean('deleted')->isTrue()) {
            $null = 'IS NOT NULL';
        }

        return "SELECT id FROM users WHERE deleted_at {$null}";
    }

    public function title(ParamsBag $params): string
    {
        if ($params->boolean('active')->isTrue()) {
            return ' deleted';
        } else {
            return '';
        }
    }

    public function fields(): array
    {
        return [];
    }
}