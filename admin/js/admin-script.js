jQuery(document).ready(function($) {
    
    /**
     * Content Type Umschalter
     */
    function toggleContentType() {
        var contentType = $('input[name="bam_content_type"]:checked').val();
        
        // Sections ein-/ausblenden
        if (contentType === 'image') {
            $('#bam_content_code').hide();
            $('#bam_content_image').show();
        } else {
            $('#bam_content_code').show();
            $('#bam_content_image').hide();
        }
        
        // Radio Cards aktiv setzen
        $('.bam-radio-card').removeClass('active');
        $('input[name="bam_content_type"]:checked').closest('.bam-radio-card').addClass('active');
        
        // Placeholder für Code-Editor
        if (contentType === 'php') {
            $('#bam_ad_content').attr('placeholder', 
                'echo "Hallo Welt";\n// PHP Code ohne <?php Tags');
        } else {
            $('#bam_ad_content').attr('placeholder', 
                '<div class="my-ad">\n  [my_shortcode]\n</div>');
        }
    }
    
    $('input[name="bam_content_type"]').on('change', toggleContentType);
    toggleContentType();
    
    /**
     * Position-abhängige Felder ein-/ausblenden
     */
    function togglePositionSettings() {
        var position = $('#bam_position').val();
        
        // Alle Settings ausblenden
        $('.bam-position-settings').hide();
        
        // Je nach Position einblenden
        switch(position) {
            case 'after_paragraph':
            case 'after_heading':
                $('#bam_paragraph_settings').show();
                break;
            case 'anchor':
                $('#bam_anchor_settings').show();
                break;
            case 'modal':
                $('#bam_modal_settings').show();
                break;
        }
    }
    
    $('#bam_position').on('change', togglePositionSettings);
    togglePositionSettings();
    
    /**
     * Anchor Close-Einstellungen ein-/ausblenden
     */
    function toggleAnchorCloseSettings() {
        var allowClose = $('input[name="bam_anchor_allow_close"]').is(':checked');
        $('#bam_anchor_close_settings').toggle(allowClose);
    }
    
    $('input[name="bam_anchor_allow_close"]').on('change', toggleAnchorCloseSettings);
    toggleAnchorCloseSettings();
    
    /**
     * Modal Dismiss-Einstellungen ein-/ausblenden
     */
    function toggleModalDismissSettings() {
        var allowDismiss = $('#bam_modal_allow_dismiss_cb').is(':checked');
        $('#bam_modal_dismiss_settings').toggle(allowDismiss);
    }
    
    $('#bam_modal_allow_dismiss_cb').on('change', toggleModalDismissSettings);
    toggleModalDismissSettings();
    
    /**
     * WordPress Media Uploader für Banner
     */
    var mediaUploader;
    
    $('#bam_banner_upload').on('click', function(e) {
        e.preventDefault();
        
        // Falls der Uploader bereits erstellt wurde, öffne ihn
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        
        // Media Uploader erstellen
        mediaUploader = wp.media({
            title: bamAdmin.mediaTitle || 'Banner-Bild auswählen',
            button: {
                text: bamAdmin.mediaButton || 'Bild verwenden'
            },
            library: {
                type: 'image'
            },
            multiple: false
        });
        
        // Wenn ein Bild ausgewählt wurde
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            
            // Versteckte Felder aktualisieren
            $('#bam_banner_image_id').val(attachment.id);
            $('#bam_banner_image_url').val(attachment.url);
            
            // Alt-Text übernehmen falls vorhanden und Feld leer
            if (attachment.alt && !$('#bam_banner_alt').val()) {
                $('#bam_banner_alt').val(attachment.alt);
            }
            
            // Vorschau aktualisieren
            var previewHtml = '<img src="' + attachment.url + '" alt="' + (attachment.alt || '') + '">';
            previewHtml += '<button type="button" class="bam-banner-remove" id="bam_banner_remove" title="Bild entfernen">✕</button>';
            
            $('#bam_banner_preview').html(previewHtml);
            
            // Event-Handler für Remove-Button neu binden
            bindRemoveButton();
        });
        
        mediaUploader.open();
    });
    
    /**
     * Banner entfernen
     */
    function bindRemoveButton() {
        $('#bam_banner_remove').off('click').on('click', function(e) {
            e.preventDefault();
            
            // Felder zurücksetzen
            $('#bam_banner_image_id').val('');
            $('#bam_banner_image_url').val('');
            
            // Placeholder anzeigen
            var placeholderHtml = '<div class="bam-banner-placeholder">';
            placeholderHtml += '<span class="bam-placeholder-icon">📷</span>';
            placeholderHtml += '<span class="bam-placeholder-text">Kein Bild ausgewählt</span>';
            placeholderHtml += '</div>';
            
            $('#bam_banner_preview').html(placeholderHtml);
        });
    }
    
    // Initial binden
    bindRemoveButton();
    
    /**
     * Drag & Drop Upload-Bereich
     */
    var dropZone = $('.bam-banner-upload-area');
    
    if (dropZone.length) {
        dropZone.on('dragover dragenter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('bam-drag-over');
        });
        
        dropZone.on('dragleave dragend drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('bam-drag-over');
        });
        
        dropZone.on('drop', function(e) {
            // Trigger den Media Uploader
            $('#bam_banner_upload').trigger('click');
        });
    }
});
