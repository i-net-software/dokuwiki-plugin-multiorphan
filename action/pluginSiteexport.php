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

class action_plugin_multiorphan_pluginSiteexport extends DokuWiki_Action_Plugin {

    var $plugin_orphan;

    function __construct() {
        $this->plugin_orphan = plugin_load('action', 'multiorphan_multiorphan');
    }

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler $controller) {

        if ( !$this->plugin_orphan ) { return; }
        $controller->register_hook('MULTIORPHAN_INSTRUCTION_LINKED', 'BEFORE', $this, 'handle_unknown_instructions');
    }

    /**
     * Handles unknown instructions using the Event.
     */
    public function handle_unknown_instructions(Doku_Event &$event) {

        $instructions = $event->data['instructions'];
        if ( $event->data['syntax'] != 'plugin' || $instructions[0] != 'siteexport_toc' || !$this->plugin_orphan ) { return false; }
        if ( !is_array($instructions[1]) || array_key_exists('start', $instructions[1]) ) { return false; }

        $instructions = p_get_instructions($instructions[3]);

        $links = array('pages' => array() );
        $this->plugin_orphan->walk_instructions( $links, $event->data['pageID'], $instructions );

        $event->data['type'] = 'plugin';
        foreach( array_keys($links['pages']) as $page ) {
            $event->data['additionalEntries'][] = array (
                'type' => 'pages',
                'entryID' => $page
            );
        }

        return true;
    }
}

// vim:ts=4:sw=4:et:
