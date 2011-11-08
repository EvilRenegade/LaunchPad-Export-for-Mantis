<?php
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
			'EVENT_MENU_FILTER' => 'getLink'			 
		);
	}
	
	function getLink() {
		return current_user_is_administrator() ? array('<a href="'.plugin_page( 'export' ).'">LaunchPad Export</a>') : array();
	}
}