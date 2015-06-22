
// multiorphan Admin Plugin Script
(function(){

    var canBeStopped = false, $orphanForm = null, $currentPagesAndMedia, $currentResults;

    var init = function() {
        $orphanForm = jQuery('form#multiorphan').submit(loadpages);
        reset();
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
            $currentPagesAndMedia = $result;
            checkpagesandmedia(jQuery.makeArray($result.pages));
        });
        
        return false;
    };
    
    /**
     * Walk the current elements Tree
     */
    var checkpagesandmedia = function(elements) {

        // Cycle pages. Media is implicite.
        var validateElement = function(result) {
            
            // Check if we still have elements in the elements list (cycle-list) and in the resultList (could be stopped.)
            if ( elements && elements.length && $currentPagesAndMedia && $currentPagesAndMedia.pages && $currentPagesAndMedia.pages.length ) {
                var element = elements.pop();
                status(getLang('checking-page') + element);
                request({'do':'checkpage','id':element}, function(response) {
                    checkResponseForOrphans(response, element);
                }).always(validateElement);
            } else {
                
                // All done. Check for Orphans.                
                findOrphans();
                console.log($currentResults);
                
                
                // Now we can leave.
                status(getLang('checking-done'));
                reset();
            }
        };
        
        validateElement();
    };
    
    /**
     * Build up the structure for linked and wanted pages
     */
    var checkResponseForOrphans = function(response, requestPage) {
        
        // Fill the $currentResults object with information.
        var checkResponse = function( id, amount, object ) {

            var checkPoint = amount == 0 ? object.wanted : object.linked;
            if ( !Array.isArray(checkPoint[id]) ) {
                checkPoint[id] = [];
            }
            
            if ( checkPoint[id].indexOf(requestPage) == -1 ) {
                checkPoint[id].push(requestPage);
            }
        }
      
        jQuery.each((response||{}).pages||[], function(page, amount){
            checkResponse(page, amount, $currentResults.pages);
        });
      
        jQuery.each((response||{}).media||[], function(media, amount){
            checkResponse(media, amount, $currentResults.media);
        });
    };
    
    /**
     * walk all linked pages and remove them from the ones that actually exist in the wiki
     * assign the result to the array.
     */
    var findOrphans = function() {

        // Sort out all not 
        var orphaned = function(linked, orphaned) {
            
            if ( !orphaned || !orphaned.length ) return [];
            
            jQuery.each(linked, function(link) {
                if ( (idx = orphaned.indexOf(link)) > -1 ) {
                    orphaned.splice(idx, 1);
                }
            });
            
            return orphaned;
        };

        $currentResults.pages.orphan = orphaned($currentResults.pages.linked, $currentPagesAndMedia.pages);
        $currentResults.media.orphan = orphaned($currentResults.media.linked, $currentPagesAndMedia.media);
    };

    /**
     * Send a request to the plugin.
     */
    var request = function(data, success) {
        data['ns']     = $orphanForm.find('input[name=ns]').val();
        // data['type']   = $orphanForm.find('select[name=type]').val() || 'both';
        data['call']   = 'multiorphan';

        throbber(true);
        return jQuery.post(DOKU_BASE + 'lib/exe/ajax.php', data, handleResponse(success)).always(function(){
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
        
        // Result Object
        $currentResults = {
            
            pages: {
                linked: {},
                wanted: {},
                orphan: []
            },
            media: {
                linked: {},
                wanted: {},
                orphan: []
            }
        };

        // All pages and Media from DW
        $currentPagesAndMedia = {};

        throbber(false);
        $orphanForm.find('input[type=submit]').val(getLang('start'));
    };
    
    var getLang = function(key) {
        return LANG.plugins.multiorphan ? LANG.plugins.multiorphan[key] : key;
    };
    
    jQuery(document).ready(init);
})();