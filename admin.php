<?php
/**
 * DokuWiki Plugin multiorphan (Admin Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  i-net software <tools@inetsoftware.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

class admin_plugin_multiorphan extends DokuWiki_Admin_Plugin {


    /**
     * return sort order for position in admin menu
     */
    function getMenuSort() {
        return 200;
    }

    function forAdminOnly() {
        return false;
    }

    /**
     * Should carry out any processing required by the plugin.
     */
    public function handle() {
    }

    /**
     * Render HTML output, e.g. helpful text and a form
     */
    public function html() {

        if (!$functions = $this->loadHelper('multiorphan')) {
            msg("Can't initialize the multiorphan plugin");
            return false;
        }
        
        $functions->__multiorphan_gui();
    }
}

// vim:ts=4:sw=4:et: