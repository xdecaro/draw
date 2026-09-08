<?php
namespace Xdecaro\Component\Decarodraw\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\WebAsset\WebAssetManager;

final class CoreIntegrationService
{
    private const COMPONENT = 'com_decarodraw';
    private const MINIMUM_UI_VERSION = '1.1.0';

    public function isReferenceApiAvailable(): bool
    {
        return class_exists(\Xdecaro\Core\Integration\EntityReference::class)
            && class_exists(\Xdecaro\Core\Integration\RelationReference::class);
    }

    public function getVersion(): string
    {
        return class_exists(\Xdecaro\Core\Version::class) ? (string) \Xdecaro\Core\Version::VERSION : '';
    }

    public function enableUi(WebAssetManager $webAssets): bool
    {
        $version = $this->getVersion();
        if ($version === '' || version_compare($version, self::MINIMUM_UI_VERSION, '<') || !class_exists(\Xdecaro\Core\Asset\AssetService::class)) {
            return false;
        }
        try {
            return (new \Xdecaro\Core\Asset\AssetService())->useComponents($webAssets);
        } catch (\Throwable) {
            return false;
        }
    }

    public function createSourceReference(string $component, string $entity, int|string $id): object
    {
        if (!$this->isReferenceApiAvailable()) {
            throw new \RuntimeException('Core by xdecaro reference API is unavailable.');
        }
        return new \Xdecaro\Core\Integration\EntityReference($component, $entity, $id);
    }

    public function createDrawReference(int|string $id): object
    {
        if (!$this->isReferenceApiAvailable()) {
            throw new \RuntimeException('Core by xdecaro reference API is unavailable.');
        }
        return new \Xdecaro\Core\Integration\EntityReference(self::COMPONENT, 'draw', $id);
    }
}
