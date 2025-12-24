jQuery(document).ready(function($) {
    
    /**
     * Position-abhängige Felder ein-/ausblenden
     */
    function togglePositionSettings() {
        var position = $('#bam_position').val();
        
        // Alle Settings ausblenden
        $('#bam_paragraph_settings').hide();
        $('#bam_anchor_settings').hide();
        
        // Je nach Position einblenden
        switch(position) {
            case 'after_paragraph':
            case 'after_heading':
                $('#bam_paragraph_settings').show();
                break;
            case 'anchor':
                $('#bam_anchor_settings').show();
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
     * Syntax-Highlighting Hinweis für PHP
     */
    $('input[name="bam_content_type"]').on('change', function() {
        var isPHP = $(this).val() === 'php';
        if (isPHP) {
            $('#bam_ad_content').attr('placeholder', 
                'echo "Hallo Welt";\n// PHP Code ohne <?php Tags');
        } else {
            $('#bam_ad_content').attr('placeholder', 
                '<div class="my-ad">\n  [my_shortcode]\n</div>');
        }
    });
});
