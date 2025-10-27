/**
 * Masonry Layout Initialization for GlobalLandingPage
 * Initializes Masonry grids with imagesLoaded support
 */

(function() {
    'use strict';
    
    /**
     * Initialize Masonry on a grid element
     * @param {string} gridSelector - CSS selector for the grid container
     * @param {Object} options - Masonry options
     */
    function initMasonryGrid(gridSelector, options) {
        var grid = document.querySelector(gridSelector);
        
        if (!grid || typeof Masonry === 'undefined') {
            console.warn('Masonry grid not initialized: grid or Masonry library not found', gridSelector);
            return null;
        }
        
        var defaultOptions = {
            itemSelector: '.resource',
            columnWidth: '.resource',
            percentPosition: true,
            gutter: 20,
            horizontalOrder: true
        };
        
        var masonryOptions = Object.assign({}, defaultOptions, options || {});
        var msnry = new Masonry(grid, masonryOptions);
        
        // Relayout after all images load
        if (typeof imagesLoaded !== 'undefined') {
            imagesLoaded(grid, function() {
                msnry.layout();
            });
        }
        
        return msnry;
    }
    
    /**
     * Initialize all Masonry grids on page load
     */
    function initAllMasonryGrids() {
        // Initialize Recent Items grid
        var recentItemsGrid = initMasonryGrid('#recent-items-grid');
        
        // Initialize Featured Sites grid
        var featuredSitesGrid = initMasonryGrid('#featured-sites-grid');
        var featuredSitesGrid = initMasonryGrid('#sites-grid');
        // Log initialization status
        if (recentItemsGrid) {
            console.log('Recent Items Masonry grid initialized');
        }
        
        if (featuredSitesGrid) {
            console.log('Featured Sites Masonry grid initialized');
        }
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAllMasonryGrids);
    } else {
        // DOM is already loaded
        initAllMasonryGrids();
    }
    
    // Re-layout on window resize (debounced)
    var resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            var grids = document.querySelectorAll('.resource-grid');
            grids.forEach(function(grid) {
                if (grid.masonry) {
                    grid.masonry.layout();
                }
            });
        }, 250);
    });
})();
