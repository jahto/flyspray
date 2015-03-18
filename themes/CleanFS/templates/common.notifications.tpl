<div id="toolbox">
  <h3><?php echo Filters::noXSS(L('notifications')); ?></h3>
  
  <?php if (!count($notifications)): ?>
  <?php echo Filters::noXSS(L('nonotifications')); ?>

  <?php else: ?>
  <?php echo tpl_form(Filters::noXSS(CreateURL('notifications.remove', Req::num('id'), $do))); ?>
   <div>
  <table id="pending_notifications">
    <tr>
      <th>
        <a href="javascript:ToggleSelected('pending_notifications')">
          <img title="<?php echo Filters::noXSS(L('toggleselected')); ?>" alt="<?php echo Filters::noXSS(L('toggleselected')); ?>" src="<?php echo Filters::noXSS($this->get_image('kaboodleloop')); ?>" width="16" height="16" />
        </a>
      </th>
      <th><?php echo Filters::noXSS(L('eventdesc')); ?></th>
    </tr>
    <?php foreach ($notifications as $req): ?>
    <tr>
      <td class="ttcolumn"><?php echo tpl_checkbox('message_id[]', true, null, $req['recipient_id']); ?></td>
      <td><?php echo $req['message_body']; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <input type="hidden" name="action" value="notifications.remove" />
 <button type="submit"><?php echo Filters::noXSS(L('forgetnotifications')); ?></button>
  </div>
</form>
  <?php endif; ?>
</div>
