<?php
/*
    LaunchPad Export for Mantis exports the selected bugs into a LaunchPad-schema
    conform XML file.
    Copyright (C) 2011  Charly "Renegade" Kiendl

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
	
*/
/** \mainpage LaunchPad Export for Mantis
	LaunchPad Export for Mantis is a plugin which adds an additional export option
	on View Issues, allowing an administrator to export the selected bugs to LaunchPad's
	import XML schema.
	
	The exported file will be put into the uploads folder and can be linked or downloaded
	from there.
	
	As a secondary component, if the administrator configures the project's LaunchPad name,
	the plugin will display a bright "This bug has moved." notification, with a link to the
	bug's location on LaunchPad.
	
	\section config Configure your LaunchPad project name
	To configure your LaunchPad project name to enable the "bug moved" notice after
	your bugs have been imported into production, proceed as follows:
		- Go to \c Manage -> <tt>Manage Configuration</tt> -> <tt>Configuration Report</tt>
		- Under <tt>Set Configuration Option</tt> add:\n
		  \b Username: All Users\n
		  <b>Project name:</b> Your project\n
		  <b>Configuration Option:</b> plugin_LaunchPadExport_launchpad_project\n
		  \b Type: string\n
		  \b Value: Your project name on LaunchPad.
		- Click the <tt>Set Configuration Option</tt> button.
	
	\section missing Missing features
	This exporter was designed and tested on a single instance of Mantis, for a single
	export venture. As a result, differing configurations may lead to trouble and/or
	the need to patch.\n
	One example would be uploads conversion: The test Mantis had the default "uploads
	in database" configuration, and that's what the code works with. If your uploads
	lie on disk at the moment, you will have to alter the code to read the file contents
	from there instead.\n
	If you do patch the exporter to work with a different configuration, please contribute
	the code back to the project so others can benefit.
	
	\section info Information
	The LaunchPad Export for Mantis plugin was written by Charly "Renegade" Kiendl in
	November 2011 for Mantis 1.2.5. It \e should work for all Mantises of the 1.2.x series,
	but no other version has been tested so far. Patches are welcome.\n
	The plugin is licensed under GPL v3.
*/
require_once("lpxmlwriter.php");

/** \brief Implements a Mantis plugin. Only custom functions are defined, see
	Mantis documentation for the rest.
	\sa http://www.mantisbt.org/docs/master-1.2.x/en/developers.html#DEV.PLUGINS
*/
class LaunchPadExportPlugin extends MantisPlugin {
    function register() {
        $this->name = 'LaunchPad Export';    # Proper name of plugin
        $this->description = 'This plugin exports the bugs of a given project to LaunchPad\'s bug import XML format.';    # Short description of the plugin
        $this->page = '';           # Default plugin page

        $this->version = '1.0';     # Plugin version string
        $this->requires = array(    # Plugin dependencies, array of basename => version pairs
            'MantisCore' => '1.2.0',  #   Should always depend on an appropriate version of MantisBT
            );

        $this->author = 'Renegade';         # Author/team name
        $this->contact = 'Renegade@RenegadeProjects.com';        # Author/team e-mail address
        $this->url = '';            # Support webpage
    }
	
	function hooks() {
		return array(
			'EVENT_MENU_FILTER' => 'getLink',
			'EVENT_VIEW_BUG_DETAILS' => 'adjustBugReport'
		);
	}
	
    function config() {
        return array(
            'launchpad_project' => 'launchpad'
        );
    }
	
	//! Returns an HTML anchor to the LaunchPad Export page; this will be added above the bug list on View Issues, next to the other exporters.
	function getLink() {
		return current_user_is_administrator() ? array('<a href="'.plugin_page( 'export' ).'">LaunchPad Export</a>') : array();
	}
	
	/* 	this could be done more efficiently by having the exporter generate a
		static lookup table, but this has the advantage of needing either zero
		configuration (if the user wants no link) or only a single configurative
		change (if the user does want the link).
	*/
	/** \brief This function is called on display of the bug view page, after the bug
		target version row and before the summary. It writes an additional row
		linking to the bug's new home on LaunchPad.
		\details It uses lpXmlWriter's getLpBugName() as part of this.
	*/
	function adjustBugReport($pEventId, $pBugId) {
		$projectName = plugin_config_get( 'launchpad_project' ); // this config option holds the project's name on LaunchPad
		
		if($projectName != 'launchpad') { // the default value for launchpad_project is "launchpad"; we only display the link-block if the value has been changed.
			$bugName = bug_get_field($pBugId, 'summary');
			$lpBugName = lpXmlWriter::getLpBugName($bugName);
			// might be overkill for one row, but we've avoided raw dumping XML everywhere else in the project, so let's not start now
			$writer = new XMLWriter();
			$writer->openMemory();
			$writer->startElement("tr");
			$writer->writeAttribute("style", "vertical-align: middle;");
			
			$writer->startElement("td");
			$writer->writeAttribute("colspan", "6");
			$writer->writeAttribute("style", "border: 3px solid Red; text-align: center; line-height: 200%;");
			
			$writer->startElement("strong");
			$writer->writeAttribute("style", "text-decoration: blink; color: Red; font-size: x-large;");
			$writer->text("→");
			$writer->endElement();
			
			$writer->text(" This bug has been ");
			$writer->writeElement("strong", "moved");
			$writer->text("! Follow ");
			$writer->startElement("a");
			$writer->writeAttribute("href", sprintf("https://bugs.launchpad.net/%s/+bug/%s", $projectName, $lpBugName));
			$writer->writeAttribute("style", "font-weight: bold;");
			$writer->text("this link");
			$writer->endElement();
			$writer->text(" to get to its current location. ");
			
			$writer->startElement("strong");
			$writer->writeAttribute("style", "text-decoration: blink; color: Red; font-size: x-large;");
			$writer->text("←");
			$writer->endElement();
			
			$writer->endElement();
			
			$writer->endElement();
			echo $writer->flush();
		}
	}
}