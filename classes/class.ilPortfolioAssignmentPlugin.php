<?php

include_once("./Services/UIComponent/classes/class.ilUserInterfaceHookPlugin.php");

/**
 * Portfolio assignment plugin
 *
 * @author Alex Killing <alex.killing@gmx.de>
 * @version $Id$
 *
 */
class ilPortfolioAssignmentPlugin extends ilUserInterfaceHookPlugin
{
	function getPluginName()
	{
		return "PortfolioAssignment";
	}
}

?>
