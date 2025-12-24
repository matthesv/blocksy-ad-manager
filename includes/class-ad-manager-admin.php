<?php
if (!defined('ABSPATH')) {
    exit;
}

class BAM_Admin {
    
    public function enqueue_scripts($hook) {
        global $post_type;
        
        if ($post_type !== BAM_Post_Type::POST_TYPE) {
            return;
        }
        
        // Media Uploader für Bilder
        wp_enqueue_media();
        
        wp_enqueue_style(
            'bam-admin-style',
            BAM_PLUGIN_URL . 'admin/css/admin-style.css',
            [],
            BAM_VERSION
        );
        
        wp_enqueue_script(
            'bam-admin-script',
            BAM_PLUGIN_URL . 'admin/js/admin-script.js',
            ['jquery'],
            BAM_VERSION,
            true
        );
    }
    
    public function add_meta_boxes() {
        add_meta_box(
            'bam_ad_content',
            __('Anzeigen-Inhalt', 'blocksy-ad-manager'),
            [$this, 'render_content_metabox'],
            BAM_Post_Type::POST_TYPE,
            'normal',
            'high'
        );
        
        add_meta_box(
            'bam_ad_position',
            __('Position & Platzierung', 'blocksy-ad-manager'),
            [$this, 'render_position_metabox'],
            BAM_Post_Type::POST_TYPE,
            'normal',
            'high'
        );
        
        add_meta_box(
            'bam_ad_targeting',
            __('Targeting', 'blocksy-ad-manager'),
            [$this, 'render_targeting_metabox'],
            BAM_Post_Type::POST_TYPE,
            'side',
            'default'
        );
        
        add_meta_box(
            'bam_ad_devices',
            __('Geräte-Sichtbarkeit', 'blocksy-ad-manager'),
            [$this, 'render_devices_metabox'],
            BAM_Post_Type::POST_TYPE,
            'side',
            'default'
        );
    }
    
    public function render_content_metabox($post) {
        wp_nonce_field('bam_save_meta', 'bam_meta_nonce');
        
        $content = get_post_meta($post->ID, '_bam_ad_content', true);
        $content_type = get_post_meta($post->ID, '_bam_content_type', true) ?: 'html';
        ?>
        <div class="bam-metabox-content">
            <p>
                <label><strong><?php _e('Inhaltstyp:', 'blocksy-ad-manager'); ?></strong></label>
            </p>
            <p>
                <label>
                    <input type="radio" name="bam_content_type" value="html" <?php checked($content_type, 'html'); ?>>
                    <?php _e('HTML / Shortcodes', 'blocksy-ad-manager'); ?>
                </label>
                <label style="margin-left: 20px;">
                    <input type="radio" name="bam_content_type" value="php" <?php checked($content_type, 'php'); ?>>
                    <?php _e('PHP Code', 'blocksy-ad-manager'); ?>
                </label>
            </p>
            <p class="description">
                <?php _e('Bei PHP-Code: Ohne öffnende/schließende PHP-Tags eingeben.', 'blocksy-ad-manager'); ?>
            </p>
            <p>
                <textarea name="bam_ad_content" id="bam_ad_content" rows="10" style="width:100%;"><?php echo esc_textarea($content); ?></textarea>
            </p>
        </div>
        <?php
    }
    
    public function render_position_metabox($post) {
        $position = get_post_meta($post->ID, '_bam_position', true) ?: 'after_paragraph';
        $paragraph_number = get_post_meta($post->ID, '_bam_paragraph_number', true) ?: 2;
        
        // Anchor Settings
        $anchor_max_height = get_post_meta($post->ID, '_bam_anchor_max_height', true) ?: '150';
        $anchor_max_height_unit = get_post_meta($post->ID, '_bam_anchor_max_height_unit', true) ?: 'px';
        $anchor_allow_close = get_post_meta($post->ID, '_bam_anchor_allow_close', true) ?: '0';
        $anchor_close_duration = get_post_meta($post->ID, '_bam_anchor_close_duration', true) ?: '24';
        ?>
        <div class="bam-metabox-position">
            <p>
                <label><strong><?php _e('Position:', 'blocksy-ad-manager'); ?></strong></label>
            </p>
            <select name="bam_position" id="bam_position" style="width:100%;">
                <optgroup label="<?php esc_attr_e('Im Inhalt', 'blocksy-ad-manager'); ?>">
                    <option value="after_paragraph" <?php selected($position, 'after_paragraph'); ?>>
                        <?php _e('Nach Absatz X', 'blocksy-ad-manager'); ?>
                    </option>
                    <option value="before_content" <?php selected($position, 'before_content'); ?>>
                        <?php _e('Vor dem Inhalt', 'blocksy-ad-manager'); ?>
                    </option>
                    <option value="after_content" <?php selected($position, 'after_content'); ?>>
                        <?php _e('Nach dem Inhalt', 'blocksy-ad-manager'); ?>
                    </option>
                    <option value="after_heading" <?php selected($position, 'after_heading'); ?>>
                        <?php _e('Nach Überschrift X', 'blocksy-ad-manager'); ?>
                    </option>
                    <option value="middle_content" <?php selected($position, 'middle_content'); ?>>
                        <?php _e('Mitte des Inhalts', 'blocksy-ad-manager'); ?>
                    </option>
                </optgroup>
                <optgroup label="<?php esc_attr_e('Spezielle Formate', 'blocksy-ad-manager'); ?>">
                    <option value="anchor" <?php selected($position, 'anchor'); ?>>
                        📌 <?php _e('Anchor Ad (fixiert unten)', 'blocksy-ad-manager'); ?>
                    </option>
                </optgroup>
            </select>
            
            <!-- Paragraph/Heading Settings -->
            <div id="bam_paragraph_settings" style="margin-top:15px;">
                <label>
                    <strong><?php _e('Nach Element Nummer:', 'blocksy-ad-manager'); ?></strong>
                </label>
                <input type="number" name="bam_paragraph_number" value="<?php echo esc_attr($paragraph_number); ?>" min="1" max="50" style="width:80px;">
            </div>
            
            <!-- Anchor Ad Settings -->
            <div id="bam_anchor_settings" class="bam-position-settings" style="margin-top:15px; display:none;">
                <div class="bam-settings-card">
                    <h4><?php _e('📐 Anchor Ad Einstellungen', 'blocksy-ad-manager'); ?></h4>
                    
                    <p>
                        <label><strong><?php _e('Maximale Höhe:', 'blocksy-ad-manager'); ?></strong></label>
                        <br>
                        <input type="number" name="bam_anchor_max_height" value="<?php echo esc_attr($anchor_max_height); ?>" min="50" max="500" style="width:80px;">
                        <select name="bam_anchor_max_height_unit" style="width:70px;">
                            <option value="px" <?php selected($anchor_max_height_unit, 'px'); ?>>px</option>
                            <option value="vh" <?php selected($anchor_max_height_unit, 'vh'); ?>>vh (%)</option>
                        </select>
                    </p>
                    
                    <p>
                        <label>
                            <input type="checkbox" name="bam_anchor_allow_close" value="1" <?php checked($anchor_allow_close, '1'); ?>>
                            <strong><?php _e('Schließen erlauben', 'blocksy-ad-manager'); ?></strong>
                        </label>
                        <br>
                        <span class="description"><?php _e('Benutzer kann die Anzeige dauerhaft ausblenden.', 'blocksy-ad-manager'); ?></span>
                    </p>
                    
                    <div id="bam_anchor_close_settings" style="margin-top:10px; <?php echo $anchor_allow_close !== '1' ? 'display:none;' : ''; ?>">
                        <label>
                            <strong><?php _e('Ausblenden für:', 'blocksy-ad-manager'); ?></strong>
                        </label>
                        <br>
                        <input type="number" name="bam_anchor_close_duration" value="<?php echo esc_attr($anchor_close_duration); ?>" min="1" max="720" style="width:80px;">
                        <?php _e('Stunden', 'blocksy-ad-manager'); ?>
                        <br>
                        <span class="description"><?php _e('Nach dieser Zeit wird die Anzeige wieder eingeblendet.', 'blocksy-ad-manager'); ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function render_targeting_metabox($post) {
        $post_types = get_post_meta($post->ID, '_bam_post_types', true) ?: ['post'];
        $categories = get_post_meta($post->ID, '_bam_categories', true) ?: [];
        $tags = get_post_meta($post->ID, '_bam_tags', true) ?: [];
        $exclude_ids = get_post_meta($post->ID, '_bam_exclude_ids', true) ?: '';
        
        $available_post_types = get_post_types(['public' => true], 'objects');
        $available_categories = get_categories(['hide_empty' => false]);
        $available_tags = get_tags(['hide_empty' => false]);
        ?>
        <div class="bam-metabox-targeting">
            <p><strong><?php _e('Seitentypen:', 'blocksy-ad-manager'); ?></strong></p>
            <?php foreach ($available_post_types as $pt): ?>
                <?php if ($pt->name === BAM_Post_Type::POST_TYPE) continue; ?>
                <label style="display:block; margin-bottom:5px;">
                    <input type="checkbox" name="bam_post_types[]" value="<?php echo esc_attr($pt->name); ?>" 
                        <?php checked(in_array($pt->name, (array)$post_types)); ?>>
                    <?php echo esc_html($pt->label); ?>
                </label>
            <?php endforeach; ?>
            
            <hr>
            
            <p><strong><?php _e('Kategorien (leer = alle):', 'blocksy-ad-manager'); ?></strong></p>
            <select name="bam_categories[]" multiple style="width:100%; height:100px;">
                <?php foreach ($available_categories as $cat): ?>
                    <option value="<?php echo esc_attr($cat->term_id); ?>" 
                        <?php selected(in_array($cat->term_id, (array)$categories)); ?>>
                        <?php echo esc_html($cat->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <hr>
            
            <p><strong><?php _e('Tags (leer = alle):', 'blocksy-ad-manager'); ?></strong></p>
            <select name="bam_tags[]" multiple style="width:100%; height:100px;">
                <?php foreach ($available_tags as $tag): ?>
                    <option value="<?php echo esc_attr($tag->term_id); ?>" 
                        <?php selected(in_array($tag->term_id, (array)$tags)); ?>>
                        <?php echo esc_html($tag->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <hr>
            
            <p><strong><?php _e('Post-IDs ausschließen:', 'blocksy-ad-manager'); ?></strong></p>
            <input type="text" name="bam_exclude_ids" value="<?php echo esc_attr($exclude_ids); ?>" style="width:100%;" placeholder="1,2,3">
        </div>
        <?php
    }
    
    public function render_devices_metabox($post) {
        $devices = get_post_meta($post->ID, '_bam_devices', true) ?: ['desktop', 'tablet', 'mobile'];
        $is_active = get_post_meta($post->ID, '_bam_is_active', true);
        $is_active = ($is_active === '' || $is_active === '1') ? '1' : '0';
        ?>
        <div class="bam-metabox-devices">
            <p>
                <label>
                    <input type="checkbox" name="bam_is_active" value="1" <?php checked($is_active, '1'); ?>>
                    <strong><?php _e('Anzeige aktiv', 'blocksy-ad-manager'); ?></strong>
                </label>
            </p>
            
            <hr>
            
            <p><strong><?php _e('Anzeigen auf:', 'blocksy-ad-manager'); ?></strong></p>
            
            <label style="display:block; margin-bottom:8px;">
                <input type="checkbox" name="bam_devices[]" value="desktop" 
                    <?php checked(in_array('desktop', (array)$devices)); ?>>
                🖥️ <?php _e('Desktop', 'blocksy-ad-manager'); ?>
                <span class="description">(> 1024px)</span>
            </label>
            
            <label style="display:block; margin-bottom:8px;">
                <input type="checkbox" name="bam_devices[]" value="tablet" 
                    <?php checked(in_array('tablet', (array)$devices)); ?>>
                📱 <?php _e('Tablet', 'blocksy-ad-manager'); ?>
                <span class="description">(768px - 1024px)</span>
            </label>
            
            <label style="display:block; margin-bottom:8px;">
                <input type="checkbox" name="bam_devices[]" value="mobile" 
                    <?php checked(in_array('mobile', (array)$devices)); ?>>
                📱 <?php _e('Mobile', 'blocksy-ad-manager'); ?>
                <span class="description">(< 768px)</span>
            </label>
        </div>
        <?php
    }
    
    public function save_meta_boxes($post_id, $post) {
        // Überprüfungen
        if (!isset($_POST['bam_meta_nonce']) || 
            !wp_verify_nonce($_POST['bam_meta_nonce'], 'bam_save_meta')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if ($post->post_type !== BAM_Post_Type::POST_TYPE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Content speichern (ohne Sanitizing für PHP/HTML)
        if (isset($_POST['bam_ad_content'])) {
            update_post_meta($post_id, '_bam_ad_content', $_POST['bam_ad_content']);
        }
        
        // Content Type
        if (isset($_POST['bam_content_type'])) {
            update_post_meta($post_id, '_bam_content_type', sanitize_text_field($_POST['bam_content_type']));
        }
        
        // Position
        if (isset($_POST['bam_position'])) {
            update_post_meta($post_id, '_bam_position', sanitize_text_field($_POST['bam_position']));
        }
        
        if (isset($_POST['bam_paragraph_number'])) {
            update_post_meta($post_id, '_bam_paragraph_number', absint($_POST['bam_paragraph_number']));
        }
        
        // Anchor Settings
        if (isset($_POST['bam_anchor_max_height'])) {
            update_post_meta($post_id, '_bam_anchor_max_height', absint($_POST['bam_anchor_max_height']));
        }
        
        if (isset($_POST['bam_anchor_max_height_unit'])) {
            $unit = sanitize_text_field($_POST['bam_anchor_max_height_unit']);
            $unit = in_array($unit, ['px', 'vh']) ? $unit : 'px';
            update_post_meta($post_id, '_bam_anchor_max_height_unit', $unit);
        }
        
        $anchor_allow_close = isset($_POST['bam_anchor_allow_close']) ? '1' : '0';
        update_post_meta($post_id, '_bam_anchor_allow_close', $anchor_allow_close);
        
        if (isset($_POST['bam_anchor_close_duration'])) {
            update_post_meta($post_id, '_bam_anchor_close_duration', absint($_POST['bam_anchor_close_duration']));
        }
        
        // Targeting
        $post_types = isset($_POST['bam_post_types']) ? array_map('sanitize_text_field', $_POST['bam_post_types']) : [];
        update_post_meta($post_id, '_bam_post_types', $post_types);
        
        $categories = isset($_POST['bam_categories']) ? array_map('absint', $_POST['bam_categories']) : [];
        update_post_meta($post_id, '_bam_categories', $categories);
        
        $tags = isset($_POST['bam_tags']) ? array_map('absint', $_POST['bam_tags']) : [];
        update_post_meta($post_id, '_bam_tags', $tags);
        
        if (isset($_POST['bam_exclude_ids'])) {
            update_post_meta($post_id, '_bam_exclude_ids', sanitize_text_field($_POST['bam_exclude_ids']));
        }
        
        // Devices
        $devices = isset($_POST['bam_devices']) ? array_map('sanitize_text_field', $_POST['bam_devices']) : [];
        update_post_meta($post_id, '_bam_devices', $devices);
        
        // Active Status
        $is_active = isset($_POST['bam_is_active']) ? '1' : '0';
        update_post_meta($post_id, '_bam_is_active', $is_active);
    }
}
