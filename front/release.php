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


include('../../../inc/includes.php');
Session::checkLoginUser();
Html::header(PluginReleasesRelease::getTypeName(2), '', "helpdesk", PluginReleasesRelease::getType());

$release = new PluginReleasesRelease();

if ($release->canView()) {
   Html::compileScss(["file"=>"../css/style.scss"]);
     echo Html::Scss("../css/style.scss");
      Search::show(PluginReleasesRelease::getType());

} else {
   Html::displayRightError();
}

Html::footer();
