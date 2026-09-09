<?php
namespace xdecaro\Component\Draw\Administrator\Controller;

defined('_JEXEC') or die;

use DomainException;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Throwable;
use xdecaro\Component\Draw\Administrator\Extension\DrawComponent;

final class DrawController extends BaseController
{
    public function create(): void
    {
        $this->assertPostAndAcl('core.create');
        $app = Factory::getApplication();
        try {
            $id = $this->component()->getDrawService()->create(
                $app->input->post->getString('title'),
                $app->input->post->getCmd('mode', 'groups'),
                [],
                (int) $app->getIdentity()->id
            );
            $app->enqueueMessage(Text::_('COM_XDECARODRAW_MSG_CREATED'));
            $this->setRedirect(Route::_('index.php?option=com_xdecarodraw&view=draw&id=' . $id, false));
        } catch (Throwable $e) {
            $this->fail($e, 'index.php?option=com_xdecarodraw&view=dashboard');
        }
    }

    public function configure(): void
    {
        $this->assertPostAndAcl('core.edit');
        $app = Factory::getApplication();
        $id = $app->input->post->getInt('id');
        try {
            $entries = $this->decodeArray($app->input->post->get('entries_json', '', 'raw'), Text::_('COM_XDECARODRAW_ENTRIES'));
            $targets = $this->decodeArray($app->input->post->get('targets_json', '', 'raw'), Text::_('COM_XDECARODRAW_TARGETS'));
            $constraints = $this->decodeArray($app->input->post->get('constraints_json', '{}', 'raw'), Text::_('COM_XDECARODRAW_CONSTRAINTS'));
            $this->component()->getDrawService()->configure($id, $entries, $targets, $constraints, (int) $app->getIdentity()->id);
            $app->enqueueMessage(Text::_('COM_XDECARODRAW_MSG_CONFIGURED'));
            $this->redirectToDraw($id);
        } catch (Throwable $e) {
            $this->fail($e, 'index.php?option=com_xdecarodraw&view=draw&id=' . $id);
        }
    }

    public function execute(): void
    {
        $this->assertPostAndAcl('draw.execute');
        $app = Factory::getApplication();
        $id = $app->input->post->getInt('id');
        try {
            $seed = trim((string) $app->input->post->getString('seed', ''));
            $this->component()->getDrawService()->execute($id, $seed === '' ? null : $seed, (int) $app->getIdentity()->id);
            $app->enqueueMessage(Text::_('COM_XDECARODRAW_MSG_EXECUTED'));
            $this->redirectToDraw($id);
        } catch (Throwable $e) {
            $this->fail($e, 'index.php?option=com_xdecarodraw&view=draw&id=' . $id);
        }
    }

    public function reveal(): void
    {
        $this->assertPostAndAcl('draw.execute');
        $app = Factory::getApplication();
        $id = $app->input->post->getInt('id');
        try {
            $result = $this->component()->getDrawService()->revealNext($id, (int) $app->getIdentity()->id);
            $app->enqueueMessage($result ? Text::_('COM_XDECARODRAW_MSG_REVEALED') : Text::_('COM_XDECARODRAW_MSG_COMPLETED'));
            $this->redirectToDraw($id);
        } catch (Throwable $e) {
            $this->fail($e, 'index.php?option=com_xdecarodraw&view=draw&id=' . $id);
        }
    }

    public function publish(): void
    {
        $this->assertPostAndAcl('draw.publish');
        $app = Factory::getApplication();
        $id = $app->input->post->getInt('id');
        try {
            $this->component()->getDrawService()->publish($id, (int) $app->getIdentity()->id);
            $app->enqueueMessage(Text::_('COM_XDECARODRAW_MSG_PUBLISHED'));
            $this->redirectToDraw($id);
        } catch (Throwable $e) {
            $this->fail($e, 'index.php?option=com_xdecarodraw&view=draw&id=' . $id);
        }
    }

    public function restart(): void
    {
        $this->assertPostAndAcl('draw.execute');
        $app = Factory::getApplication();
        $id = $app->input->post->getInt('id');
        try {
            $seed = trim((string) $app->input->post->getString('seed', ''));
            $this->component()->getDrawService()->restart($id, $seed === '' ? null : $seed, (int) $app->getIdentity()->id);
            $app->enqueueMessage(Text::_('COM_XDECARODRAW_MSG_RESTARTED'));
            $this->redirectToDraw($id);
        } catch (Throwable $e) {
            $this->fail($e, 'index.php?option=com_xdecarodraw&view=draw&id=' . $id);
        }
    }

    private function assertPostAndAcl(string $action): void
    {
        if (!Session::checkToken('post')) {
            throw new DomainException(Text::_('JINVALID_TOKEN'));
        }
        if (!Factory::getApplication()->getIdentity()->authorise($action, 'com_xdecarodraw')) {
            throw new DomainException(Text::_('JERROR_ALERTNOAUTHOR'));
        }
    }

    private function decodeArray(mixed $json, string $label): array
    {
        $decoded = json_decode((string) $json, true);
        if (!is_array($decoded)) {
            throw new DomainException(Text::sprintf('COM_XDECARODRAW_ERR_INVALID_JSON', $label));
        }
        return $decoded;
    }

    private function component(): DrawComponent
    {
        $component = Factory::getApplication()->bootComponent('com_xdecarodraw');
        if (!$component instanceof DrawComponent) {
            throw new DomainException('Draw component facade unavailable.');
        }
        return $component;
    }

    private function redirectToDraw(int $id): void
    {
        $this->setRedirect(Route::_('index.php?option=com_xdecarodraw&view=draw&id=' . max(1, $id), false));
    }

    private function fail(Throwable $e, string $url): void
    {
        Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
        $this->setRedirect(Route::_($url, false));
    }
}
