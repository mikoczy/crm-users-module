<?php

namespace Crm\UsersModule\Components\Widgets;

use Crm\ApplicationModule\Widget\BaseWidget;

class DetailWidget extends BaseWidget
{
    private $templateName = 'detail_widget.latte';

    public function render($path, $params = '')
    {
        $widgets = $this->widgetManager->getWidgets($path);
        $mainWidgets = [];
        $mainWidgetsTitles = [];
        $subWidgets = [];
        $subWidgetsTitles = [];
        foreach ($widgets as $sorting => $widget) {
            $this->addComponent($widget, $widget->identifier());
            if ($sorting < 1000) {
                $mainWidgets[] = $widget;
                $mainWidgetsTitles[$widget->identifier()] = $widget->header($params);
            } else {
                $subWidgets[] = $widget;
                $subWidgetsTitles[$widget->identifier()] = $widget->header($params);
            }
        }

        $this->template->mainWidgets = $mainWidgets;
        $this->template->mainWidgetsTitles = $mainWidgetsTitles;
        $this->template->subWidgets = $subWidgets;
        $this->template->subWidgetsTitles = $subWidgetsTitles;
        $this->template->params = $params;

        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
