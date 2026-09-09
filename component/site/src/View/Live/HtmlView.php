<?php
namespace xdecaro\Component\Draw\Site\View\Live;

defined('_JEXEC') or die;

use DomainException;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use xdecaro\Component\Draw\Administrator\Extension\DrawComponent;

final class HtmlView extends BaseHtmlView
{
    public array $snapshot = [];

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        $component = $app->bootComponent('com_xdecarodraw');
        if (!$component instanceof DrawComponent) {
            throw new DomainException('Draw component unavailable.');
        }
        $this->snapshot = $component->getReadService()->getPublicSnapshot($app->input->getInt('id'));
        $document = $app->getDocument();
        $document->setTitle((string) $this->snapshot['title']);
        $wa = $document->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_xdecarodraw');
        $wa->useStyle('com_xdecarodraw.live')->useScript('com_xdecarodraw.live');
        parent::display($tpl);
    }
}
