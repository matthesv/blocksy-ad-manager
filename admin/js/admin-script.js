jQuery(document).ready(function($) {
    
    // Position-abhängige Felder ein-/ausblenden
    function toggleParagraphSettings() {
        var position = $('#bam_position').val();
        var showSettings = ['after_paragraph', 'after_heading'].includes(position);
        
        $('#bam_paragraph_settings').toggle(showSettings);
    }
    
    $('#bam_position').on('change', toggleParagraphSettings);
    toggleParagraphSettings();
    
    // Syntax-Highlighting Hinweis für PHP
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
