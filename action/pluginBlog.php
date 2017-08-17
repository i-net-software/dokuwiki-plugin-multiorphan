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

class action_plugin_multiorphan_pluginBlog extends DokuWiki_Action_Plugin {

    var $plugin_dir;

    function __construct() {
        $this->plugin_blog = plugin_load('helper', 'blog');
    }

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler $controller) {

        if ( !$this->plugin_blog ) { return; }
        $controller->register_hook('MULTIORPHAN_INSTRUCTION_LINKED', 'BEFORE', $this, 'handle_unknown_instructions');
    }

    /**
     * Handles unknown instructions using the Event.
     */
    public function handle_unknown_instructions(Doku_Event &$event) {

        $instructions = $event->data['instructions'];
        if ( $event->data['syntax'] != 'plugin' || !in_array( $instructions[0], array( 'blog_blog', 'blog_archive', 'blog_autoarchive') ) || !$this->plugin_blog ) { return false; }

        $event->data['type'] = 'plugin';

        $data = $this->plugin_blog->getBlog( $instructions[1][0] );
        foreach( $data as $page ) {
            $event->data['additionalEntries'][] = array (
                'type' => 'pages',
                'entryID' => $page['id']
            );
        }

        return true;
    }
}

// vim:ts=4:sw=4:et:
