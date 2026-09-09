<?php
defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

$id = (int) $this->draw['id'];
$status = (string) $this->draw['status'];
$entriesForJson = array_map(static fn(array $e): array => [
    'entry_key' => $e['entry_key'], 'display_name' => $e['display_name'], 'source_component' => $e['source_component'], 'source_entity' => $e['source_entity'], 'source_id' => $e['source_id'], 'pot_key' => $e['pot_key'], 'seed_order' => $e['seed_order'] === null ? null : (int) $e['seed_order'], 'metadata' => $e['metadata_array'],
], $this->entries);
$targetsForJson = array_map(static fn(array $t): array => [
    'target_type' => $t['target_type'], 'target_key' => $t['target_key'], 'position_no' => $t['position_no'] === null ? null : (int) $t['position_no'], 'metadata' => $t['metadata_array'],
], $this->targets);
$constraints = $this->draw['configuration_array']['constraints'] ?? [];
$publicUrl = Uri::root() . 'index.php?option=com_xdecarodraw&view=live&id=' . $id;
?>
<div class="xdecaro-scope xdd-draw">
  <section class="xdecaro-card xdd-panel"><div class="xdecaro-card__body xdd-summary"><div><strong><?php echo Text::_('COM_XDECARODRAW_STATUS'); ?></strong><span class="xdd-status xdd-status--<?php echo $this->escape($status); ?>"><?php echo $this->escape($status); ?></span></div><div><strong><?php echo Text::_('COM_XDECARODRAW_EXECUTION'); ?></strong><span><?php echo (int) $this->executionNo; ?></span></div><div><strong><?php echo Text::_('COM_XDECARODRAW_ENTRIES'); ?></strong><span><?php echo count($this->entries); ?></span></div><div><strong><?php echo Text::_('COM_XDECARODRAW_TARGETS'); ?></strong><span><?php echo count($this->targets); ?></span></div></div></section>

  <?php if (in_array($status, ['live','completed','published'], true)) : ?>
  <section class="xdecaro-card xdd-panel"><div class="xdecaro-card__header"><h2 class="xdecaro-card__title"><?php echo Text::_('COM_XDECARODRAW_PUBLIC_LIVE'); ?></h2></div><div class="xdecaro-card__body"><a class="btn btn-outline-primary" href="<?php echo $this->escape($publicUrl); ?>" target="_blank" rel="noopener"><?php echo Text::_('COM_XDECARODRAW_OPEN_PUBLIC_LIVE'); ?></a><code class="xdd-url"><?php echo $this->escape($publicUrl); ?></code></div></section>
  <?php endif; ?>

  <?php if ($this->canEdit && $this->inputsMutable) : ?>
  <section class="xdecaro-card xdd-panel"><div class="xdecaro-card__header"><h2 class="xdecaro-card__title"><?php echo Text::_('COM_XDECARODRAW_CONFIGURATION'); ?></h2><p class="xdecaro-card__description"><?php echo Text::_('COM_XDECARODRAW_CONFIGURATION_DESC'); ?></p></div><div class="xdecaro-card__body">
    <form action="<?php echo Route::_('index.php?option=com_xdecarodraw&task=draw.configure'); ?>" method="post" class="xdd-json-form">
      <input type="hidden" name="id" value="<?php echo $id; ?>">
      <div><label for="xdd-entries"><?php echo Text::_('COM_XDECARODRAW_ENTRIES_JSON'); ?></label><textarea id="xdd-entries" class="form-control font-monospace" rows="14" name="entries_json" required><?php echo $this->escape(json_encode($entriesForJson, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); ?></textarea></div>
      <div><label for="xdd-targets"><?php echo Text::_('COM_XDECARODRAW_TARGETS_JSON'); ?></label><textarea id="xdd-targets" class="form-control font-monospace" rows="14" name="targets_json" required><?php echo $this->escape(json_encode($targetsForJson, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); ?></textarea></div>
      <div class="xdd-json-wide"><label for="xdd-constraints"><?php echo Text::_('COM_XDECARODRAW_CONSTRAINTS_JSON'); ?></label><textarea id="xdd-constraints" class="form-control font-monospace" rows="10" name="constraints_json"><?php echo $this->escape(json_encode($constraints, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); ?></textarea></div>
      <div class="xdd-json-wide"><button class="btn btn-primary" type="submit"><?php echo Text::_('JSAVE'); ?></button></div><?php echo HTMLHelper::_('form.token'); ?>
    </form>
  </div></section>
  <?php elseif (!$this->inputsMutable) : ?>
  <div class="alert alert-info"><?php echo Text::_('COM_XDECARODRAW_INPUTS_LOCKED'); ?></div>
  <?php endif; ?>

  <?php if ($this->canExecute && in_array($status, ['ready','completed','published'], true)) : ?>
  <section class="xdecaro-card xdd-panel"><div class="xdecaro-card__header"><h2 class="xdecaro-card__title"><?php echo $this->executionNo > 0 ? Text::_('COM_XDECARODRAW_RESTART') : Text::_('COM_XDECARODRAW_EXECUTE'); ?></h2></div><div class="xdecaro-card__body"><form action="<?php echo Route::_('index.php?option=com_xdecarodraw&task=draw.' . ($this->executionNo > 0 ? 'restart' : 'execute')); ?>" method="post" class="xdd-inline-form"><input type="hidden" name="id" value="<?php echo $id; ?>"><div><label for="xdd-seed"><?php echo Text::_('COM_XDECARODRAW_OPTIONAL_SEED'); ?></label><input id="xdd-seed" class="form-control" type="text" name="seed" maxlength="512"><small class="form-text"><?php echo Text::_('COM_XDECARODRAW_OPTIONAL_SEED_DESC'); ?></small></div><div class="xdd-form-action"><button class="btn btn-primary" type="submit"><?php echo $this->executionNo > 0 ? Text::_('COM_XDECARODRAW_RESTART') : Text::_('COM_XDECARODRAW_EXECUTE'); ?></button></div><?php echo HTMLHelper::_('form.token'); ?></form></div></section>
  <?php endif; ?>

  <?php if ($this->canExecute && $status === 'live') : ?>
  <form action="<?php echo Route::_('index.php?option=com_xdecarodraw&task=draw.reveal'); ?>" method="post" class="xdd-primary-action"><input type="hidden" name="id" value="<?php echo $id; ?>"><button class="btn btn-success btn-lg" type="submit"><?php echo Text::_('COM_XDECARODRAW_REVEAL_NEXT'); ?></button><?php echo HTMLHelper::_('form.token'); ?></form>
  <?php endif; ?>
  <?php if ($this->canPublish && $status === 'completed') : ?>
  <form action="<?php echo Route::_('index.php?option=com_xdecarodraw&task=draw.publish'); ?>" method="post" class="xdd-primary-action"><input type="hidden" name="id" value="<?php echo $id; ?>"><button class="btn btn-primary btn-lg" type="submit"><?php echo Text::_('COM_XDECARODRAW_PUBLISH'); ?></button><?php echo HTMLHelper::_('form.token'); ?></form>
  <?php endif; ?>

  <section class="xdecaro-card xdd-panel"><div class="xdecaro-card__header"><h2 class="xdecaro-card__title"><?php echo Text::_('COM_XDECARODRAW_ASSIGNMENTS'); ?></h2></div><div class="xdecaro-card__body"><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>#</th><th><?php echo Text::_('COM_XDECARODRAW_ENTRY'); ?></th><th><?php echo Text::_('COM_XDECARODRAW_TARGET'); ?></th><th><?php echo Text::_('COM_XDECARODRAW_REVEALED'); ?></th></tr></thead><tbody><?php foreach ($this->assignments as $a) : ?><tr><td><?php echo (int) $a['sequence_no']; ?></td><td><?php echo $this->escape($a['display_name']); ?></td><td><code><?php echo $this->escape($a['target_key']); ?><?php echo $a['position_no'] !== null ? ':' . (int) $a['position_no'] : ''; ?></code></td><td><?php echo $a['revealed_at'] ? $this->escape($a['revealed_at']) : '—'; ?></td></tr><?php endforeach; ?></tbody></table></div></div></section>

  <section class="xdecaro-card xdd-panel"><div class="xdecaro-card__header"><h2 class="xdecaro-card__title"><?php echo Text::_('COM_XDECARODRAW_AUDIT'); ?></h2></div><div class="xdecaro-card__body"><div class="table-responsive"><table class="table table-sm"><thead><tr><th><?php echo Text::_('COM_XDECARODRAW_EVENT'); ?></th><th><?php echo Text::_('COM_XDECARODRAW_ACTOR'); ?></th><th><?php echo Text::_('COM_XDECARODRAW_CREATED'); ?></th><th><?php echo Text::_('COM_XDECARODRAW_DETAILS'); ?></th></tr></thead><tbody><?php foreach ($this->audit as $row) : ?><tr><td><code><?php echo $this->escape($row['event_type']); ?></code></td><td><?php echo (int) $row['actor_id']; ?></td><td><?php echo $this->escape($row['created']); ?></td><td><small class="font-monospace"><?php echo $this->escape(json_encode($row['payload_array'], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)); ?></small></td></tr><?php endforeach; ?></tbody></table></div></div></section>
</div>
