<?php
/**
 * ---------------------------------------------------------------------
 * GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2015-2020 Teclib' and contributors.
 *
 * http://glpi-project.org
 *
 * based on GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2003-2014 by the INDEPNET Development Team.
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 */

include ('../../../inc/includes.php');

Session::checkLoginUser();

if (!isset($_REQUEST['action'])) {
   exit;
}

if ($_REQUEST['action'] == 'change_task_state') {
   header("Content-Type: application/json; charset=UTF-8");
} else {
   header("Content-Type: text/html; charset=UTF-8");
}

$objType = $_REQUEST['parenttype']::getType();
$foreignKey = $_REQUEST['parenttype']::getForeignKeyField();

switch ($_REQUEST['action']) {
   case "change_task_state":
      if (!isset($_REQUEST['items_id'])) {
         exit();
      }
      $objClass = $_REQUEST['itemtype'];
      $obj = new $objClass;
      $obj->getFromDB(intval($_REQUEST['items_id']));

      if (!in_array($obj->fields['state'], [0, Planning::INFO])) {
         $new_state = ($obj->fields['state'] == Planning::DONE)
                           ? Planning::TODO
                           : Planning::DONE;
         $new_label = Planning::getState($new_state);
         echo json_encode([
            'state'  => $new_state,
            'label'  => $new_label
         ]);

         $obj->update([
            'id'         => intval($_REQUEST['items_id']),
            $foreignKey => intval($_REQUEST[$foreignKey]),
            'state'      => $new_state
         ]);
      }
      break;
   case "viewsubitem":
      Html::header_nocache();
      if (!isset($_REQUEST['type'])) {
         exit();
      }
      if (!isset($_REQUEST['parenttype'])) {
         exit();
      }

      $item = getItemForItemtype($_REQUEST['type']);
      $parent = getItemForItemtype($_REQUEST['parenttype']);

      if (isset($_REQUEST[$parent->getForeignKeyField()])
            && isset($_REQUEST["id"])
            && $parent->getFromDB($_REQUEST[$parent->getForeignKeyField()])) {

         $ol = ObjectLock::isLocked( $_REQUEST['parenttype'], $parent->getID() );
         if ($ol && (Session::getLoginUserID() != $ol->fields['users_id'])) {
            ObjectLock::setReadOnlyProfile( );
         }

         $parent::showSubForm($item, $_REQUEST["id"], ['parent' => $parent,
                                                      $foreignKey => $_REQUEST[$foreignKey]]);
      } else {
         echo __('Access denied');
      }

      Html::ajaxFooter();
      break;
}