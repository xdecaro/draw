<?php
namespace Xdecaro\Component\Decarodraw\Administrator\View\Dashboard;

defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Xdecaro\Component\Decarodraw\Administrator\Service\CoreIntegrationService;
final class HtmlView extends BaseHtmlView
{
    public bool $coreUiActive = false;
    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        if (!$app->getIdentity()->authorise('core.manage', 'com_decarodraw')) { throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403); }
        ToolbarHelper::title(Text::_('COM_DECARODRAW'), 'shuffle');
        $wa = $app->getDocument()->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_decarodraw');
        try { $this->coreUiActive = Factory::getContainer()->get(CoreIntegrationService::class)->enableUi($wa); } catch (\Throwable) {}
        $wa->useStyle('com_decarodraw.admin');
        parent::display($tpl);
    }
}
