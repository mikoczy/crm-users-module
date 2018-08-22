<?php

namespace Crm\UsersModule\Segment;

use Crm\SegmentModule\Params\ParamsBag;
use Crm\ApplicationModule\Criteria\CriteriaInterface;
use Crm\SegmentModule\Params\StringArrayParam;

class SourceCriteria implements CriteriaInterface
{
    private function availableSources(): array
    {
        return ['web', 'api'];
    }

    public function label(): string
    {
        return "Source";
    }

    public function category(): string
    {
        return "Users";
    }

    public function params(): array
    {
        return [
            new StringArrayParam('source', true, null, null, $this->availableSources()),
        ];
    }

    public function join(ParamsBag $params): string
    {
        $values = $params->stringArray('source')->escapedString();
        return "SELECT id FROM users WHERE source IN ($values)";
    }

    public function title(ParamsBag $params): string
    {
        return "source {$params->stringArray('source')->escapedString()}";
    }

    public function fields(): array
    {
        return [];
    }
}
