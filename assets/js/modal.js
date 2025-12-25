/**
 * Modal Ad JavaScript
 * 
 * @package Blocksy_Ad_Manager
 * @since 1.3.0
 */

(function() {
    'use strict';
    
    /**
     * Storage Helper (same as anchor.js)
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
     * Scrollbar Width Calculator
     */
    function getScrollbarWidth() {
        var outer = document.createElement('div');
        outer.style.visibility = 'hidden';
        outer.style.overflow = 'scroll';
        outer.style.msOverflowStyle = 'scrollbar';
        document.body.appendChild(outer);
        
        var inner = document.createElement('div');
        outer.appendChild(inner);
        
        var scrollbarWidth = outer.offsetWidth - inner.offsetWidth;
        outer.parentNode.removeChild(outer);
        
        return scrollbarWidth;
    }
    
    /**
     * Modal Ad Controller
     */
    function BamModalAd(element) {
        this.element = element;
        this.adId = element.getAttribute('data-ad-id');
        this.delay = parseInt(element.getAttribute('data-delay'), 10) || 3;
        this.allowDismiss = element.getAttribute('data-allow-dismiss') === '1';
        this.dismissDuration = parseInt(element.getAttribute('data-dismiss-duration'), 10) || 24;
        this.closeOutside = element.getAttribute('data-close-outside') === '1';
        this.showOverlay = element.getAttribute('data-show-overlay') === '1';
        
        this.modal = element.querySelector('.bam-modal');
        this.overlay = element.querySelector('.bam-modal-overlay');
        this.closeBtn = element.querySelector('.bam-modal-close');
        this.dismissCheckbox = element.querySelector('.bam-modal-dismiss-checkbox');
        
        this.storageKeyDismissed = 'bam_modal_dismissed_' + this.adId;
        this.isOpen = false;
        this.timer = null;
        
        this.init();
    }
    
    BamModalAd.prototype.init = function() {
        var self = this;
        
        // Check if modal was dismissed
        if (BamStorage.get(this.storageKeyDismissed)) {
            this.element.classList.add('bam-modal-hidden');
            return;
        }
        
        // Hide overlay if disabled
        if (!this.showOverlay && this.overlay) {
            this.overlay.style.background = 'transparent';
            this.overlay.style.backdropFilter = 'none';
        }
        
        this.bindEvents();
        this.scheduleOpen();
    };
    
    BamModalAd.prototype.bindEvents = function() {
        var self = this;
        
        // Close button
        if (this.closeBtn) {
            this.closeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                self.close();
            });
        }
        
        // Click outside to close
        if (this.closeOutside && this.overlay) {
            this.overlay.addEventListener('click', function(e) {
                e.preventDefault();
                self.close();
            });
        }
        
        // ESC key to close
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && self.isOpen) {
                self.close();
            }
        });
        
        // Prevent clicks inside modal from closing
        if (this.modal) {
            this.modal.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }
    };
    
    BamModalAd.prototype.scheduleOpen = function() {
        var self = this;
        
        this.timer = setTimeout(function() {
            self.open();
        }, this.delay * 1000);
    };
    
    BamModalAd.prototype.open = function() {
        if (this.isOpen) return;
        
        this.isOpen = true;
        
        // Lock body scroll
        var scrollbarWidth = getScrollbarWidth();
        document.documentElement.style.setProperty('--bam-scrollbar-width', scrollbarWidth + 'px');
        document.body.classList.add('bam-modal-open');
        
        // Show modal
        this.element.classList.add('bam-modal-visible');
        this.element.setAttribute('aria-hidden', 'false');
        
        // Focus close button for accessibility
        if (this.closeBtn) {
            this.closeBtn.focus();
        }
        
        // Trigger event
        var event = new CustomEvent('bam:modal:opened', {
            detail: { adId: this.adId }
        });
        this.element.dispatchEvent(event);
    };
    
    BamModalAd.prototype.close = function() {
        var self = this;
        
        if (!this.isOpen) return;
        
        this.isOpen = false;
        
        // Check if "Don't show again" is checked
        if (this.allowDismiss && this.dismissCheckbox && this.dismissCheckbox.checked) {
            BamStorage.set(this.storageKeyDismissed, '1', this.dismissDuration);
        }
        
        // Hide modal
        this.element.classList.remove('bam-modal-visible');
        this.element.setAttribute('aria-hidden', 'true');
        
        // Unlock body scroll
        document.body.classList.remove('bam-modal-open');
        document.documentElement.style.removeProperty('--bam-scrollbar-width');
        
        // Trigger event
        var event = new CustomEvent('bam:modal:closed', {
            detail: { adId: this.adId }
        });
        this.element.dispatchEvent(event);
        
        // Remove from DOM after animation
        setTimeout(function() {
            if (self.element && self.element.parentNode) {
                self.element.classList.add('bam-modal-hidden');
            }
        }, 350);
    };
    
    BamModalAd.prototype.destroy = function() {
        if (this.timer) {
            clearTimeout(this.timer);
        }
        
        if (this.isOpen) {
            document.body.classList.remove('bam-modal-open');
        }
    };
    
    /**
     * Initialize all Modal Ads
     */
    function initModalAds() {
        var modalAds = document.querySelectorAll('.bam-modal-wrapper');
        
        for (var i = 0; i < modalAds.length; i++) {
            new BamModalAd(modalAds[i]);
        }
    }
    
    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initModalAds);
    } else {
        initModalAds();
    }
    
    // Expose to global scope
    window.BamModalAd = BamModalAd;
    
    // Share storage helper if not already defined
    if (!window.BamStorage) {
        window.BamStorage = BamStorage;
    }
    
})();
