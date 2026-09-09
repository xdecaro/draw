<?php
namespace xdecaro\Component\Draw\Administrator\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Extension\MVCComponent;
use LogicException;
use xdecaro\Component\Draw\Administrator\Service\CoreIntegrationService;
use xdecaro\Component\Draw\Administrator\Service\DrawReadService;
use xdecaro\Component\Draw\Administrator\Service\DrawService;

final class DrawComponent extends MVCComponent
{
    private ?DrawService $drawService = null;
    private ?DrawReadService $readService = null;
    private ?CoreIntegrationService $coreIntegrationService = null;

    public function setDrawService(DrawService $service): void { $this->drawService = $service; }
    public function setReadService(DrawReadService $service): void { $this->readService = $service; }
    public function setCoreIntegrationService(CoreIntegrationService $service): void { $this->coreIntegrationService = $service; }

    public function getDrawService(): DrawService
    {
        if (!$this->drawService) { throw new LogicException('DrawService is not initialized.'); }
        return $this->drawService;
    }

    public function getReadService(): DrawReadService
    {
        if (!$this->readService) { throw new LogicException('DrawReadService is not initialized.'); }
        return $this->readService;
    }

    public function getCoreIntegrationService(): CoreIntegrationService
    {
        if (!$this->coreIntegrationService) { throw new LogicException('CoreIntegrationService is not initialized.'); }
        return $this->coreIntegrationService;
    }
}
