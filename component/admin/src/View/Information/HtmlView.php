<?php
namespace xdecaro\Component\Draw\Administrator\View\Information;

defined('_JEXEC') or die;

use Joomla\CMS\Extension\ExtensionHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;
use xdecaro\Component\Draw\Administrator\Service\CoreIntegrationService;

final class HtmlView extends BaseHtmlView
{
    public string $version = '1.0.0';
    public string $coreVersion = '';
    public bool $coreApiAvailable = false;
    public bool $coreCapabilityAvailable = false;
    public bool $coreUiActive = false;
    public bool $competitionsInstalled = false;
    public array $diagnostics = [];

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        if (!$app->getIdentity()->authorise('core.manage', 'com_xdecarodraw')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        ToolbarHelper::title(Text::_('COM_XDECARODRAW_INFORMATION'), 'info-circle');
        $wa = $app->getDocument()->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_xdecarodraw');
        try {
            $core = Factory::getContainer()->get(CoreIntegrationService::class);
            $this->coreVersion = $core->getVersion();
            $this->coreApiAvailable = $core->isReferenceApiAvailable();
            $this->coreCapabilityAvailable = $core->isCapabilityApiAvailable();
            $this->coreUiActive = $core->enableUi($wa);
        } catch (\Throwable) {}
        $this->competitionsInstalled = ExtensionHelper::isEnabled('component', 'com_xdecarocompetitions');
        /** @var DatabaseInterface $db */
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $tableList = array_fill_keys($db->getTableList(), true);
        $expected = ['draws','entries','targets','assignments','audit'];
        foreach ($expected as $name) {
            $table = $db->getPrefix() . 'xdecarodraw_' . $name;
            $this->diagnostics[$name] = isset($tableList[$table]);
        }
        $wa->useStyle('com_xdecarodraw.admin');
        parent::display($tpl);
    }
}
