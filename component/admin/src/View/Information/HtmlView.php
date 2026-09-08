<?php
namespace xdecaro\Component\Draw\Administrator\View\Information;

defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use xdecaro\Component\Draw\Administrator\Service\CoreIntegrationService;
final class HtmlView extends BaseHtmlView
{
    public string $coreVersion = '';
    public bool $coreApiAvailable = false;
    public bool $coreUiActive = false;
    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        if (!$app->getIdentity()->authorise('core.manage', 'com_xdecarodraw')) { throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403); }
        ToolbarHelper::title(Text::_('COM_XDECARODRAW_INFORMATION'), 'info-circle');
        $wa = $app->getDocument()->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_xdecarodraw');
        try { $core = Factory::getContainer()->get(CoreIntegrationService::class); $this->coreVersion = $core->getVersion(); $this->coreApiAvailable = $core->isReferenceApiAvailable(); $this->coreUiActive = $core->enableUi($wa); } catch (\Throwable) {}
        $wa->useStyle('com_xdecarodraw.admin');
        parent::display($tpl);
    }
}
