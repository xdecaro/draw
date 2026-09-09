<?php
defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
$s = $this->snapshot;
$snapshotUrl = Route::_('index.php?option=com_xdecarodraw&task=live.snapshot&format=json&id=' . (int) $s['draw_id']);
?>
<div class="xdd-live" data-xdd-live data-draw-id="<?php echo (int) $s['draw_id']; ?>" data-snapshot-url="<?php echo $this->escape($snapshotUrl); ?>" data-revealed="<?php echo (int) $s['revealed_count']; ?>">
  <header class="xdd-live__header"><p class="xdd-live__eyebrow"><?php echo Text::_('COM_XDECARODRAW_LIVE_DRAW'); ?></p><h1><?php echo $this->escape($s['title']); ?></h1><div class="xdd-live__meta"><span data-xdd-status><?php echo $this->escape($s['status']); ?></span><span><?php echo Text::sprintf('COM_XDECARODRAW_EXECUTION_N', (int) $s['execution_no']); ?></span><span data-xdd-progress><?php echo (int) $s['revealed_count']; ?>/<?php echo (int) $s['total']; ?></span></div></header>
  <main class="xdd-live__grid" data-xdd-grid aria-live="polite"><?php foreach ($s['assignments'] as $a) : ?><article class="xdd-live__card" data-sequence="<?php echo (int) $a['sequence_no']; ?>"><div class="xdd-live__number">#<?php echo (int) $a['sequence_no']; ?></div><div class="xdd-live__entry"><?php echo $this->escape($a['display_name']); ?></div><div class="xdd-live__target"><?php echo $this->escape($a['target_key']); ?><?php echo $a['position_no'] !== null ? ' · ' . (int) $a['position_no'] : ''; ?></div></article><?php endforeach; ?></main>
  <section class="xdd-live__verification<?php echo $s['completed'] ? '' : ' is-hidden'; ?>" data-xdd-verification><h2><?php echo Text::_('COM_XDECARODRAW_VERIFICATION'); ?></h2><dl><dt><?php echo Text::_('COM_XDECARODRAW_SEED'); ?></dt><dd data-xdd-seed><code><?php echo $this->escape($s['seed']); ?></code></dd><dt><?php echo Text::_('COM_XDECARODRAW_INPUT_HASH'); ?></dt><dd data-xdd-input-hash><code><?php echo $this->escape($s['input_hash']); ?></code></dd><dt><?php echo Text::_('COM_XDECARODRAW_RESULT_HASH'); ?></dt><dd data-xdd-result-hash><code><?php echo $this->escape($s['result_hash']); ?></code></dd></dl></section>
  <noscript><p class="alert alert-info"><?php echo Text::_('COM_XDECARODRAW_LIVE_NOSCRIPT'); ?></p></noscript>
</div>
