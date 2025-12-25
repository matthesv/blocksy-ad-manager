/**
 * Anchor Ad JavaScript
 * 
 * @package Blocksy_Ad_Manager
 * @since 1.1.0
 */

(function() {
    'use strict';
    
    /**
     * Cookie/Storage Helper
     */
    const BamStorage = {
        set: function(key, value, hours) {
            const expires = new Date();
            expires.setTime(expires.getTime() + (hours * 60 * 60 * 1000));
            
            try {
                localStorage.setItem(key, JSON.stringify({
                    value: value,
                    expires: expires.getTime()
                }));
            } catch (e) {
                // Fallback to cookie
                document.cookie = key + '=' + value + ';expires=' + expires.toUTCString() + ';path=/;SameSite=Lax';
            }
        },
        
        get: function(key) {
            try {
                const item = localStorage.getItem(key);
                if (item) {
                    const data = JSON.parse(item);
                    if (data.expires > Date.now()) {
                        return data.value;
                    }
                    localStorage.removeItem(key);
                }
            } catch (e) {
                // Fallback to cookie
                const cookies = document.cookie.split(';');
                for (let i = 0; i < cookies.length; i++) {
                    const cookie = cookies[i].trim();
                    if (cookie.startsWith(key + '=')) {
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
    class BamAnchorAd {
        constructor(element) {
            this.element = element;
            this.adId = element.dataset.adId;
            this.allowClose = element.dataset.allowClose === '1';
            this.closeDuration = parseInt(element.dataset.closeDuration, 10) || 24;
            
            this.minimizeBtn = element.querySelector('.bam-anchor-minimize');
            this.closeBtn = element.querySelector('.bam-anchor-close');
            this.content = element.querySelector('.bam-anchor-content');
            
            this.storageKeyMinimized = 'bam_anchor_minimized_' + this.adId;
            this.storageKeyClosed = 'bam_anchor_closed_' + this.adId;
            
            this.init();
        }
        
        init() {
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
        }
        
        bindEvents() {
            // Minimize button
            if (this.minimizeBtn) {
                this.minimizeBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.toggleMinimize();
                });
            }
            
            // Close button
            if (this.closeBtn) {
                this.closeBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.close();
                });
            }
            
            // Keyboard accessibility
            this.element.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    if (this.allowClose) {
                        this.close();
                    } else {
                        this.minimize();
                    }
                }
            });
        }
        
        toggleMinimize() {
            if (this.element.classList.contains('bam-minimized')) {
                this.expand();
            } else {
                this.minimize();
            }
        }
        
        minimize() {
            this.element.classList.add('bam-minimized');
            BamStorage.set(this.storageKeyMinimized, '1', 24); // Remember for 24h
            
            // Update ARIA
            this.minimizeBtn.setAttribute('aria-expanded', 'false');
            
            // Trigger event
            this.element.dispatchEvent(new CustomEvent('bam:anchor:minimized', {
                detail: { adId: this.adId }
            }));
        }
        
        expand() {
            this.element.classList.remove('bam-minimized');
            BamStorage.remove(this.storageKeyMinimized);
            
            // Update ARIA
            this.minimizeBtn.setAttribute('aria-expanded', 'true');
            
            // Trigger event
            this.element.dispatchEvent(new CustomEvent('bam:anchor:expanded', {
                detail: { adId: this.adId }
            }));
        }
        
        close() {
            this.element.classList.add('bam-closed');
            BamStorage.set(this.storageKeyClosed, '1', this.closeDuration);
            
            // Trigger event
            this.element.dispatchEvent(new CustomEvent('bam:anchor:closed', {
                detail: { adId: this.adId }
            }));
            
            // Remove from DOM after animation
            setTimeout(() => {
                this.element.remove();
            }, 400);
        }
    }
    
    /**
     * Initialize all Anchor Ads
     */
    function initAnchorAds() {
        const anchorAds = document.querySelectorAll('.bam-anchor-ad');
        
        anchorAds.forEach(function(ad) {
            new BamAnchorAd(ad);
        });
    }
    
    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAnchorAds);
    } else {
        initAnchorAds();
    }
    
    // Expose to global scope for external access
    window.BamAnchorAd = BamAnchorAd;
    window.BamStorage = BamStorage;
    
})();
