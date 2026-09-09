<?php
namespace xdecaro\Component\Draw\Administrator\View\Dashboard;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use xdecaro\Component\Draw\Administrator\Service\CoreIntegrationService;
use xdecaro\Component\Draw\Administrator\Service\DrawReadService;

final class HtmlView extends BaseHtmlView
{
    public bool $coreUiActive = false;
    public array $draws = [];
    public bool $canCreate = false;

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        $identity = $app->getIdentity();
        if (!$identity->authorise('core.manage', 'com_xdecarodraw')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        $this->canCreate = $identity->authorise('core.create', 'com_xdecarodraw');
        $this->draws = Factory::getContainer()->get(DrawReadService::class)->listDraws();
        ToolbarHelper::title(Text::_('COM_XDECARODRAW'), 'shuffle');
        $wa = $app->getDocument()->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_xdecarodraw');
        try { $this->coreUiActive = Factory::getContainer()->get(CoreIntegrationService::class)->enableUi($wa); } catch (\Throwable) {}
        $wa->useStyle('com_xdecarodraw.admin');
        parent::display($tpl);
    }
}
