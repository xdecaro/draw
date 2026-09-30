<?php
namespace xdecaro\Component\Draw\Administrator\View\Draw;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use xdecaro\Component\Draw\Administrator\Extension\DrawComponent;

final class HtmlView extends BaseHtmlView
{
    public array $draw = [];
    public array $entries = [];
    public array $targets = [];
    public array $assignments = [];
    public array $audit = [];
    public int $executionNo = 0;
    public bool $inputsMutable = false;
    public bool $canEdit = false;
    public bool $canExecute = false;
    public bool $canPublish = false;

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

        $id = $app->input->getInt('id');
        $read = $component->getReadService();
        $this->draw = $read->getDraw($id);
        $this->entries = $read->getEntries($id);
        $this->targets = $read->getTargets($id);
        $this->executionNo = $read->getCurrentExecutionNo($id);
        $this->assignments = $read->getAssignments($id, $this->executionNo ?: null, false);
        $this->audit = $read->getAudit($id);
        $this->inputsMutable = $read->countAssignments($id) === 0;
        $this->canEdit = $identity->authorise('core.edit', 'com_xdecarodraw');
        $this->canExecute = $identity->authorise('draw.execute', 'com_xdecarodraw');
        $this->canPublish = $identity->authorise('draw.publish', 'com_xdecarodraw');
        ToolbarHelper::title($this->escape((string) $this->draw['title']), 'shuffle');
        ToolbarHelper::back(Text::_('JTOOLBAR_BACK'), 'index.php?option=com_xdecarodraw&view=dashboard');
        $wa = $app->getDocument()->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_xdecarodraw');
        try { $component->getCoreIntegrationService()->enableUi($wa); } catch (\Throwable) {}
        $wa->useStyle('com_xdecarodraw.admin');
        parent::display($tpl);
    }
}