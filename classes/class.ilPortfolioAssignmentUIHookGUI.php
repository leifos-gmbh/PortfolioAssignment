<?php

/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once("./Services/UIComponent/classes/class.ilUIHookPluginGUI.php");

/**
 * User interface hook class
 *
 * @author Alex Killing <alex.killing@gmx.de>
 * @version $Id$
 */
class ilPortfolioAssignmentUIHookGUI extends ilUIHookPluginGUI
{
	protected $involved_courses = array();

	/**
	 * Modify HTML output of GUI elements. Modifications modes are:
	 * - ilUIHookPluginGUI::KEEP (No modification)
	 * - ilUIHookPluginGUI::REPLACE (Replace default HTML with your HTML)
	 * - ilUIHookPluginGUI::APPEND (Append your HTML to the default HTML)
	 * - ilUIHookPluginGUI::PREPEND (Prepend your HTML to the default HTML)
	 *
	 * @param string $a_comp component
	 * @param string $a_part string that identifies the part of the UI that is handled
	 * @param string $a_par array of parameters (depend on $a_comp and $a_part)
	 *
	 * @return array array with entries "mode" => modification mode, "html" => your html
	 */
	function getHTML($a_comp, $a_part, $a_par = array())
	{
		// do not show the search part of the main menu
		// $a_par["main_menu_gui"]
		if ($a_comp == "Services/MainMenu" && $a_part == "main_menu_list_entries")
		{
			return array("mode" => ilUIHookPluginGUI::APPEND, "html" =>
				$this->getPortfolioAssignmentText());
		}

		return array("mode" => ilUIHookPluginGUI::KEEP, "html" => "");
	}
	
	/**
	 * Get portfolio assignment text
	 *
	 * @return string
	 */
	protected function getPortfolioAssignmentText()
	{
		global $DIC;

		$ctrl = $DIC->ctrl();
		$user = $DIC->user();
		$tree = $DIC->repositoryTree();

		$lng = $DIC->language();
		$main_tpl = $DIC->ui()->mainTemplate();

		$port = false;
		foreach ($ctrl->getCallHistory() as $i)
		{
			if (strtolower($i["class"]) == "ilobjportfoliogui")
			{
				$port = true;
			}
		}


		if (strtolower($_GET["baseClass"]) == "ilpersonaldesktopgui" && $port)
		{
			$port_id = (int) $_GET["prt_id"];

			include_once "Modules/Portfolio/classes/class.ilPortfolioExerciseGUI.php";
			include_once "Modules/Exercise/classes/class.ilExSubmission.php";
			$exercises = ilExSubmission::findUserFiles($user->getId(), $port_id);
			// #0022794
			if (!$exercises)
			{
				$exercises = ilExSubmission::findUserFiles($user->getId(), $port_id.".sec");
			}
			if($exercises)
			{
				foreach ($exercises as $exercise)
				{
					// #9988
					$active_ref = false;
					foreach (ilObject::_getAllReferences($exercise["obj_id"]) as $ref_id)
					{
						if (!$tree->isSaved($ref_id))
						{
							$active_ref = true;
							$exc_ref_id = $ref_id;
							$ass_id = $exercise["ass_id"];
						}
					}
				}
			}
			if ($ass_id > 0)
			{
				$ass = new ilExAssignment($ass_id);

				if ($ass->getInstruction() != "")
				{
					$lng->loadLanguageModule("exc");

					// instruction files
					$files = $ass->getFiles();

					if (count($files) > 0)
					{
						global $DIC;

						//file has -> name,fullpath,size,ctime
						include_once("./Services/MediaObjects/classes/class.ilObjMediaObject.php");
						include_once("./Services/MediaObjects/classes/class.ilMediaPlayerGUI.php");
						include_once "./Services/UIComponent/Modal/classes/class.ilModalGUI.php";

						$files_html = "";
						foreach($files as $file)
						{
							$files_html.= "<p><a href='".
								$this->getSubmissionLink($exc_ref_id, $ass_id, "downloadFile", array("file"=>urlencode($file["name"]))).
								"'>".$file["name"]."</a></p>";
						}
						if ($files_html != "")
						{
							$files_html =
								"<h4 style='background-color:#f3f3f3; padding: 5px; font-weight:bold;'>".$lng->txt("exc_files")."</h4>".
								$files_html;
						}
					}




					$style = "<style>#exc_ass_".$ass->getId()."_tr {display: none;}</style>";

					$code = <<<EOT
					<script>
					
						ilProfAssignmentPluginLayout = function () {
							var tiny_reg = il.Util.getRegion($("#iltinymenu"));
							var instr_reg = il.Util.getRegion($("#instr_port_plugin_container"));
							var vp_reg = il.Util.getViewportRegion();
							//console.log("----");
							//console.log(tiny_reg.height);
							//console.log(instr_reg.top);
							//console.log(vp_reg.top);
							var diff = tiny_reg.height + vp_reg.top - instr_reg.top;
							//console.log(diff)
							if (diff < 0) {
								diff = 0;
							}
							$("#instr_port_plugin_container").css("padding-top", diff + "px");
						};
						
						$(window).resize(ilProfAssignmentPluginLayout);
						$(window).scroll(ilProfAssignmentPluginLayout);

					</script>
EOT;

					$main_tpl->setRightContent("<div id='instr_port_plugin_container'><div id='instr_port_plugin' style='background-color:white; padding: 2px 10px; font-size:90%;'>".
						"<h4 style='background-color:#f3f3f3; padding: 5px; font-weight:bold;'>".$lng->txt("exc_work_instructions")."</h4>".
						$ass->getInstruction().$files_html."</div></div>".$style.$code);
					return;
					$main_tpl->setRightContent("&nbsp;");
					/*return "<div id='portinstr' style='background-color:white; z-index: 1200; display: block; top: 40px; overflow: auto; position: fixed; bottom: 0px; right: 0px; width: 25%; height: 100%;'>".
						nl2br($ass->getInstruction()).
						"</div>".
						"<script>$(function() { $('#portinstr').insertAfter($('body :last-child'))});</script>";*/
				}
			}

		}
		return "";
	}


	protected function getSubmissionLink($exc_ref_id, $ass_id, $a_cmd, array $a_params = null)
	{
		global $DIC;

		$ilCtrl = $DIC->ctrl();

		if(is_array($a_params))
		{
			foreach($a_params as $name => $value)
			{
				$ilCtrl->setParameterByClass("ilexsubmissiongui", $name, $value);
			}
		}

		$ilCtrl->setParameterByClass("ilexsubmissiongui", "ass_id", $ass_id);
		$ilCtrl->setParameterByClass("ilexsubmissiongui", "ref_id", $exc_ref_id);
		$url = $ilCtrl->getLinkTargetByClass(array("ilExerciseHandlerGUI", "ilObjExerciseGUI", "ilexsubmissiongui"), $a_cmd);
		$ilCtrl->setParameterByClass("ilexsubmissiongui", "ass_id", "");
		$ilCtrl->setParameterByClass("ilexsubmissiongui", "ref_id", "");

		if (is_array($a_params))
		{
			foreach ($a_params as $name => $value)
			{
				$ilCtrl->setParameterByClass("ilexsubmissiongui", $name, "");
			}
		}

		return $url;
	}


}
