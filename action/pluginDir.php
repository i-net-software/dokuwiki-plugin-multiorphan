<?php
/**
 * DokuWiki Plugin multiorphan (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  i-net software <tools@inetsoftware.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) {
    die();
}

class action_plugin_multiorphan_pluginDir extends DokuWiki_Action_Plugin {

    var $plugin_dir;

    function __construct() {
        $this->plugin_dir = plugin_load('syntax', 'dir');
    }

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler $controller) {

        if ( !$this->plugin_dir ) { return; }
        $controller->register_hook('MULTIORPHAN_INSTRUCTION_LINKED', 'BEFORE', $this, 'handle_unknown_instructions');
    }

    /**
     * Handles unknown instructions using the Event.
     */
    public function handle_unknown_instructions(Doku_Event &$event) {

        $instructions = $event->data['instructions'];
        if ( $event->data['syntax'] != 'plugin' || $instructions[0] != 'dir' || !$this->plugin_dir ) { return false; }

        $data = $this->plugin_dir->handle($instructions[1], null, null, new Doku_Handler());
        $this->plugin_dir->_initRender('xhtml', new Doku_Renderer_xhtml());
        
        $this->plugin_dir->debug = true;
        if ( !$this->plugin_dir->_parseOptions($data) ) {
            print $this->plugin_dir->rdr->doc;
            return false;
        }

        $event->data['type'] = 'plugin';
        foreach( $this->plugin_dir->pages as $page ) {
            $event->data['additionalEntries'][] = array (
                'type' => 'pages',
                'entryID' => $page['id']
            );
        }

        return true;
    }
}

// vim:ts=4:sw=4:et:
