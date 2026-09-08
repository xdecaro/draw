<?php
defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
?>
<div class="xdecaro-scope xdd-draw">
  <section class="xdecaro-card"><div class="xdecaro-card__header"><h2 class="xdecaro-card__title"><?php echo Text::_('COM_DECARODRAW_BASELINE_TITLE'); ?></h2><p class="xdecaro-card__description"><?php echo Text::_('COM_DECARODRAW_BASELINE_DESC'); ?></p></div><div class="xdecaro-card__body"><div class="xdd-stages"><span class="xdecaro-badge">draft</span><span>→</span><span class="xdecaro-badge">ready</span><span>→</span><span class="xdecaro-badge">live</span><span>→</span><span class="xdecaro-badge">completed</span><span>→</span><span class="xdecaro-badge xdecaro-badge--success">published</span></div><p><?php echo Text::_('COM_DECARODRAW_NO_ENGINE_YET'); ?></p></div></section>
</div>
