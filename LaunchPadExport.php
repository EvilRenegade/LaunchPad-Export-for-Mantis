<?php
require_once("lpxmlwriter.php");
class LaunchPadExportPlugin extends MantisPlugin {
    function register() {
        $this->name = 'LaunchPad Export';    # Proper name of plugin
        $this->description = 'This plugin exports the bugs of a given project to LaunchPad\'s bug import XML format.';    # Short description of the plugin
        $this->page = '';           # Default plugin page

        $this->version = '0.1';     # Plugin version string
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
	
	function getLink() {
		return current_user_is_administrator() ? array('<a href="'.plugin_page( 'export' ).'">LaunchPad Export</a>') : array();
	}
	
	/* 	this could be done more efficiently by having the exporter generate a
		static lookup table, but this has the advantage of needing either zero
		configuration (if the user wants no link) or only a single configurative
		change (if the user does want the link).
	*/
	function adjustBugReport($pEventId, $pBugId) {
		$projectName = plugin_config_get( 'launchpad_project' );
		
		if($projectName != 'launchpad') {
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