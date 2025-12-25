/**
 * Anchor Ad JavaScript
 * 
 * @package Blocksy_Ad_Manager
 * @since 1.3.0
 */

(function() {
    'use strict';
    
    // Debug-Modus (auf false setzen für Produktion)
    var DEBUG = false;
    
    function log(message, data) {
        if (DEBUG && console && console.log) {
            if (data) {
                console.log('[BAM Anchor] ' + message, data);
            } else {
                console.log('[BAM Anchor] ' + message);
            }
        }
    }
    
    /**
     * Storage Helper
     */
    var BamStorage = {
        set: function(key, value, hours) {
            var expires = new Date();
            expires.setTime(expires.getTime() + (hours * 60 * 60 * 1000));
            
            try {
                localStorage.setItem(key, JSON.stringify({
                    value: value,
                    expires: expires.getTime()
                }));
            } catch (e) {
                document.cookie = key + '=' + value + ';expires=' + expires.toUTCString() + ';path=/;SameSite=Lax';
            }
        },
        
        get: function(key) {
            try {
                var item = localStorage.getItem(key);
                if (item) {
                    var data = JSON.parse(item);
                    if (data.expires > Date.now()) {
                        return data.value;
                    }
                    localStorage.removeItem(key);
                }
            } catch (e) {
                var cookies = document.cookie.split(';');
                for (var i = 0; i < cookies.length; i++) {
                    var cookie = cookies[i].trim();
                    if (cookie.indexOf(key + '=') === 0) {
                        return cookie.substring(key.length + 1);
                    }
                }
            }
            return null;
        },
        
        remove: function(key) {
            try {
                localStorage.removeItem(key);
            } catch (e) {
                document.cookie = key + '=;expires=Thu, 01 Jan 1970 00:00:00 UTC;path=/;';
            }
        }
    };
    
    /**
     * Anchor Ad Controller
     */
    function BamAnchorAd(element) {
        var self = this;
        
        this.element = element;
        this.adId = element.getAttribute('data-ad-id');
        this.allowClose = element.getAttribute('data-allow-close') === '1';
        this.closeDuration = parseInt(element.getAttribute('data-close-duration'), 10) || 24;
        
        this.toggleBtn = element.querySelector('.bam-anchor-toggle');
        this.closeBtn = element.querySelector('.bam-anchor-close');
        
        this.storageKeyMinimized = 'bam_anchor_minimized_' + this.adId;
        this.storageKeyClosed = 'bam_anchor_closed_' + this.adId;
        
        log('Initializing Anchor Ad', { id: this.adId, element: element });
        log('Toggle button found:', this.toggleBtn);
        log('Close button found:', this.closeBtn);
        
        this.init();
    }
    
    BamAnchorAd.prototype.init = function() {
        var self = this;
        
        // Check if ad was closed
        if (this.allowClose && BamStorage.get(this.storageKeyClosed)) {
            log('Ad was previously closed, hiding');
            this.element.style.display = 'none';
            return;
        }
        
        // Check if ad was minimized
        if (BamStorage.get(this.storageKeyMinimized)) {
            log('Ad was previously minimized');
            this.element.classList.add('bam-minimized');
        }
        
        this.bindEvents();
    };
    
    BamAnchorAd.prototype.bindEvents = function() {
        var self = this;
        
        // Toggle button (minimize/expand)
        if (this.toggleBtn) {
            log('Binding click event to toggle button');
            
            // Event mit addEventListener
            this.toggleBtn.addEventListener('click', function(e) {
                log('Toggle button clicked!');
                e.preventDefault();
                e.stopPropagation();
                self.toggle();
            }, false);
            
            // Auch Touch-Event für Mobile
            this.toggleBtn.addEventListener('touchend', function(e) {
                log('Toggle button touched!');
                e.preventDefault();
                e.stopPropagation();
                self.toggle();
            }, false);
        } else {
            log('ERROR: Toggle button not found!');
        }
        
        // Close button
        if (this.closeBtn) {
            log('Binding click event to close button');
            
            this.closeBtn.addEventListener('click', function(e) {
                log('Close button clicked!');
                e.preventDefault();
                e.stopPropagation();
                self.close();
            }, false);
            
            this.closeBtn.addEventListener('touchend', function(e) {
                log('Close button touched!');
                e.preventDefault();
                e.stopPropagation();
                self.close();
            }, false);
        }
        
        // Keyboard accessibility
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && self.element && !self.element.classList.contains('bam-closed')) {
                if (self.allowClose) {
                    self.close();
                } else {
                    self.minimize();
                }
            }
        });
    };
    
    BamAnchorAd.prototype.toggle = function() {
        log('Toggle called, current state:', this.element.classList.contains('bam-minimized') ? 'minimized' : 'expanded');
        
        if (this.element.classList.contains('bam-minimized')) {
            this.expand();
        } else {
            this.minimize();
        }
    };
    
    BamAnchorAd.prototype.minimize = function() {
        log('Minimizing...');
        
        // Fokus entfernen bevor wir minimieren (verhindert aria-hidden Warnung)
        if (document.activeElement && this.element.contains(document.activeElement)) {
            document.activeElement.blur();
        }
        
        this.element.classList.add('bam-minimized');
        BamStorage.set(this.storageKeyMinimized, '1', 24);
        
        // Trigger event
        try {
            var event = new CustomEvent('bam:anchor:minimized', {
                detail: { adId: this.adId }
            });
            this.element.dispatchEvent(event);
        } catch(e) {
            log('CustomEvent error:', e);
        }
        
        log('Minimized successfully');
    };
    
    BamAnchorAd.prototype.expand = function() {
        log('Expanding...');
        
        this.element.classList.remove('bam-minimized');
        BamStorage.remove(this.storageKeyMinimized);
        
        // Trigger event
        try {
            var event = new CustomEvent('bam:anchor:expanded', {
                detail: { adId: this.adId }
            });
            this.element.dispatchEvent(event);
        } catch(e) {
            log('CustomEvent error:', e);
        }
        
        log('Expanded successfully');
    };
    
    BamAnchorAd.prototype.close = function() {
        var self = this;
        
        log('Closing...');
        
        // Fokus entfernen
        if (document.activeElement && this.element.contains(document.activeElement)) {
            document.activeElement.blur();
        }
        
        this.element.classList.add('bam-closed');
        BamStorage.set(this.storageKeyClosed, '1', this.closeDuration);
        
        // Trigger event
        try {
            var event = new CustomEvent('bam:anchor:closed', {
                detail: { adId: this.adId }
            });
            this.element.dispatchEvent(event);
        } catch(e) {
            log('CustomEvent error:', e);
        }
        
        // Remove from DOM after animation
        setTimeout(function() {
            if (self.element && self.element.parentNode) {
                self.element.style.display = 'none';
            }
        }, 400);
        
        log('Closed successfully');
    };
    
    /**
     * Initialize all Anchor Ads
     */
    function initAnchorAds() {
        log('Looking for anchor ads...');
        
        var anchorAds = document.querySelectorAll('.bam-anchor-ad');
        
        log('Found ' + anchorAds.length + ' anchor ad(s)');
        
        if (anchorAds.length === 0) {
            log('No anchor ads found on page');
            return;
        }
        
        for (var i = 0; i < anchorAds.length; i++) {
            try {
                new BamAnchorAd(anchorAds[i]);
            } catch(e) {
                log('Error initializing anchor ad:', e);
            }
        }
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            log('DOMContentLoaded fired');
            initAnchorAds();
        });
    } else {
        log('DOM already ready');
        initAnchorAds();
    }
    
    // Also try on window load as fallback
    window.addEventListener('load', function() {
        log('Window load fired');
        // Re-init if not already initialized
        var anchorAds = document.querySelectorAll('.bam-anchor-ad:not([data-bam-initialized])');
        if (anchorAds.length > 0) {
            log('Found uninitialized anchor ads, initializing...');
            for (var i = 0; i < anchorAds.length; i++) {
                anchorAds[i].setAttribute('data-bam-initialized', 'true');
                try {
                    new BamAnchorAd(anchorAds[i]);
                } catch(e) {
                    log('Error initializing anchor ad on load:', e);
                }
            }
        }
    });
    
    // Expose to global scope
    window.BamAnchorAd = BamAnchorAd;
    window.BamStorage = BamStorage;
    
    // Manual init function for debugging
    window.bamInitAnchors = initAnchorAds;
    
})();
