<?php
namespace xdecaro\Component\Draw\Administrator\View\Dashboard;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use xdecaro\Component\Draw\Administrator\Extension\DrawComponent;

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

        $component = $app->bootComponent('com_xdecarodraw');
        if (!$component instanceof DrawComponent) {
            throw new \RuntimeException('Draw component facade unavailable.');
        }

        $this->canCreate = $identity->authorise('core.create', 'com_xdecarodraw');
        $this->draws = $component->getReadService()->listDraws();
        ToolbarHelper::title(Text::_('COM_XDECARODRAW'), 'shuffle');
        $wa = $app->getDocument()->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_xdecarodraw');
        try { $this->coreUiActive = $component->getCoreIntegrationService()->enableUi($wa); } catch (\Throwable) {}
        $wa->useStyle('com_xdecarodraw.admin');
        parent::display($tpl);
    }
}