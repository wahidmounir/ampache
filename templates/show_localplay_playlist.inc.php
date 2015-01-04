<?php
/* vim:set softtabstop=4 shiftwidth=4 expandtab: */
/**
 *
 * LICENSE: GNU General Public License, version 2 (GPLv2)
 * Copyright 2001 - 2015 Ampache.org
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License v2
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 */

$localplay = new Localplay(AmpConfig::get('localplay_controller'));
$localplay->connect();
$status = $localplay->status();
?>
<?php if ($browse->get_show_header()) require AmpConfig::get('prefix') . '/templates/list_header.inc.php'; ?>
<table class="tabledata" cellpadding="0" cellspacing="0">
    <thead>
        <tr class="th-top">
            <th class="cel_track"><?php echo T_('Track'); ?></th>
            <th class="cel_name"><?php echo T_('Name'); ?></th>
            <th class="cel_action"><?php echo T_('Action'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php
        foreach ($object_ids as $object) {
            $class = ' class="cel_name"';
            if ($status['track'] == $object['track']) { $class=' class="cel_name lp_current"'; }
        ?>
        <tr class="<?php echo UI::flip_class(); ?>" id="localplay_playlist_<?php echo $object['id']; ?>">
            <td class="cel_track">
                <?php echo scrub_out($object['track']); ?>
            </td>
            <td<?php echo $class; ?>>
                <?php echo $localplay->format_name($object['name'],$object['id']); ?>
            </td>
            <td class="cel_action">
            <?php echo Ajax::button('?page=localplay&action=delete_track&id=' . intval($object['id']),'delete', T_('Delete'),'localplay_delete_' . intval($object['id'])); ?>
            </td>
        </tr>
        <?php } if (!count($object_ids)) { ?>
        <tr class="<?php echo UI::flip_class(); ?>">
            <td colspan="3"><span class="error"><?php echo T_('No Records Found'); ?></span></td>
        </tr>
        <?php } ?>
    </tbody>
    <tfoot>
        <tr class="th-bottom">
            <th class="cel_track"><?php echo T_('Track'); ?></th>
            <th class="cel_name"><?php echo T_('Name'); ?></th>
            <th class="cel_action"><?php echo T_('Action'); ?></th>
        </tr>
    </tfoot>
</table>
<?php if ($browse->get_show_header()) require AmpConfig::get('prefix') . '/templates/list_header.inc.php'; ?>
