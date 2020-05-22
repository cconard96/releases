<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 Releases plugin for GLPI
 Copyright (C) 2018 by the Releases Development Team.

 https://github.com/InfotelGLPI/releases
 -------------------------------------------------------------------------

 LICENSE

 This file is part of releases.

 releases is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 releases is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with releases. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Class PluginReleasesReview
 */
class PluginReleasesReview extends CommonDBTM {

   public $dohistory = true;
   static $rightname = 'ticket';
   protected $usenotepad = true;
   static $types = [];


   /**
    * @param int $nb
    *
    * @return translated
    */
   static function getTypeName($nb = 0) {

      return _n('Review', 'Reviews', $nb, 'releases');
   }


   //TODO
   /**
    * @return array
    */
   function rawSearchOptions() {

      $tab = [];

   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

      if ($item->getType() == self::getType()) {
        return self::getTypeName(2);
      } else if ($item->getType() == PluginReleasesRelease::getType()){
         return self::getTypeName(1);
      }

      return '';
   }
   static function countForItem(CommonDBTM $item) {
      $dbu = new DbUtils();
      $table = CommonDBTM::getTable(PluginReleasesReview::class);
      return $dbu->countElementsInTable($table,
         ["plugin_releases_releases_id" => $item->getID()]);
   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      global $CFG_GLPI;
      if ($item->getType() == PluginReleasesRelease::getType()) {
         $self = new self();
         if(self::canCreate()) {
            $review = new PluginReleasesReview();
            if($review->getFromDBByCrit(["plugin_releases_releases_id"=>$item->getField('id')])){
               $ID = $review->getID();
            }else{
               $ID = 0;
            }
            $self->showForm($ID, ['plugin_releases_releases_id' => $item->getField('id'),
               'target' => $CFG_GLPI['root_doc'] . "/plugins/releases/front/review.form.php"]);
         }
      }

   }
   function defineTabs($options = []) {

      $ong = [];
      $this->addDefaultFormTab($ong);
      return $ong;
   }
/**
* Type than could be linked to a Rack
*
* @param $all boolean, all type, or only allowed ones
*
* @return array of types
* */
   static function getTypes($all = false) {

      if ($all) {
         return self::$types;
      }

      // Only allowed types
      $types = self::$types;

      foreach ($types as $key => $type) {
         if (!class_exists($type)) {
            continue;
         }

         $item = new $type();
         if (!$item->canView()) {
            unset($types[$key]);
         }
      }
      return $types;
   }
   function post_addItem() {
      // Add document if needed, without notification
      $this->input = $this->addFiles($this->input, ['force_update' => true]);

      $release = new PluginReleasesRelease();
      $release->getFromDB($this->input['plugin_releases_releases_id']);
      if($release->getField('state')<PluginReleasesRelease::REVIEW){
         $val = [];
         $val['id'] = $release->getID();
         $val['state'] = PluginReleasesRelease::REVIEW;
         $release->update($val);
      }


   }
   function post_updateItem($history = 1) {
      // Add document if needed, without notification
      $this->input = $this->addFiles($this->input, ['force_update' => true]);

   }
   /**
    * Actions done after the PURGE of the item in the database
    *
    * @return void
    **/
   function post_purgeItem() {
      $release = new PluginReleasesRelease();
      $release->getFromDB($this->getField("plugin_releases_releases_id"));
      $val = [];
      $val['id'] = $this->getField("plugin_releases_releases_id");
      $val['state'] = PluginReleasesRelease::FINALIZE;
      $release->update($val);
   }

   function initShowForm($ID, $options = []){
      $this->initForm($ID, $options);
      $this->showFormHeader($options);

   }

   function closeShowForm($options){
      $this->showFormButtons($options);
   }

   function showForm($ID, $options = []) {

      $this->initShowForm($ID,$options);

      $this->coreShowForm($ID,$options);
      $this->closeShowForm($options);

      return true;
   }

   function coreShowForm($ID, $options = []) {
      global $CFG_GLPI, $DB;
      $this->getFromDB($ID);

      echo "<tr class='tab_bg_1'>";
      if (isset($options['plugin_releases_releases_id'])) {


         echo "<td hidden>" . _n('Release', 'Releases', 1, 'releases') . "</td>";
         $rand = mt_rand();

         echo "<td hidden>";
         Dropdown::show(PluginReleasesRelease::getType(),
            ['name' => "plugin_releases_releases_id", 'id' => "plugin_releases_releases_id",
               'value' => $options["plugin_releases_releases_id"],
               'rand' => $rand]);
         echo "</td>";
      } else {
         echo "<td>" . _n('Release', 'Releases', 1, 'releases') . "</td>";
         $rand = mt_rand();

         echo "<td>";
         Dropdown::show(PluginReleasesRelease::getType(), ['name' => "plugin_releases_releases_id", 'id' => "plugin_releases_releases_id",
            'value' => $this->fields["plugin_releases_releases_id"]]);
         echo "</td>";
      }
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>";
      echo __("Real date release",'releases');
      echo "</td>";

      echo "<td>";
      $canedit = true;
      if($this->getField("date_lock") == 1){
         $canedit = false;
      }
      Html::showDateField('real_date_release',["value"=>$this->getField('real_date_release'),'canedit'=>$canedit]);
      echo "</td>";
      echo "<td>" . __('Conforming realization') . "</td>";
      echo "<td>";
      Dropdown::showYesNo("conforming_realization",$this->getField("conforming_realization"));
      echo "</td>";

      echo "</tr>";
      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Incident') . "</td>";
      echo "<td>";
      Dropdown::showYesNo("incident",$this->getField("incident"));
      echo "</td>";
      echo "<td colspan='2'></td>";
      echo "</tr>";
      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Description') . "</td>";
      echo "<td colspan='3'>";
       Html::textarea(["name"=>"incident_description","enable_richtext"=>true,"value"=>$this->getField('incident_description')]);
      echo "</td>";
      echo "</tr>";
      echo "<tr class='tab_bg_1'>";
      echo "<td>".__("Technical Support Document","releases")."</td>";

      echo "<td colspan='3'>";
      $document = new Document_Item();
      $type = PluginReleasesReview::getType();



         $content_id = "content$rand";
         Html::file(['filecontainer' => 'fileupload_info_ticket',
            'editor_id' => $content_id,
            'showtitle' => false,
            'multiple' => true]);
         if($document->find(["itemtype"=>$type,"items_id"=>$this->getID()])) {
         $d = new Document();
         $items_i = $document->find(["itemtype"=>$type,"items_id"=>$this->getID()]);
//         $item_i = reset($items_i);
         foreach ($items_i as $item) {
            $items_i = $d->find(["id"=>$item["documents_id"]]);
            $item_i = reset($items_i);
            $foreignKey = "plugin_releases_reviews_id";
            $pics_url = $CFG_GLPI['root_doc'] . "/pics/timeline";
            //TODO AJOUTER DAT
            if ($item_i['filename']) {
               $filename = $item_i['filename'];
               $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
               echo "<img src='";
               if (empty($filename)) {
                  $filename = $item_i['name'];
               }
               if (file_exists(GLPI_ROOT . "/pics/icones/$ext-dist.png")) {
                  echo $CFG_GLPI['root_doc'] . "/pics/icones/$ext-dist.png";
               } else {
                  echo "$pics_url/file.png";
               }
               echo "'/>&nbsp;";

               echo "<a href='" . $CFG_GLPI['root_doc'] . "/front/document.send.php?docid=" . $item_i['id']
                  . "&$foreignKey=" . $this->getID() . "' target='_blank'>$filename";
               if (Document::isImage(GLPI_DOC_DIR . '/' . $item_i['filepath'])) {
                  echo "<div class='timeline_img_preview'>";
                  echo "<img src='" . $CFG_GLPI['root_doc'] . "/front/document.send.php?docid=" . $item_i['id']
                     . "&$foreignKey=" . $this->getID() . "&context=timeline'/>";
                  echo "</div>";
               }
               echo "</a>";
            }
            if ($item_i['link']) {
               echo "<a href='{$item_i['link']}' target='_blank'><i class='fa fa-external-link'></i>{$item_i['name']}</a>";
            }
            if (!empty($item_i['mime'])) {
               echo "&nbsp;(" . $item_i['mime'] . ")";
            }
            echo "<span class='buttons'>";
            echo "<a href='" . Document::getFormURLWithID($item_i['id']) . "' class='edit_document fa fa-eye pointer' title='" .
               _sx("button", "Show") . "'>";
            echo "<span class='sr-only'>" . _sx('button', 'Show') . "</span></a>";

            $doc = new Document();
            $doc->getFromDB($item_i['id']);
            if ($doc->can($item_i['id'], UPDATE)) {
               echo "<a href='" . static::getFormURL() .
                  "?delete_document&documents_id=" . $item_i['id'] .
                  "&$foreignKey=" . $this->getID() . "' class='delete_document fas fa-trash-alt pointer' title='" .
                  _sx("button", "Delete permanently") . "'>";
               echo "<span class='sr-only'>" . _sx('button', 'Delete permanently') . "</span></a>";
            }
            echo "</span>";
         }
      }

      echo "</td>";
      echo "</tr>";

      return true;
   }

//   function showScripts(PluginReleasesRelease $release) {
//      global $DB, $CFG_GLPI;
//
//      $instID = $release->fields['id'];
//
//      if (!$release->can($instID, READ)) {
//         return false;
//      }
//
//      $rand = mt_rand();
//      $canedit = $release->can($instID, UPDATE);
//
//      $query = "SELECT *
//               FROM `glpi_plugin_release_deploytasks` ";
//      $query .= " WHERE `glpi_plugin_release_deploytasks`.`plugin_release_releases_id` = '$instID'
//          ORDER BY `glpi_plugin_release_deploytasks`.`name`";
//      $result = $DB->query($query);
//      $number = $DB->numrows($result);
//
//      echo "<div class='spaced'>";
//
//      if ($canedit && $number) {
//         Html::openMassiveActionsForm('mass' . __CLASS__ . $rand);
//         $massiveactionparams = [];
//         Html::showMassiveActions(
//            $massiveactionparams);
//      }
//
//
//      echo "<table class='tab_cadre_fixe'>";
//      echo "<tr class='tab_bg_1'>";
//
//      if ($canedit && $number) {
//         echo "<th width='10'>" . Html::getCheckAllAsCheckbox('mass' . __CLASS__ . $rand) . "</th>";
//      }
//
//
//      echo "<th>" . __('Name') . "</th>";
//      echo "<th>" . __('Deploy task type', 'release') . "</th>";
//      echo "<th>" . __('Associated Risk',"release") . "</th>";
//      echo "<th>" . __('Description') . "</th>";
//      echo "<th>" . __('State') . "</th>";
//
//
//      echo "</tr>";
//      if ($number != 0) {
//         $i = 0;
//         $row_num = 1;
//
//         while ($data = $DB->fetch_array($result)) {
//
//
//            $i++;
//            $row_num++;
//            echo "<tr class='tab_bg_1 center'>";
//            echo "<td width='10'>";
//            if ($canedit) {
//               Html::showMassiveActionCheckBox(__CLASS__, $data["id"]);
//            }
//            echo "</td>";
//
//            echo "<td class='center'>";
//            echo "<a href='" . $CFG_GLPI["root_doc"] . "/plugins/release/front/deploytask.form.php?id=" . $data["id"] . "'>";
//            echo $data["name"];
//            if ($_SESSION["glpiis_ids_visible"] || empty($data["name"])) {
//               echo " (" . $data["id"] . ")";
//            }
//            echo "</a></td>";
//            echo "<td >";
//
//
//            echo Dropdown::getDropdownName(PluginReleasesTypeDeployTask::getTable(),$data["plugin_release_typedeploytasks_id"]);
//
//            echo "</td>";
//            echo "<td >";
//
//            echo Dropdown::getDropdownName(PluginReleasesRisk::getTable(),$data["plugin_release_risks_id"]);
//
//            echo "</td>";
//
//            echo "<td >";
//            echo Html::setSimpleTextContent($data["comment"]);
//            echo "</td>";
//            echo "<td >";
//
//            echo Planning::getState($data["state"]);
//
//            echo "</td>";
//
//
//            echo "</tr>";
//         }
//
//      }else{
//
//         echo "<tr class='tab_bg_1 center'>";
//         echo "<td colspan='5'>".__("No data to display","release")."</td>";
//         echo "</tr>";
//
//      }
//
//      if ($canedit && $number) {
//         $paramsma['ontop'] = false;
//         Html::showMassiveActions($paramsma);
//         Html::closeForm();
//      }
//      echo "</table>";
//      echo "</div>";
//   }

}
