<?php
defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
?>
<div class="xdecaro-scope xdd-draw">
  <?php if ($this->canCreate) : ?>
  <section class="xdecaro-card xdd-panel">
    <div class="xdecaro-card__header"><h2 class="xdecaro-card__title"><?php echo Text::_('COM_XDECARODRAW_NEW_DRAW'); ?></h2><p class="xdecaro-card__description"><?php echo Text::_('COM_XDECARODRAW_NEW_DRAW_DESC'); ?></p></div>
    <div class="xdecaro-card__body">
      <form action="<?php echo Route::_('index.php?option=com_xdecarodraw&task=draw.create'); ?>" method="post" class="xdd-inline-form">
        <div><label for="xdd-title"><?php echo Text::_('COM_XDECARODRAW_TITLE'); ?></label><input id="xdd-title" class="form-control" type="text" name="title" maxlength="255" required></div>
        <div><label for="xdd-mode"><?php echo Text::_('COM_XDECARODRAW_MODE'); ?></label><select id="xdd-mode" class="form-select" name="mode"><option value="groups">groups</option><option value="bracket">bracket</option><option value="generic">generic</option></select></div>
        <div class="xdd-form-action"><button class="btn btn-primary" type="submit"><?php echo Text::_('COM_XDECARODRAW_CREATE'); ?></button></div>
        <?php echo HTMLHelper::_('form.token'); ?>
      </form>
    </div>
  </section>
  <?php endif; ?>

  <section class="xdecaro-card xdd-panel">
    <div class="xdecaro-card__header"><h2 class="xdecaro-card__title"><?php echo Text::_('COM_XDECARODRAW_DRAWS'); ?></h2></div>
    <div class="xdecaro-card__body">
      <?php if (!$this->draws) : ?><p class="text-muted"><?php echo Text::_('COM_XDECARODRAW_NO_DRAWS'); ?></p><?php else : ?>
      <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th><?php echo Text::_('COM_XDECARODRAW_TITLE'); ?></th><th><?php echo Text::_('COM_XDECARODRAW_MODE'); ?></th><th><?php echo Text::_('COM_XDECARODRAW_STATUS'); ?></th><th><?php echo Text::_('COM_XDECARODRAW_UPDATED'); ?></th><th class="text-end"><?php echo Text::_('COM_XDECARODRAW_ACTIONS'); ?></th></tr></thead><tbody>
      <?php foreach ($this->draws as $draw) : ?>
        <tr><th scope="row"><?php echo $this->escape($draw['title']); ?></th><td><code><?php echo $this->escape($draw['mode']); ?></code></td><td><span class="xdd-status xdd-status--<?php echo $this->escape($draw['status']); ?>"><?php echo $this->escape($draw['status']); ?></span></td><td><?php echo $this->escape($draw['modified'] ?: $draw['created']); ?></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?php echo Route::_('index.php?option=com_xdecarodraw&view=draw&id=' . (int) $draw['id']); ?>"><?php echo Text::_('JOPEN'); ?></a></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
      <?php endif; ?>
    </div>
  </section>
</div>
