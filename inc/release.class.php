<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 Presales plugin for GLPI
 Copyright (C) 2018 by the Presales Development Team.

 https://github.com/InfotelGLPI/presales
 -------------------------------------------------------------------------

 LICENSE

 This file is part of Presales.

 Presales is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 Presales is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with Presales. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}


/**
 * Class PluginPresalesTask
 */
class PluginReleasesRelease extends CommonDBTM {

   public $dohistory = true;
   static $rightname = 'ticket';
   protected $usenotepad = true;
   static $types = [];

   // STATUS
   const TODO = 1; // todo
   const DONE = 2; // done
   const PROCESSING = 3; // processing
   const WAITING = 4; // waiting
   const LATE = 5; // late
   const DEF = 6; // default

   const NEWRELEASE = 7;
   const RELEASEDEFINITION = 8; // default
   const DATEDEFINITION = 9; // date definition
   const CHANGEDEFINITION = 10; // changes defenition
   const RISKDEFINITION = 11; // risks definition
   const TESTDEFINITION = 12; // tests definition
   const ROLLBACKDEFINITION = 13; // rollbacks definition
   const FINALIZE = 14; // finalized
   const REVIEW = 15; // reviewed
   const CLOSE = 16; // closed





   /**
    * @param int $nb
    *
    * @return translated
    */
   static function getTypeName($nb = 0) {

      return _n('Release', 'Releases', $nb, 'releases');
   }

   static function countForItem($ID,$class,$state = 0) {
      $dbu = new DbUtils();
      $table = CommonDBTM::getTable($class);
      if($state){
         return $dbu->countElementsInTable($table,
            ["plugin_release_releases_id" => $ID,"state"=>2]);
      }
      return $dbu->countElementsInTable($table,
         ["plugin_release_releases_id" => $ID]);
   }


   //TODO
   /**
    * @return array
    */
   function rawSearchOptions() {

      $tab = [];

      $tab[] = [
         'id' => 'common',
         'name' => self::getTypeName(2)
      ];

      $tab[] = [
         'id' => '1',
         'table' => $this->getTable(),
         'field' => 'name',
         'name' => __('name'),
         'datatype' => 'itemlink',
         'itemlink_type' => $this->getType()
      ];
      $tab[] = [
         'id' => '2',
         'table' => $this->getTable(),
         'field' => 'release_area',
         'name' => __('Release Area','releases'),
         'massiveaction' => false,
         'datatype' => 'specific'
      ];
      $tab[] = [
         'id' => '3',
         'table' => $this->getTable(),
         'field' => 'date_preproduction',
         'name' =>  __('Pre-production run date','releases'),
         'massiveaction' => false,
         'datatype' => 'specific'
      ];
      $tab[] = [
         'id' => '4',
         'table' => $this->getTable(),
         'field' => 'is_recursive',
         'name' =>  __('Number of risk','releases'),
         'massiveaction' => false,
         'datatype' => 'specific'
      ];
      $tab[] = [
         'id' => '5',
         'table' => $this->getTable(),
         'field' => 'name',
         'name' =>  __('Number of test','releases'),
         'massiveaction' => false,
         'datatype' => 'specific'
      ];
      $tab[] = [
         'id' => '6',
         'table' => $this->getTable(),
         'field' => 'service_shutdown',
         'name' =>  __('Number of task','releases'),
         'massiveaction' => false,
         'datatype' => 'specific'
      ];
      $tab[] = [
         'id' => '7',
         'table' => $this->getTable(),
         'field' => 'state',
         'name' =>  __('Status'),
         'massiveaction' => false,
         'datatype' => 'specific'
      ];
      $tab[] = [
         'id' => '8',
         'table' => $this->getTable(),
         'field' => 'date_production',
         'name' =>  __('Production run date','releases'),
         'massiveaction' => false,
         'datatype' => 'specific'
      ];
      return $tab;

   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

      if ($item->getType() == self::getType()) {
        return __("Finalization",'releases');
      }
      if ($item->getType() == Change::getType()) {
         return self::getTypeName(1);
      }

      return '';
   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      global $CFG_GLPI;
      if ($item->getType() == self::getType()) {
         $self = new self();
         $self->showFinalisationTabs($item->getID());
      }
      if ($item->getType() == Change::getType()) {
         $change_release = new PluginReleasesChange_Release();
         if($change_release->getFromDBByCrit(['changes_id'=>$item->getID()])){
            $release = new self();
            $release->showForm($change_release->getField("plugin_release_releases_id"),["changestabs"=>1]);

         }else{
            $self = new self();
            $self->showCreateRelease($item);
         }

      }



   }
   function defineTabs($options = []) {

      $ong = [];
      $this->addDefaultFormTab($ong);
      $this->addStandardTab(PluginReleasesChange_Release::getType(), $ong, $options);
      $this->addStandardTab(PluginReleasesRisk::getType(), $ong, $options);
      $this->addStandardTab(PluginReleasesTest::getType(), $ong, $options);
      $this->addStandardTab(PluginReleasesRollback::getType(), $ong, $options);
      $this->addStandardTab(PluginReleasesDeployTask::getType(), $ong, $options);
      $this->addStandardTab(self::getType(), $ong, $options);
      $this->addStandardTab(PluginReleasesReview::getType(), $ong, $options);
      return $ong;
   }

   /**
    * @param datas $input
    *
    * @return datas
    */
   function prepareInputForAdd($input) {

      if (isset($input['target']) && empty($input['target'])) {
         $input['target'] = [];
      }
      $input['target'] = json_encode($input['target']);
      if(!empty($input["date_preproduction"]) && $input["date_preproduction"] !="0000-00-00 00:00:00"  && !empty($input["date_production"]) && $input["date_production"] !="0000-00-00 00:00:00" && $input["state"]<self::DATEDEFINITION  ){

         $input['state'] = self::DATEDEFINITION;

      }else if (!empty($input["release_area"])  &&  $input["state"]<self::RELEASEDEFINITION  ){

         $input['state'] = self::RELEASEDEFINITION;

      }
      return $input;
   }



   /**
    * Actions done after the ADD of the item in the database
    *
    * @return void
    **/
   function post_addItem() {
      global $DB;
      if(isset($this->input["releasetemplates_id"])){
         $template = new PluginReleasesReleasetemplate();
         $template->getFromDB($this->input["releasetemplates_id"]);
         $tests= json_decode($template->getField("tests"));
         $rollbacks= json_decode($template->getField("rollbacks"));
         $tasks= json_decode($template->getField("tasks"));
         $risks = [];
         $releaseTest = new PluginReleasesTest();
         $testTemplate = new PluginReleasesTesttemplate();
         $releaseTask = new PluginReleasesDeployTask();
         $taskTemplate = new PluginReleasesDeploytasktemplate();
         $releaseRollback = new PluginReleasesRollback();
         $rollbackTemplate = new PluginReleasesRollbacktemplate();
         $releaseRisk = new PluginReleasesRisk();
         $riskTemplate = new PluginReleasesRisktemplate();

         foreach ($tests as $test ){
            if($testTemplate->getFromDB($test)){
               $input = $testTemplate->fields;
               $input["plugin_release_releases_id"] = $this->getID();
               if($riskTemplate->getFromDB($input["plugin_release_risks_id"])){
                  if(array_key_exists($input["plugin_release_risks_id"],$risks)){
                     $input["plugin_release_risks_id"] = $risks[$input["plugin_release_risks_id"]];
                  }else {
                     $inputRisk = $riskTemplate->fields;
                     $inputRisk["plugin_release_releases_id"] = $this->getID();
                     unset($inputRisk["id"]);
                     $idRisk = $releaseRisk->add($inputRisk);
                     $risks[$input["plugin_release_risks_id"]] = $idRisk;
                     $input["plugin_release_risks_id"] = $idRisk;
                  }
               }else{
                  $input["plugin_release_risks_id"] = 0;
               }
               unset($input["id"]);
               $releaseTest->add($input);
            }
         }
         foreach ($tasks as $task ){
            if($taskTemplate->getFromDB($task)){
               $input = $taskTemplate->fields;
               $input["plugin_release_releases_id"] = $this->getID();
               if($riskTemplate->getFromDB($input["plugin_release_risks_id"])){
                  if(array_key_exists($input["plugin_release_risks_id"],$risks)){
                     $input["plugin_release_risks_id"] = $risks[$input["plugin_release_risks_id"]];
                  }else {
                     $inputRisk = $riskTemplate->fields;
                     $inputRisk["plugin_release_releases_id"] = $this->getID();
                     unset($inputRisk["id"]);
                     $idRisk = $releaseRisk->add($inputRisk);
                     $risks[$input["plugin_release_risks_id"]] = $idRisk;
                     $input["plugin_release_risks_id"] = $idRisk;
                  }
               }else{
                  $input["plugin_release_risks_id"] = 0;
               }
               unset($input["id"]);
               $releaseTask->add($input);
            }
         }
         foreach ($rollbacks as $rollback ){
            if($rollbackTemplate->getFromDB($rollback)){
               $input = $rollbackTemplate->fields;
               $input["plugin_release_releases_id"] = $this->getID();
               unset($input["id"]);
               $releaseRollback->add($input);
            }
         }

      }
//      $query = "INSERT INTO `glpi_plugin_release_globalstatues`
//                             ( `plugin_release_releases_id`,`itemtype`, `state`)
//                      VALUES (".$this->fields['id'].",'". PluginReleasesRisk::getType()."', 0),
//                      (".$this->fields['id'].",'". PluginReleasesTest::getType()."', 0),
//                      (".$this->fields['id'].",'". PluginReleasesRelease::getType()."', 0),
//                      (".$this->fields['id'].",'". PluginReleasesDeployTask::getType()."', 0),
//                      (".$this->fields['id'].",'PluginReleaseDate', 0),
//                      (".$this->fields['id'].",'". PluginReleasesRollback::getType()."', 0)
//                      ;";
//      $DB->queryOrDie($query, "statues creation");

   }

   /**
    * display a value according to a field
    *
    * @param $field     String         name of the field
    * @param $values    String / Array with the value to display
    * @param $options   Array          of option
    *
    * @return a string
    **@since version 0.83
    *
    */
   static function getSpecificValueToDisplay($field, $values, array $options = []) {

      if (!is_array($values)) {
         $values = [$field => $values];
      }
      switch ($field) {
         case 'state':
            $var = "<span class='status'>";
            $var .=  self::getStatusIcon($values["state"]);
            $var .= self::getStatus($values["state"]);
            $var .= "</span>";
            return $var;
            break;
         case 'name':
            return self::countForItem($options["raw_data"]["id"],PluginReleasesTest::class,1).' / '.self::countForItem($options["raw_data"]["id"],PluginReleasesTest::class);
            break;
         case 'is_recursive':
            return self::countForItem($options["raw_data"]["id"],PluginReleasesRisk::class);
            break;
         case 'service_shutdown':
            return self::countForItem($options["raw_data"]["id"],PluginReleasesDeployTask::class,1).' / '.self::countForItem($options["raw_data"]["id"],PluginReleasesDeployTask::class);
            break;
         case 'release_area':
            return Html::resume_text(Html::clean($values["release_area"]));
            break;
      }
      return parent::getSpecificValueToDisplay($field, $values, $options);
   }

   /**
    * get the Ticket status list
    *
    * @param $withmetaforsearch boolean (false by default)
    *
    * @return array
    **/
   static function getAllStatusArray($releasestatus = false) {

      // To be overridden by class
      if($releasestatus){
         $tab = [
            self::TODO => __( 'To do'),
            self::DONE => __( 'Done'),
            self::PROCESSING  => __('In progress', 'releases'),
            self::WAITING  => __('waiting','releases'),
            self::LATE   => __('Late', 'releases'),
            self::DEF   => __('Default', 'releases'),

            self::NEWRELEASE => _x('status', 'New'),
            self::RELEASEDEFINITION => __( 'Release area defined','releases'),
            self::DATEDEFINITION  => __('Date defined', 'releases'),
            self::CHANGEDEFINITION  => __('Changes defined','releases'),
            self::RISKDEFINITION   => __('Risks defined', 'releases'),
            self::TESTDEFINITION   => __('Tests defined', 'releases'),
            self::ROLLBACKDEFINITION   => __('Rollbacks defined', 'releases'),
            self::FINALIZE   => __('Finalized', 'releases'),
            self::REVIEW   => __('Reviewed', 'releases'),
            self::CLOSE   => _x('status','Close')];
      }else{
         $tab = [
            self::NEWRELEASE => _x('status', 'New'),
            self::RELEASEDEFINITION => __( 'Release area defined','release'),
            self::DATEDEFINITION  => __('Date defined', 'release'),
            self::CHANGEDEFINITION  => __('Changes defined','release'),
            self::RISKDEFINITION   => __('Risks defined', 'release'),
            self::TESTDEFINITION   => __('Tests defined', 'release'),
            self::ROLLBACKDEFINITION   => __('Rollbacks defined', 'release'),
            self::FINALIZE   => __('Finalized', 'release'),
            self::REVIEW   => __('Reviewed', 'release'),
            self::CLOSE   => _x('status','Close')];
      }



      return $tab;
   }

   /**
    * Get status icon
    *
    * @since 9.3
    *
    * @return string
    */
   public static function getStatusIcon($status) {
      $class = static::getStatusClass($status);
      $label = static::getStatus($status);
      return "<i class='$class' title='$label'></i>";
   }

   /**
    * Get ITIL object status Name
    *
    * @since 0.84
    *
    * @param integer $value     status ID
    **/
   static function getStatus($value) {

      $tab  = static::getAllStatusArray(true);
      // Return $value if not defined
      return (isset($tab[$value]) ? $tab[$value] : $value);
   }

   /**
    * Get status class
    *
    * @since 9.3
    *
    * @return string
    */
   public static function getStatusClass($status) {
      $class = null;
      $solid = true;

      switch ($status) {
         case self::TODO :
            $class = 'circle';
            break;
         case self::DONE :
            $class = 'circle';
//            $solid = false;
            break;
         case self::PROCESSING :
            $class = 'circle';
            break;
         case self::WAITING :
            $class = 'circle';
            break;
         case self::LATE :
            $class = 'circle';
//            $solid = false;
            break;
         case self::DEF :
            $class = 'circle';
            break;
         case self::NEWRELEASE :
            $class = 'circle';
            break;
         case self::RELEASEDEFINITION :
            $class = 'circle';
            $solid = false;
            break;
         case self::DATEDEFINITION :
            $class = 'circle';
            $solid = false;
            break;
         case self::CHANGEDEFINITION :
            $class = 'circle';
            $solid = false;
            break;
         case self::RISKDEFINITION :
            $class = 'circle';
            $solid = false;
            break;
         case self::TESTDEFINITION :
            $class = 'circle';
            $solid = false;
            break;
         case self::ROLLBACKDEFINITION :
            $class = 'circle';
            $solid = false;
            break;
         case self::FINALIZE :
            $class = 'circle';
            $solid = false;
            break;
         case self::REVIEW :
            $class = 'circle';
            $solid = false;
            break;
         case self::CLOSE :
            $class = 'circle';
            break;


         default:
            $class = 'circle';
            break;

      }

      return $class == null
         ? ''
         : 'releasestatus ' . ($solid ? 'fas fa-' : 'far fa-') . $class.
         " ".self::getStatusKey($status);
   }

   /**
    * Get status key
    *
    * @since 9.3
    *
    * @return string
    */
   public static function getStatusKey($status) {
      $key = '';
      switch ($status) {
         case self::DONE :
            $key = 'done';
            break;
         case self::TODO :
            $key = 'todo';
            break;

         case self::WAITING :
            $key = 'waiting';
            break;
         case self::PROCESSING :
            $key = 'inprogress';
            break;
         case self::LATE :
            $key = 'late';
            break;
         case self::DEF :
            $key = 'default';
            break;
         case self::NEWRELEASE :
            $key = 'newrelease';
            break;
         case self::RELEASEDEFINITION :
            $key = 'releasedef';
            break;
         case self::DATEDEFINITION :
            $key = 'datedef';
            break;
         case self::CHANGEDEFINITION :
            $key = 'changedef';
            break;
         case self::RISKDEFINITION :
            $key = 'riskdef';
            break;
         case self::TESTDEFINITION :
            $key = 'testdef';
            break;
         case self::ROLLBACKDEFINITION :
            $key = 'rollbackdef';
            break;
         case self::FINALIZE :
            $key = 'finalize';
            break;
         case self::REVIEW :
            $key = 'review';
            break;
         case self::CLOSE :
            $key = 'closerelease';
            break;

      }
      return $key;
   }

   /**
    *
    * @param datas $input
    *
    * @return datas
    */
   function prepareInputForUpdate($input) {

      if ((isset($input['target']) && empty($input['target']))||!isset($input['target'])) {
         $input['target'] = [];
      }
      $input['target'] = json_encode($input['target']);
      if(!empty($input["date_preproduction"])  && !empty($input["date_production"]) && $input["state"]<self::DATEDEFINITION  ){

         $input['state'] = self::DATEDEFINITION;

      }else if (!empty($input["release_area"])  &&  $input["state"]<self::RELEASEDEFINITION  ){

         $input['state'] = self::RELEASEDEFINITION;

      }
      return $input;
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

   function initShowForm($ID, $options = []){


      $this->initForm($ID, $options);
      $this->showFormHeader($options);

   }

   function closeShowForm($options){
      $this->showFormButtons($options);
   }

   function showForm($ID, $options = []) {
      global $DB,$CFG_GLPI;
     // echo "<div style='display: inline-flex;'>";
      $this->initShowForm($ID,$options);

      $this->coreShowForm($ID,$options);
      $this->closeShowForm($options);
     /* $var = '<div id="workflow" class="workflow"></div>';


      echo $var;
      Ajax::updateItem("workflow",$CFG_GLPI["root_doc"] . "/plugins/release/ajax/workflow.php",
         ["id"=>$ID]);
      echo "</div>";*/

      return true;
   }
   function prepareField($template_id){
      $template= new PluginReleasesReleasetemplate();
      $template->getFromDB($template_id);

      foreach ($this->fields as $key => $field){
         if($key!="id"){
            $this->fields[$key] = $template->getField($key);
         }
      }
   }
   function coreShowForm($ID, $options = []) {
      global $CFG_GLPI, $DB;
      if(isset($options["template_id"])&&$options["template_id"]!=0){
         $this->prepareField($options["template_id"]);
         echo  Html::hidden("releasetemplates_id",["value"=>$options["template_id"]]);
      }
      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Name') . "</td>";
      echo "<td>";
      echo Html::input("name",["value"=>$this->getField('name')]);
     
      echo "</td>";
      echo "<td>";
      if(isset($options["changestabs"])){
         echo "<a href='".$this->getFormURL()."?id=$ID'>";
         echo __('Go to the release',"release");
         echo "</a>";
      }
      echo "</td>";
      echo "<td>";
      Dropdown::showFromArray('state',self::getAllStatusArray(false),['value'=>$this->getField('state')]);
//      echo self::getStatus($this->getField('state'));
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Release area','release') . "</td>";
      echo "<td colspan='3'>";
       Html::textarea(["name"=>"release_area","enable_richtext"=>true,"value"=>$this->getField('release_area')]);
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Pre-production run date','release') . "</td>";
      echo "<td >";
      Html::showDateField("date_preproduction",["value"=>$this->getField('date_preproduction')]);
      echo "</td>";
      echo "<td>" . __('Production run date','release') . "</td>";
      echo "<td >";
      Html::showDateField("date_production",["value"=>$this->getField('date_production')]);
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Service shutdown','release') . "</td>";
      echo "<td >";
      Dropdown::showYesNo("service_shutdown",$this->getField('service_shutdown'));
      echo "</td>";
      echo "<td colspan='2'>";
      Html::textarea(["name"=>"service_shutdown_details","enable_richtext"=>true,"value"=>$this->getField('service_shutdown_details')]);
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Non-working hour','release') . "</td>";
      echo "<td >";
      Dropdown::showYesNo("hour_type",$this->getField('hour_type'));
      echo "</td>";
      echo "<td>" . __('Communication','release') . "</td>";
      echo "<td >";
      Dropdown::showYesNo("communication",$this->getField('communication'));
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Communication type','release') . "</td>";
      echo "<td >";
      $types   = ['Entity'=>'Entity', 'Group'=>'Group', 'Profile'=>'Profile', 'User'=>'User','Location'=>'Location'];
      $addrand = Dropdown::showItemTypes('communication_type', $types,["id"=>"communication_type","value"=>$this->getField('communication_type')]);
      echo "</td>";
      $targets = json_decode($this->getField('target'));
      echo "<td>" ._n('Target', 'Targets',
            Session::getPluralNumber()) . "</td>";


      echo "<td id='targets'>";


      echo "</td>";
      Ajax::updateItem( "targets",
         $CFG_GLPI["root_doc"] . "/plugins/release/ajax/changeTarget.php",
         ['type' => $this->getField('communication_type'),'current_type'=>$this->getField('communication_type'),'values'=>$targets], true);
      Ajax::updateItemOnSelectEvent("dropdown_communication_type".$addrand, "targets",
         $CFG_GLPI["root_doc"] . "/plugins/release/ajax/changeTarget.php",
         ['type' => '__VALUE__','current_type'=>$this->getField('communication_type'),'values'=>$targets], true);
      echo "</tr>";
      return true;
   }

   function showFinalisationTabs($ID){
      global $CFG_GLPI;
      $this->getFromDB($ID);

      echo "<table class='tab_cadre_fixe' id='mainformtable'>";
      echo "<tr class='tab_bg_1'>";
      echo "<td>";
      echo __('Risk','release');
      echo "</td>";
      echo "<td>";
      echo self::getStateItem($this->getField("risk_state"));
      echo "</td>";
      echo "</tr>";
      echo "<tr class='tab_bg_1'>";
      echo "<td>";

      echo __('Test','release');
      echo "</td>";
      echo "<td>";
      echo self::getStateItem($this->getField("test_state"));
      echo "</td>";
      echo "</tr>";
      echo "<tr class='tab_bg_1'>";
      echo "<td>";
      echo __('Rollback','release');
      echo "</td>";
      echo "<td>";
      echo self::getStateItem($this->getField("rollback_state"));
      echo "</td>";
      echo "</tr>";
      echo "<tr class='tab_bg_1'>";
      echo "<td>";
      echo __('Deploy Task','release');
      echo "</td>";
      echo "<td>";
      $dtF = self::countForItem($ID,PluginReleasesDeployTask::class,1);
      $dtT = self::countForItem($ID ,PluginReleasesDeployTask::class);
      echo $dtF;
      echo "/";
      echo $dtT;
      echo "</td>";
      echo "</tr>";




      echo "</table>";
      $allfinish =  $this->getField("risk_state") && ($dtT == $dtF) && $this->getField("test_state") && $this->getField("rollback_state");
      if(!$allfinish){

         echo '<span style="color: red" class="center"> '.__("Care all steps are not finish !").'</span>';
         echo "<br>";
      }
      if($this->getField('state')<self::FINALIZE){
         echo '<a id="finalize" class="vsubmit"> '.__("Finalize",'release').'</a>';

       echo  Html::scriptBlock(
 "$('#finalize').click(function(){
            $.ajax({
               url:  '".$CFG_GLPI['root_doc']."/plugins/release/ajax/finalize.php',
               data: {'id' : ".$this->getID()."},
               success: function() {
                  document.location.reload();
               }
            });
         });");
      }
      function getField($field) {

         if($field == "content"){
            return $this->fields["service_shutdown_details"];
         }else{
            return parent::getField($field);
         }
         if (array_key_exists($field, $this->fields)) {
            return $this->fields[$field];
         }
         return NOT_AVAILABLE;
      }


   }


   public static function getStateItem($state){
      switch ($state){
         case 0:
            return __("Waiting","release");
            break;
         case 1:
            return __("Done");
            break;
      }
   }

   function showTimelineForm($rand,$obj) {

      global $CFG_GLPI;



      $task  = new $obj();
      $canadd_task = $task->can(-1, CREATE);
      $taskClass = $obj;

      // javascript function for add and edit items
      $objType = self::getType();
      $foreignKey = self::getForeignKeyField();

      echo "<script type='text/javascript' >
      function change_task_state(tasks_id, target,type) {
         $.post('".$CFG_GLPI["root_doc"]."/plugins/release/ajax/updateState.php',
                {'action':     'change_task_state',
                  'tasks_id':   tasks_id,
                  'type': type,
                  'parenttype': '$objType',
                  '$foreignKey': ".$this->fields['id']."
                })
                .done(function(response) {
                                  $(target).removeClass('state_1 state_2')
                           .addClass('state_'+response.state)
                           .attr('title', response.label);
                })
                .fail(function( jqXHR, textStatus, errorThrown ){
                  console.log('erreur');
                  console.log(jqXHR);
                  console.log(textStatus);
                  console.log(errorThrown);
                });
      }

      function viewEditSubitem" . $this->fields['id'] . "$rand(e, itemtype, items_id, o, domid) {
               domid = (typeof domid === 'undefined')
                         ? 'viewitem".$this->fields['id'].$rand."'
                         : domid;
               var target = e.target || window.event.srcElement;
               if (target.nodeName == 'a') return;
               if (target.className == 'read_more_button') return;

               var _eltsel = '[data-uid='+domid+']';
               var _elt = $(_eltsel);
               _elt.addClass('edited');
               $(_eltsel + ' .displayed_content').hide();
               $(_eltsel + ' .cancel_edit_item_content').show()
                                                        .click(function() {
                                                            $(this).hide();
                                                            _elt.removeClass('edited');
                                                            $(_eltsel + ' .edit_item_content').empty().hide();
                                                            $(_eltsel + ' .displayed_content').show();
                                                        });
               $(_eltsel + ' .edit_item_content').show()
                                                 .load('".$CFG_GLPI["root_doc"]."/plugins/release/ajax/viewsubitem.php',
                                                       {'action'    : 'viewsubitem',
                                                        'type'      : itemtype,
                                                        'parenttype': '$objType',
                                                        '$foreignKey': ".$this->fields['id'].",
                                                        'id'        : items_id
                                                       });
      };
      </script>";

      if (!$canadd_task) {
         return false;
      }

      echo "<script type='text/javascript' >\n";
      echo "function viewAddSubitem" . $this->fields['id'] . "$rand(itemtype) {\n";
      $params = ['action'     => 'viewsubitem',
         'type'       => 'itemtype',
         'parenttype' => $objType,
         $foreignKey => $this->fields['id'],
         'id'         => -1];
      if (isset($_GET['load_kb_sol'])) {
         $params['load_kb_sol'] = $_GET['load_kb_sol'];
      }

      $out = Ajax::updateItemJsCode("viewitem" . $this->fields['id'] . "$rand",
         $CFG_GLPI["root_doc"]."/plugins/release/ajax/viewsubitem.php",
         $params, "", false);
      echo str_replace("\"itemtype\"", "itemtype", $out);
      echo "$('#approbation_form$rand').remove()";
      echo "};";


      echo "</script>\n";

      //show choices
      echo "<div class='timeline_form'>";
      echo "<ul class='timeline_choices'>";

      if ($canadd_task) {
         echo "<h2>"._sx('button', 'Add')." : </h2>";
      }
      if ($canadd_task) {
         $class = $obj::getCssClass();
         echo "<li class='$class' onclick='".
            "javascript:viewAddSubitem".$this->fields['id']."$rand(\"$taskClass\");'>"
            ."<i class='far fa-check-square'></i>".$obj::getTypeName(1)."</li>";
      }
      Plugin::doHook('timeline_actions', ['item' => $this, 'rand' => $rand]);

      echo "</ul>"; // timeline_choices
      echo "<div class='clear'>&nbsp;</div>";

      echo "</div>"; //end timeline_form

      echo "<div class='ajax_box' id='viewitem" . $this->fields['id'] . "$rand'></div>\n";
   }

   function showTimeLine($rand,$obj){
      global $DB, $CFG_GLPI, $autolink_options;

      $objType = self::getType();
      $user              = new User();
      $group             = new Group();
      $pics_url          = $CFG_GLPI['root_doc']."/pics/timeline";
      $timeline          = $this->getTimelineItems($obj);

      $autolink_options['strip_protocols'] = false;



      echo "<div class='timeline_history'>";
      $timeline_index = 0;
      foreach ($timeline as $item) {
         $options = [ 'parent' => $this,
            'rand' => $rand
         ];
         if ($obj = getItemForItemtype($item['type'])) {
            $obj->fields = $item['item'];
         } else {
            $obj = $item;
         }

         if (is_array($obj)) {
            $item_i = $obj['item'];
         } else {
            $item_i = $obj->fields;
         }

         $date = "";
         if (isset($item_i['date'])) {
            $date = $item_i['date'];
         } else if (isset($item_i['date_mod'])) {
            $date = $item_i['date_mod'];
         }

         // set item position depending on field timeline_position
         $user_position = 'left'; // default position
//         if (isset($item_i['timeline_position'])) {
//            switch ($item_i['timeline_position']) {
//               case self::TIMELINE_LEFT:
//                  $user_position = 'left';
//                  break;
//               case self::TIMELINE_MIDLEFT:
//                  $user_position = 'left middle';
//                  break;
//               case self::TIMELINE_MIDRIGHT:
//                  $user_position = 'right middle';
//                  break;
//               case self::TIMELINE_RIGHT:
//                  $user_position = 'right';
//                  break;
//            }
//         }


         echo "<div class='h_item $user_position'>";

         echo "<div class='h_info'>";

         echo "<div class='h_date'><i class='far fa-clock'></i>".Html::convDateTime($date)."</div>";
         if ($item_i['users_id'] !== false) {
            echo "<div class='h_user'>";
            if (isset($item_i['users_id']) && ($item_i['users_id'] != 0)) {
               $user->getFromDB($item_i['users_id']);

               echo "<div class='tooltip_picture_border'>";
               echo "<img class='user_picture' alt=\"".__s('Picture')."\" src='".
                  User::getThumbnailURLForPicture($user->fields['picture'])."'>";
               echo "</div>";

               echo "<span class='h_user_name'>";
               $userdata = getUserName($item_i['users_id'], 2);
               echo $user->getLink()."&nbsp;";
               echo Html::showToolTip($userdata["comment"],
                  ['link' => $userdata['link']]);
               echo "</span>";
            } else {
               echo __("Requester");
            }
            echo "</div>"; // h_user
         }

         echo "</div>"; //h_info

         $domid = "viewitem{$item['type']}{$item_i['id']}";
         if ($item['type'] == $objType.'Validation' && isset($item_i['status'])) {
            $domid .= $item_i['status'];
         }
         $randdomid = $domid . $rand;
         $domid = Toolbox::slugify($domid);

         $fa = null;
         $class = "h_content";
         if($item['type'] != "Document_Item"){
            $class .= " {$item['type']::getCssClass()}";
         }else{
            $class .= " ".$item['type'];
         }


//         $class .= " {$item_i['state']}";


         echo "<div class='$class' id='$domid' data-uid='$randdomid'>";
         if ($fa !== null) {
            echo "<i class='solimg fa fa-$fa fa-5x'></i>";
         }
         if (isset($item_i['can_edit']) && $item_i['can_edit']) {
            echo "<div class='edit_item_content'></div>";
            echo "<span class='cancel_edit_item_content'></span>";
         }
         echo "<div class='displayed_content'>";
         echo "<div class='h_controls'>";
         if (!in_array($item['type'], ['Document_Item', 'Assign'])
            && $item_i['can_edit']
         ) {
            // merge/split icon

            // edit item
            echo "<span class='far fa-edit control_item' title='".__('Edit')."'";
            echo "onclick='javascript:viewEditSubitem".$this->fields['id']."$rand(event, \"".$item['type']."\", ".$item_i['id'].", this, \"$randdomid\")'";
            echo "></span>";
         }

         // show "is_private" icon
         if (isset($item_i['is_private']) && $item_i['is_private']) {
            echo "<span class='private'><i class='fas fa-lock control_item' title='" . __s('Private') .
               "'></i><span class='sr-only'>".__('Private')."</span></span>";
         }

         echo "</div>";
         if (isset($item_i['requesttypes_id'])
            && file_exists("$pics_url/".$item_i['requesttypes_id'].".png")) {
            echo "<img src='$pics_url/".$item_i['requesttypes_id'].".png' class='h_requesttype' />";
         }

         if (isset($item_i['content'])) {
            $content = "<h2>".$item_i['name']."  </h2>".$item_i['content'];
            $content = Toolbox::getHtmlToDisplay($content);
            $content = autolink($content, false);

            $long_text = "";
            if ((substr_count($content, "<br") > 30) || (strlen($content) > 2000)) {
               $long_text = "long_text";
            }

            echo "<div class='item_content $long_text'>";
            echo "<p>";
            if (isset($item_i['state'])) {
               $onClick = "onclick='change_task_state(".$item_i['id'].", this,\"".$item['type']."\")'";
               if (!$item_i['can_edit']) {
                  $onClick = "style='cursor: not-allowed;'";
               }
               echo "<span class='state state_".$item_i['state']."'
                           $onClick
                           title='".Planning::getState($item_i['state'])."'>";
               echo "</span>";
            }
            echo "</p>";

            echo "<div class='rich_text_container'>";
            echo Html::setRichTextContent('', $content, '', true);
            echo "</div>";

            if (!empty($long_text)) {
               echo "<p class='read_more'>";
               echo "<a class='read_more_button'>.....</a>";
               echo "</p>";
            }
            echo "</div>";
         }

         echo "<div class='b_right'>";

         if (isset($item_i['plugin_release_typedeploytasks_id']) && !empty($item_i['plugin_release_typedeploytasks_id'])) {
            echo Dropdown::getDropdownName("glpi_plugin_release_typedeploytasks", $item_i['plugin_release_typedeploytasks_id'])."<br>";
         }
         if (isset($item_i['plugin_release_typerisks_id']) && !empty($item_i['plugin_release_typerisks_id'])) {
            echo Dropdown::getDropdownName("glpi_plugin_release_typerisks", $item_i['plugin_release_typerisks_id'])."<br>";
         }
         if (isset($item_i['plugin_release_typetests_id']) && !empty($item_i['plugin_release_typetests_id'])) {
            echo Dropdown::getDropdownName("glpi_plugin_release_typetests", $item_i['plugin_release_typetests_id'])."<br>";
         }
         if (isset($item_i['plugin_release_risks_id']) && !empty($item_i['plugin_release_risks_id'])) {
            echo __("Associated with")." ";
            echo Dropdown::getDropdownName("glpi_plugin_release_risks", $item_i['plugin_release_risks_id'])."<br>";
         }


         if (isset($item_i['actiontime']) && !empty($item_i['actiontime'])) {
            echo "<span class='actiontime'>";
            echo Html::timestampToString($item_i['actiontime'], false);
            echo "</span>";
         }
         if (isset($item_i['begin'])) {
            echo "<span class='planification'>";
            echo Html::convDateTime($item_i["begin"]);
            echo " &rArr; ";
            echo Html::convDateTime($item_i["end"]);
            echo "</span>";
         }


         if (isset($item_i['users_id_editor']) && $item_i['users_id_editor'] > 0) {
            echo "<div class='users_id_editor' id='users_id_editor_".$item_i['users_id_editor']."'>";
            $user->getFromDB($item_i['users_id_editor']);
            $userdata = getUserName($item_i['users_id_editor'], 2);
            if(isset($item_i['date_mod']))
            echo sprintf(
               __('Last edited on %1$s by %2$s'),
               Html::convDateTime($item_i['date_mod']),
               $user->getLink()
            );
            echo Html::showToolTip($userdata["comment"],
               ['link' => $userdata['link']]);
            echo "</div>";
         }

         echo "</div>"; // b_right
         if ($item['type'] == 'Document_Item') {
            if ($item_i['filename']) {
               $filename = $item_i['filename'];
               $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
               echo "<img src='";
               if (empty($filename)) {
                  $filename = $item_i['name'];
               }
               if (file_exists(GLPI_ROOT."/pics/icones/$ext-dist.png")) {
                  echo $CFG_GLPI['root_doc']."/pics/icones/$ext-dist.png";
               } else {
                  echo "$pics_url/file.png";
               }
               echo "'/>&nbsp;";

               echo "<a href='".$CFG_GLPI['root_doc']."/front/document.send.php?docid=".$item_i['id']
                  ."&PluginReleasesRelease=".$this->getID()."' target='_blank'>$filename";
               if (Document::isImage(GLPI_DOC_DIR . '/' . $item_i['filepath'])) {
                  echo "<div class='timeline_img_preview'>";
                  echo "<img src='".$CFG_GLPI['root_doc']."/front/document.send.php?docid=".$item_i['id']
                     ."&PluginReleasesRelease=".$this->getID()."&context=timeline'/>";
                  echo "</div>";
               }
               echo "</a>";
            }
            if ($item_i['link']) {
               echo "<a href='{$item_i['link']}' target='_blank'><i class='fa fa-external-link'></i>{$item_i['name']}</a>";
            }
            if (!empty($item_i['mime'])) {
               echo "&nbsp;(".$item_i['mime'].")";
            }
            echo "<span class='buttons'>";
            echo "<a href='".Document::getFormURLWithID($item_i['id'])."' class='edit_document fa fa-eye pointer' title='".
               _sx("button", "Show")."'>";
            echo "<span class='sr-only'>" . _sx('button', 'Show') . "</span></a>";

            $doc = new Document();
            $doc->getFromDB($item_i['id']);
            if ($doc->can($item_i['id'], UPDATE)) {
               echo "<a href='".static::getFormURL().
                  "?delete_document&documents_id=".$item_i['id'].
                  "&PluginReleasesRelease=".$this->getID()."' class='delete_document fas fa-trash-alt pointer' title='".
                  _sx("button", "Delete permanently")."'>";
               echo "<span class='sr-only'>" . _sx('button', 'Delete permanently')  . "</span></a>";
            }
            echo "</span>";
         }

         echo "</div>"; // displayed_content
         echo "</div>"; //end h_content

         echo "</div>"; //end  h_info

         $timeline_index++;
      } // end foreach timeline
      echo "</div>";
   }


   function getTimelineItems($obj) {

      $objType = self::getType();
      $foreignKey = self::getForeignKeyField();

      $timeline = [];

      $user = new User();



      $item              = new $obj;


      //checks rights
      $restrict_fup = $restrict_task = [];


      if ($item->maybePrivate() && !Session::haveRight("task", CommonITILTask::SEEPRIVATE)) {
         $restrict_task = [
            'OR' => [
               'is_private'   => 0,
               'users_id'     => Session::getLoginUserID()
            ]
         ];
      }


      if ($item->canview()) {
         $tasks = $item->find([$foreignKey => $this->getID()] + $restrict_task );
         foreach ($tasks as $tasks_id => $task) {
            $item->getFromDB($tasks_id);
            $task['can_edit']                           = $item->canUpdateItem();
            if(isset($task['date_creation'])){
               $timeline[$task['date_creation']."_task_".$tasks_id] = ['type' => $obj,
                  'item' => $task,
               ];
            }else{
               $timeline["_task_".$tasks_id] = ['type' => $obj,
                  'item' => $task,
               ];
            }

         }
      }
      if($obj == "PluginReleasesDeployTask") {


         $document_item_obj = new Document_Item();
         $document_obj = new Document();
         $document_items = $document_item_obj->find(['itemtype' => self::getType(), 'items_id' => $this->getID()]);
         foreach ($document_items as $document_item) {
            $document_obj->getFromDB($document_item['documents_id']);

            $item = $document_obj->fields;
            // #1476 - set date_mod and owner to attachment ones
            $item['date_mod'] = $document_item['date_mod'];
            $item['users_id'] = $document_item['users_id'];

            $item['timeline_position'] = $document_item['timeline_position'];

            $timeline[$document_item['date_mod'] . "_document_" . $document_item['documents_id']]
               = ['type' => 'Document_Item', 'item' => $item];
         }
      }


      //reverse sort timeline items by key (date)
      krsort($timeline);

      return $timeline;
   }

   /**
    * Dropdown of release items state
    *
    * @param $name   select name
    * @param $value  default value (default '')
    * @param $display  display of send string ? (true by default)
    * @param $options  options
    **/
   static function dropdownStateItem($name, $value = '', $display = true, $options = []) {

      $values = [static::TODO => __('To do'),
         static::DONE => __('Done')];

      return Dropdown::showFromArray($name, $values, array_merge(['value'   => $value,
         'display' => $display], $options));
   }

   /**
    * Dropdown of release state
    *
    * @param $name   select name
    * @param $value  default value (default '')
    * @param $display  display of send string ? (true by default)
    * @param $options  options
    **/
   static function dropdownState($name, $value = '', $display = true, $options = []) {

      $values = [static::TODO => __('To do'),
         static::DONE => __('Done'),
         static::PROCESSING => __('Processing'),
         static::WAITING => __("Waiting"),
         static::LATE => __("Late"),
         static::DEF => __("Default"),
         ];

      return Dropdown::showFromArray($name, $values, array_merge(['value'   => $value,
         'display' => $display], $options));
   }

   function showStateItem($field = "",$text = "",$state){
      global $CFG_GLPI;

      echo "<div colspan='4' class='center'>".$text ."</div>";
      echo "<div id='fakeupdate'></div>";

      echo "<div class='center'>";
      $rand = mt_rand();
      Dropdown::showYesNo($field,$this->getField($field),-1,["rand"=>$rand]);
      $params = ['value'=>"__VALUE__","field"=>$field,"plugin_release_releases_id"=>$this->getID(),'state'=>$state];
      Ajax::updateItemOnSelectEvent("dropdown_$field$rand","fakeupdate",$CFG_GLPI["root_doc"]."/plugins/release/ajax/changeitemstate.php",$params);

      echo "</div>";

   }
   function showCreateRelease($item){


      echo "<form name='form' method='post' action='".$this->getFormURL()."'  enctype=\"multipart/form-data\">";
      echo Html::hidden("changes_id",["value"=>$item->getID()]);
//      echo '<a class="vsubmit"> '.__("Create a release from this change",'release').'</a>';
      echo Html::submit(__("Create a release from this change",'release'), ['name' => 'createRelease']);
      Html::closeForm();
   }

   /**
    * @return array
    */
   static function getMenuContent() {

      $menu['title'] = self::getMenuName(2);
      $menu['page'] = self::getSearchURL(false);
      $menu['links']['search'] = self::getSearchURL(false);

      $menu['links']['template'] = "/plugins/releases/front/releasetemplate.php";
      if (self::canCreate()) {
         $dbu = new DbUtils();
         $template = new PluginReleasesReleasetemplate();
         $condition = $dbu->getEntitiesRestrictCriteria($template->getTable());
         $templates = $template->find($condition);
         if(empty($templates)){
            $menu['links']['add'] = self::getFormURL(false);
         }else{
            $temp = new PluginReleasesReleasetemplate();
            $menu['links']['add'] = PluginReleasesCreationRelease::getSearchURL(false);
         }
      }


      return $menu;
   }

}

