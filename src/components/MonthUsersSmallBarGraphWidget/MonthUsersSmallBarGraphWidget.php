<?php

namespace Crm\UsersModule\Components;

use Crm\ApplicationModule\Components\Graphs\SmallBarGraphControlFactoryInterface;
use Crm\ApplicationModule\Graphs\Criteria;
use Crm\ApplicationModule\Graphs\GraphData;
use Crm\ApplicationModule\Graphs\GraphDataItem;
use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Nette\Localization\ITranslator;

class MonthUsersSmallBarGraphWidget extends BaseWidget
{
    private $templateName = 'month_users_small_bar_graph_widget.latte';

    private $factory;

    private $graphData;

    private $translator;

    public function __construct(
        WidgetManager $widgetManager,
        SmallBarGraphControlFactoryInterface $factory,
        GraphData $graphData,
        ITranslator $translator
    ) {
        parent::__construct($widgetManager);
        $this->factory = $factory;
        $this->graphData = $graphData;
        $this->translator = $translator;
    }

    public function identifier()
    {
        return 'monthuserssmallbargraphwidget';
    }

    public function render()
    {
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }

    protected function createComponentUsersSmallBarGraph()
    {
        $graphDataItem = new GraphDataItem();
        $graphDataItem
            ->setCriteria(
                (new Criteria())
                    ->setStart('-31 days')
                    ->setTableName('users')
            );

        $this->graphData->addGraphDataItem($graphDataItem);
        $this->graphData->setScaleRange('day');

        $control = $this->factory->create();
        $control->setGraphTitle($this->translator->translate('users.month_users_small_bar_graph_widget.title'))
            ->addSerie($this->graphData->getData());
        return $control;
    }
}
