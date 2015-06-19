
// multiorphan Admin Plugin Script
(function(){

    var canBeStopped = false, $orphanForm = null, $currentResults;

    var init = function() {
        $orphanForm = jQuery('form#multiorphan').submit(loadpages);
    };
    
    /**
     * Load all pages and media
     * Cycle the result.
     */
    var loadpages = function(event) {
        
        if ( canBeStopped ) {
            
            reset();
            errorLog(getLang('request-aborted'));
            return false;
        }
        
        $orphanForm.find('input[type=submit]').val(getLang('stop'));
        event.stopPropagation();
        resetErrorLog();
        canBeStopped = true;
        request({'do':'loadpages'}, function( $result ){

            // Start cycling pages
            $currentResults = $result;
            checkpagesandmedia();
        });
        
        return false;
    };
    
    /**
     * Walk the current elements Tree
     */
    var checkpagesandmedia = function() {
        
        var isPage = true;
        var validateElement = function(result) {
            
            if ( result ) {
                console.log(result);
            }
            
            // A way out of endles execution;
            var elements = isPage ? $currentResults.pages : $currentResults.media;
            if ( elements && elements.length ) {
                var element = elements.pop();
                status(getLang('checking-' + (isPage?'page':'media')) + element);
                request({'do':'checkpage','id':element,'isPage':isPage}, validateElement);
            } else if ( isPage ) {
                isPage = false;
                validateElement();
            } else {
                reset();
            }
        };
        
        validateElement();
    };

    /**
     * Send a request to the plugin.
     */
    var request = function(data, success) {
        data['ns']     = $orphanForm.find('input[name=ns]').val() || JSINFO['id'];
        data['type']   = $orphanForm.find('select[name=type]').val() || 'both';
        data['call']   = 'multiorphan';

        throbber(true);
        jQuery.post(DOKU_BASE + 'lib/exe/ajax.php', data, handleResponse(success)).always(function(){
            throbber(false);
        });
    };

    /**
     * Handles Reponses with JSON from AJAX requests.
     * Also takes care of errors.
     */ 
    var handleResponse = function( next ) {
        return function( response ) {
            // Check for errors
            var $result;
            try {
                $result = jQuery.parseJSON(response);
            } catch( e ) {
                throbber(false);
                return errorLog( getLang('error-parsing') + "\n" + response + "\n\n" + e );
            }

            delete response;
            if ( $result && $result.error ) {
                reset();
                return errorLog( $result.error );
            } else {
                return next( $result );
            }
        }
    };

    /**
     * Set text for status
     */
    var status = function(text) {
        jQuery('#multiorphan__out').html(text).removeClass('error');
    };

    /**
     * Log errors into container
     */
    var errorLog = function(text) {

        if (!text || !text.length) {
            return;
        }

        if (!jQuery('#multiorphan__errorlog').size()) {
            jQuery('#multiorphan__out').parent().append(jQuery('<div id="multiorphan__errorlog"/>'));
        }

        var msg = text.split("\n");
        for ( var int = 0; int < msg.length; int++) {

            var txtMsg = msg[int];
            txtMsg = txtMsg.replace(new RegExp("^runtime error:", "i"), "");

            if (txtMsg.length == 0) {
                continue;
            }

            jQuery('#multiorphan__errorlog').append(jQuery('<p/>').text(txtMsg.replace(new RegExp("</?.*?>", "ig"), "")));
        }
    };

    var resetErrorLog = function() {
        jQuery('#multiorphan__errorlog').remove();
    };
    
            
    /**
     * Display the loading gif
     */
    var throbberCount = 0;
    var throbber = function(on) {
        throbberCount = Math.max(0, throbberCount + (on?1:-1));
        jQuery('#multiorphan__throbber').css('visibility', throbberCount>0 ? 'visible' : 'hidden');
    };
    
    var reset = function() {
        canBeStopped = false;
        delete $currentResults.pages;
        delete $currentResults.media;
        throbber(false);
        $orphanForm.find('input[type=submit]').val(getLang('start'));
    };
    
    var getLang = function(key) {
        return LANG.plugins.multiorphan ? LANG.plugins.multiorphan[key] : key;
    };
    
    jQuery(document).ready(init);
})();