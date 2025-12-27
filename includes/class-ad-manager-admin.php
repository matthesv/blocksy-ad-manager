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
        
        // Lokalisierung für JS
        wp_localize_script('bam-admin-script', 'bamAdmin', [
            'mediaTitle'  => __('Banner-Bild auswählen', 'blocksy-ad-manager'),
            'mediaButton' => __('Bild verwenden', 'blocksy-ad-manager'),
        ]);
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
        
        // Banner/Image Settings
        $banner_image_id = get_post_meta($post->ID, '_bam_banner_image_id', true);
        $banner_image_url = get_post_meta($post->ID, '_bam_banner_image_url', true);
        $banner_link = get_post_meta($post->ID, '_bam_banner_link', true);
        $banner_alt = get_post_meta($post->ID, '_bam_banner_alt', true);
        $banner_new_tab = get_post_meta($post->ID, '_bam_banner_new_tab', true) ?: '1';
        $banner_nofollow = get_post_meta($post->ID, '_bam_banner_nofollow', true) ?: '0';
        
        // Bild-URL aus ID holen falls vorhanden
        if ($banner_image_id && !$banner_image_url) {
            $banner_image_url = wp_get_attachment_url($banner_image_id);
        }
        ?>
        <div class="bam-metabox-content">
            <p>
                <label><strong><?php _e('Inhaltstyp:', 'blocksy-ad-manager'); ?></strong></label>
            </p>
            <p class="bam-content-type-selector">
                <label class="bam-radio-card <?php echo $content_type === 'html' ? 'active' : ''; ?>">
                    <input type="radio" name="bam_content_type" value="html" <?php checked($content_type, 'html'); ?>>
                    <span class="bam-radio-icon">📝</span>
                    <span class="bam-radio-label"><?php _e('HTML / Shortcodes', 'blocksy-ad-manager'); ?></span>
                </label>
                <label class="bam-radio-card <?php echo $content_type === 'php' ? 'active' : ''; ?>">
                    <input type="radio" name="bam_content_type" value="php" <?php checked($content_type, 'php'); ?>>
                    <span class="bam-radio-icon">⚙️</span>
                    <span class="bam-radio-label"><?php _e('PHP Code', 'blocksy-ad-manager'); ?></span>
                </label>
                <label class="bam-radio-card <?php echo $content_type === 'image' ? 'active' : ''; ?>">
                    <input type="radio" name="bam_content_type" value="image" <?php checked($content_type, 'image'); ?>>
                    <span class="bam-radio-icon">🖼️</span>
                    <span class="bam-radio-label"><?php _e('Banner / Bild', 'blocksy-ad-manager'); ?></span>
                </label>
            </p>
            
            <!-- HTML/PHP Content -->
            <div id="bam_content_code" class="bam-content-section" style="<?php echo $content_type === 'image' ? 'display:none;' : ''; ?>">
                <p class="description">
                    <?php _e('Bei PHP-Code: Ohne öffnende/schließende PHP-Tags eingeben.', 'blocksy-ad-manager'); ?>
                </p>
                <p>
                    <textarea name="bam_ad_content" id="bam_ad_content" rows="10" style="width:100%;"><?php echo esc_textarea($content); ?></textarea>
                </p>
            </div>
            
            <!-- Banner/Image Content -->
            <div id="bam_content_image" class="bam-content-section bam-banner-settings" style="<?php echo $content_type !== 'image' ? 'display:none;' : ''; ?>">
                
                <div class="bam-banner-upload-area">
                    <div class="bam-banner-preview" id="bam_banner_preview">
                        <?php if ($banner_image_url): ?>
                            <img src="<?php echo esc_url($banner_image_url); ?>" alt="<?php echo esc_attr($banner_alt); ?>">
                            <button type="button" class="bam-banner-remove" id="bam_banner_remove" title="<?php esc_attr_e('Bild entfernen', 'blocksy-ad-manager'); ?>">✕</button>
                        <?php else: ?>
                            <div class="bam-banner-placeholder">
                                <span class="bam-placeholder-icon">📷</span>
                                <span class="bam-placeholder-text"><?php _e('Kein Bild ausgewählt', 'blocksy-ad-manager'); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="bam-banner-actions">
                        <button type="button" class="button button-primary" id="bam_banner_upload">
                            <?php _e('Bild auswählen / hochladen', 'blocksy-ad-manager'); ?>
                        </button>
                    </div>
                    
                    <input type="hidden" name="bam_banner_image_id" id="bam_banner_image_id" value="<?php echo esc_attr($banner_image_id); ?>">
                    <input type="hidden" name="bam_banner_image_url" id="bam_banner_image_url" value="<?php echo esc_url($banner_image_url); ?>">
                </div>
                
                <div class="bam-banner-options">
                    <p>
                        <label><strong><?php _e('Link-URL:', 'blocksy-ad-manager'); ?></strong></label>
                        <input type="url" name="bam_banner_link" id="bam_banner_link" value="<?php echo esc_url($banner_link); ?>" style="width:100%;" placeholder="https://example.com">
                        <span class="description"><?php _e('Wohin soll das Banner verlinken?', 'blocksy-ad-manager'); ?></span>
                    </p>
                    
                    <p>
                        <label><strong><?php _e('Alt-Text:', 'blocksy-ad-manager'); ?></strong></label>
                        <input type="text" name="bam_banner_alt" id="bam_banner_alt" value="<?php echo esc_attr($banner_alt); ?>" style="width:100%;" placeholder="<?php esc_attr_e('Beschreibung des Bildes', 'blocksy-ad-manager'); ?>">
                        <span class="description"><?php _e('Wichtig für SEO und Barrierefreiheit.', 'blocksy-ad-manager'); ?></span>
                    </p>
                    
                    <div class="bam-banner-checkboxes">
                        <label>
                            <input type="checkbox" name="bam_banner_new_tab" value="1" <?php checked($banner_new_tab, '1'); ?>>
                            <?php _e('In neuem Tab öffnen', 'blocksy-ad-manager'); ?>
                        </label>
                        
                        <label>
                            <input type="checkbox" name="bam_banner_nofollow" value="1" <?php checked($banner_nofollow, '1'); ?>>
                            <?php _e('Nofollow-Link (rel="nofollow")', 'blocksy-ad-manager'); ?>
                        </label>
                    </div>
                </div>
            </div>
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
        
        // Modal Settings
        $modal_delay = get_post_meta($post->ID, '_bam_modal_delay', true) ?: '3';
        $modal_width = get_post_meta($post->ID, '_bam_modal_width', true) ?: '600';
        $modal_width_unit = get_post_meta($post->ID, '_bam_modal_width_unit', true) ?: 'px';
        $modal_allow_dismiss = get_post_meta($post->ID, '_bam_modal_allow_dismiss', true) ?: '1';
        $modal_dismiss_duration = get_post_meta($post->ID, '_bam_modal_dismiss_duration', true) ?: '24';
        $modal_close_outside = get_post_meta($post->ID, '_bam_modal_close_outside', true) ?: '1';
        $modal_show_overlay = get_post_meta($post->ID, '_bam_modal_show_overlay', true) ?: '1';
        
        // Borlabs Cookie Integration Settings
        $wait_for_borlabs = get_post_meta($post->ID, '_bam_wait_for_borlabs', true) ?: '1';
        $borlabs_extra_delay = get_post_meta($post->ID, '_bam_borlabs_extra_delay', true) ?: '0';
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
                    <option value="modal" <?php selected($position, 'modal'); ?>>
                        🪟 <?php _e('Modal / Popup', 'blocksy-ad-manager'); ?>
                    </option>
                </optgroup>
            </select>
            
            <!-- Paragraph/Heading Settings -->
            <div id="bam_paragraph_settings" class="bam-position-settings" style="margin-top:15px;">
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
                    
                    <!-- Borlabs Cookie Integration für Anchor -->
                    <hr style="margin: 15px 0; border: 0; border-top: 1px solid #e0e0e0;">
                    
                    <h4><?php _e('🍪 Borlabs Cookie Integration', 'blocksy-ad-manager'); ?></h4>
                    
                    <p>
                        <label>
                            <input type="checkbox" name="bam_wait_for_borlabs" value="1" <?php checked($wait_for_borlabs, '1'); ?>>
                            <strong><?php _e('Auf Borlabs Cookie warten', 'blocksy-ad-manager'); ?></strong>
                        </label>
                        <br>
                        <span class="description"><?php _e('Anzeige erscheint erst, nachdem das Borlabs Cookie Banner geschlossen wurde.', 'blocksy-ad-manager'); ?></span>
                    </p>
                    
                    <p>
                        <label><strong><?php _e('Zusätzliche Verzögerung nach Cookie-Banner:', 'blocksy-ad-manager'); ?></strong></label>
                        <br>
                        <input type="number" name="bam_borlabs_extra_delay" value="<?php echo esc_attr($borlabs_extra_delay); ?>" min="0" max="60" style="width:80px;">
                        <?php _e('Sekunden', 'blocksy-ad-manager'); ?>
                        <br>
                        <span class="description"><?php _e('Extra Wartezeit nach dem Schließen des Cookie-Banners.', 'blocksy-ad-manager'); ?></span>
                    </p>
                </div>
            </div>
            
            <!-- Modal Ad Settings -->
            <div id="bam_modal_settings" class="bam-position-settings" style="margin-top:15px; display:none;">
                <div class="bam-settings-card">
                    <h4><?php _e('🪟 Modal Einstellungen', 'blocksy-ad-manager'); ?></h4>
                    
                    <p>
                        <label><strong><?php _e('Verzögerung:', 'blocksy-ad-manager'); ?></strong></label>
                        <br>
                        <input type="number" name="bam_modal_delay" value="<?php echo esc_attr($modal_delay); ?>" min="0" max="120" style="width:80px;">
                        <?php _e('Sekunden', 'blocksy-ad-manager'); ?>
                        <br>
                        <span class="description"><?php _e('Nach wie vielen Sekunden soll das Modal erscheinen? (Nach Cookie-Banner falls aktiviert)', 'blocksy-ad-manager'); ?></span>
                    </p>
                    
                    <p>
                        <label><strong><?php _e('Modal-Breite:', 'blocksy-ad-manager'); ?></strong></label>
                        <br>
                        <input type="number" name="bam_modal_width" value="<?php echo esc_attr($modal_width); ?>" min="200" max="1200" style="width:80px;">
                        <select name="bam_modal_width_unit" style="width:70px;">
                            <option value="px" <?php selected($modal_width_unit, 'px'); ?>>px</option>
                            <option value="vw" <?php selected($modal_width_unit, 'vw'); ?>>vw (%)</option>
                        </select>
                    </p>
                    
                    <hr style="margin: 15px 0; border: 0; border-top: 1px solid #e0e0e0;">
                    
                    <p>
                        <label>
                            <input type="checkbox" name="bam_modal_show_overlay" value="1" <?php checked($modal_show_overlay, '1'); ?>>
                            <strong><?php _e('Hintergrund abdunkeln', 'blocksy-ad-manager'); ?></strong>
                        </label>
                    </p>
                    
                    <p>
                        <label>
                            <input type="checkbox" name="bam_modal_close_outside" value="1" <?php checked($modal_close_outside, '1'); ?>>
                            <strong><?php _e('Click außerhalb schließt Modal', 'blocksy-ad-manager'); ?></strong>
                        </label>
                    </p>
                    
                    <hr style="margin: 15px 0; border: 0; border-top: 1px solid #e0e0e0;">
                    
                    <p>
                        <label>
                            <input type="checkbox" name="bam_modal_allow_dismiss" value="1" <?php checked($modal_allow_dismiss, '1'); ?> id="bam_modal_allow_dismiss_cb">
                            <strong><?php _e('"Nicht mehr anzeigen" Option', 'blocksy-ad-manager'); ?></strong>
                        </label>
                        <br>
                        <span class="description"><?php _e('Zeigt eine Checkbox zum dauerhaften Ausblenden.', 'blocksy-ad-manager'); ?></span>
                    </p>
                    
                    <div id="bam_modal_dismiss_settings" style="margin-top:10px; <?php echo $modal_allow_dismiss !== '1' ? 'display:none;' : ''; ?>">
                        <label>
                            <strong><?php _e('Ausblenden für:', 'blocksy-ad-manager'); ?></strong>
                        </label>
                        <br>
                        <input type="number" name="bam_modal_dismiss_duration" value="<?php echo esc_attr($modal_dismiss_duration); ?>" min="1" max="720" style="width:80px;">
                        <?php _e('Stunden', 'blocksy-ad-manager'); ?>
                        <br>
                        <span class="description"><?php _e('Nach dieser Zeit wird das Modal wieder angezeigt.', 'blocksy-ad-manager'); ?></span>
                    </div>
                    
                    <!-- Borlabs Cookie Integration für Modal -->
                    <hr style="margin: 15px 0; border: 0; border-top: 1px solid #e0e0e0;">
                    
                    <h4><?php _e('🍪 Borlabs Cookie Integration', 'blocksy-ad-manager'); ?></h4>
                    
                    <p>
                        <label>
                            <input type="checkbox" name="bam_wait_for_borlabs" value="1" <?php checked($wait_for_borlabs, '1'); ?>>
                            <strong><?php _e('Auf Borlabs Cookie warten', 'blocksy-ad-manager'); ?></strong>
                        </label>
                        <br>
                        <span class="description"><?php _e('Modal erscheint erst, nachdem das Borlabs Cookie Banner geschlossen wurde.', 'blocksy-ad-manager'); ?></span>
                    </p>
                    
                    <p>
                        <label><strong><?php _e('Zusätzliche Verzögerung nach Cookie-Banner:', 'blocksy-ad-manager'); ?></strong></label>
                        <br>
                        <input type="number" name="bam_borlabs_extra_delay" value="<?php echo esc_attr($borlabs_extra_delay); ?>" min="0" max="60" style="width:80px;">
                        <?php _e('Sekunden', 'blocksy-ad-manager'); ?>
                        <br>
                        <span class="description"><?php _e('Extra Wartezeit nach dem Schließen des Cookie-Banners (zusätzlich zur normalen Verzögerung).', 'blocksy-ad-manager'); ?></span>
                    </p>
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
            $content_type = sanitize_text_field($_POST['bam_content_type']);
            $content_type = in_array($content_type, ['html', 'php', 'image']) ? $content_type : 'html';
            update_post_meta($post_id, '_bam_content_type', $content_type);
        }
        
        // Banner/Image Settings
        if (isset($_POST['bam_banner_image_id'])) {
            update_post_meta($post_id, '_bam_banner_image_id', absint($_POST['bam_banner_image_id']));
        }
        
        if (isset($_POST['bam_banner_image_url'])) {
            update_post_meta($post_id, '_bam_banner_image_url', esc_url_raw($_POST['bam_banner_image_url']));
        }
        
        if (isset($_POST['bam_banner_link'])) {
            update_post_meta($post_id, '_bam_banner_link', esc_url_raw($_POST['bam_banner_link']));
        }
        
        if (isset($_POST['bam_banner_alt'])) {
            update_post_meta($post_id, '_bam_banner_alt', sanitize_text_field($_POST['bam_banner_alt']));
        }
        
        $banner_new_tab = isset($_POST['bam_banner_new_tab']) ? '1' : '0';
        update_post_meta($post_id, '_bam_banner_new_tab', $banner_new_tab);
        
        $banner_nofollow = isset($_POST['bam_banner_nofollow']) ? '1' : '0';
        update_post_meta($post_id, '_bam_banner_nofollow', $banner_nofollow);
        
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
        
        // Modal Settings
        if (isset($_POST['bam_modal_delay'])) {
            update_post_meta($post_id, '_bam_modal_delay', absint($_POST['bam_modal_delay']));
        }
        
        if (isset($_POST['bam_modal_width'])) {
            update_post_meta($post_id, '_bam_modal_width', absint($_POST['bam_modal_width']));
        }
        
        if (isset($_POST['bam_modal_width_unit'])) {
            $unit = sanitize_text_field($_POST['bam_modal_width_unit']);
            $unit = in_array($unit, ['px', 'vw']) ? $unit : 'px';
            update_post_meta($post_id, '_bam_modal_width_unit', $unit);
        }
        
        $modal_allow_dismiss = isset($_POST['bam_modal_allow_dismiss']) ? '1' : '0';
        update_post_meta($post_id, '_bam_modal_allow_dismiss', $modal_allow_dismiss);
        
        if (isset($_POST['bam_modal_dismiss_duration'])) {
            update_post_meta($post_id, '_bam_modal_dismiss_duration', absint($_POST['bam_modal_dismiss_duration']));
        }
        
        $modal_close_outside = isset($_POST['bam_modal_close_outside']) ? '1' : '0';
        update_post_meta($post_id, '_bam_modal_close_outside', $modal_close_outside);
        
        $modal_show_overlay = isset($_POST['bam_modal_show_overlay']) ? '1' : '0';
        update_post_meta($post_id, '_bam_modal_show_overlay', $modal_show_overlay);
        
        // Borlabs Cookie Integration Settings
        $wait_for_borlabs = isset($_POST['bam_wait_for_borlabs']) ? '1' : '0';
        update_post_meta($post_id, '_bam_wait_for_borlabs', $wait_for_borlabs);
        
        if (isset($_POST['bam_borlabs_extra_delay'])) {
            update_post_meta($post_id, '_bam_borlabs_extra_delay', absint($_POST['bam_borlabs_extra_delay']));
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
