<?php

namespace Crm\UsersModule\Components\Widgets;

interface DetailWidgetFactoryInterface
{
    /** @return DetailWidget */
    public function create();
}
