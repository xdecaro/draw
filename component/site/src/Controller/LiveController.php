<?php
namespace xdecaro\Component\Draw\Site\Controller;

defined('_JEXEC') or die;

use DomainException;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Throwable;
use xdecaro\Component\Draw\Administrator\Extension\DrawComponent;

final class LiveController extends BaseController
{
    public function snapshot(): void
    {
        $app = Factory::getApplication();
        try {
            $component = $app->bootComponent('com_xdecarodraw');
            if (!$component instanceof DrawComponent) {
                throw new DomainException('Draw component unavailable.');
            }
            $data = $component->getReadService()->getPublicSnapshot($app->input->getInt('id'));
            $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
            echo new JsonResponse($data);
        } catch (Throwable $e) {
            $app->setHeader('Status', '404 Not Found', true);
            $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
            echo new JsonResponse(null, $e->getMessage(), true);
        }
        $app->close();
    }
}
