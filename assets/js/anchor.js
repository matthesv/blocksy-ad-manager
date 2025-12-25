/**
 * Anchor Ad JavaScript
 * 
 * @package Blocksy_Ad_Manager
 * @since 1.2.0
 */

(function() {
    'use strict';
    
    /**
     * Cookie/Storage Helper
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
        this.element = element;
        this.adId = element.getAttribute('data-ad-id');
        this.allowClose = element.getAttribute('data-allow-close') === '1';
        this.closeDuration = parseInt(element.getAttribute('data-close-duration'), 10) || 24;
        
        this.toggleBtn = element.querySelector('.bam-anchor-toggle');
        this.closeBtn = element.querySelector('.bam-anchor-close');
        
        this.storageKeyMinimized = 'bam_anchor_minimized_' + this.adId;
        this.storageKeyClosed = 'bam_anchor_closed_' + this.adId;
        
        this.init();
    }
    
    BamAnchorAd.prototype.init = function() {
        var self = this;
        
        // Check if ad was closed
        if (this.allowClose && BamStorage.get(this.storageKeyClosed)) {
            this.element.classList.add('bam-closed');
            return;
        }
        
        // Check if ad was minimized
        if (BamStorage.get(this.storageKeyMinimized)) {
            this.element.classList.add('bam-minimized');
        }
        
        this.bindEvents();
    };
    
    BamAnchorAd.prototype.bindEvents = function() {
        var self = this;
        
        // Toggle button (minimize/expand)
        if (this.toggleBtn) {
            this.toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                self.toggle();
            });
        }
        
        // Close button
        if (this.closeBtn) {
            this.closeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                self.close();
            });
        }
        
        // Keyboard accessibility
        this.element.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (self.allowClose) {
                    self.close();
                } else {
                    self.minimize();
                }
            }
        });
    };
    
    BamAnchorAd.prototype.toggle = function() {
        if (this.element.classList.contains('bam-minimized')) {
            this.expand();
        } else {
            this.minimize();
        }
    };
    
    BamAnchorAd.prototype.minimize = function() {
        this.element.classList.add('bam-minimized');
        BamStorage.set(this.storageKeyMinimized, '1', 24);
        
        // Update ARIA
        if (this.toggleBtn) {
            this.toggleBtn.setAttribute('aria-expanded', 'false');
        }
        
        // Trigger event
        var event = new CustomEvent('bam:anchor:minimized', {
            detail: { adId: this.adId }
        });
        this.element.dispatchEvent(event);
    };
    
    BamAnchorAd.prototype.expand = function() {
        this.element.classList.remove('bam-minimized');
        BamStorage.remove(this.storageKeyMinimized);
        
        // Update ARIA
        if (this.toggleBtn) {
            this.toggleBtn.setAttribute('aria-expanded', 'true');
        }
        
        // Trigger event
        var event = new CustomEvent('bam:anchor:expanded', {
            detail: { adId: this.adId }
        });
        this.element.dispatchEvent(event);
    };
    
    BamAnchorAd.prototype.close = function() {
        var self = this;
        
        this.element.classList.add('bam-closed');
        BamStorage.set(this.storageKeyClosed, '1', this.closeDuration);
        
        // Trigger event
        var event = new CustomEvent('bam:anchor:closed', {
            detail: { adId: this.adId }
        });
        this.element.dispatchEvent(event);
        
        // Remove from DOM after animation
        setTimeout(function() {
            if (self.element && self.element.parentNode) {
                self.element.parentNode.removeChild(self.element);
            }
        }, 400);
    };
    
    /**
     * Initialize all Anchor Ads
     */
    function initAnchorAds() {
        var anchorAds = document.querySelectorAll('.bam-anchor-ad');
        
        for (var i = 0; i < anchorAds.length; i++) {
            new BamAnchorAd(anchorAds[i]);
        }
    }
    
    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAnchorAds);
    } else {
        initAnchorAds();
    }
    
    // Expose to global scope
    window.BamAnchorAd = BamAnchorAd;
    window.BamStorage = BamStorage;
    
})();
