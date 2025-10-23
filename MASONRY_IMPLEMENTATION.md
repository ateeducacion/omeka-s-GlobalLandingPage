# Masonry Grid Implementation - GlobalLandingPage Module

## Overview

This document describes the Masonry-style grid layout implementation for the GlobalLandingPage module. The new homepage displays the 10 most recent items and featured sites in responsive, visually appealing Masonry grids.

## Features

### 1. Recent Items Grid
- Displays the **10 most recent items** from the Omeka S installation
- Items are sorted by creation date (newest first)
- Each item card includes:
  - Thumbnail image (if available)
  - Item title
  - Resource class label (type)
  - Direct link to item detail page

### 2. Featured Sites Grid
- Displays sites with names beginning with **"Área"**
- Sites are sorted alphabetically by title
- Each site card includes:
  - Thumbnail image (if available)
  - Site title
  - Summary (truncated to 100 characters)
  - Direct link to site homepage

### 3. Masonry Layout
- Uses [Masonry.js v4](https://masonry.desandro.com/) for dynamic grid layout
- Responsive breakpoints:
  - Mobile (< 640px): 1 column (100% width)
  - Tablet (640px+): 2 columns
  - Desktop (960px+): 3 columns
  - Large Desktop (1280px+): 4 columns
- Gutter spacing: 20px between items
- Maintains consistent card heights and prevents layout shifts

### 4. Image Loading Optimization
- Uses [imagesLoaded](https://imagesloaded.desandro.com/) to prevent layout shifts
- Lazy loading for images (`loading="lazy"` attribute)
- Placeholder graphics for items/sites without thumbnails

## File Structure

### Modified Files

1. **src/Controller/LandingController.php**
   - Added logic to fetch 10 most recent items
   - Added logic to filter and fetch featured sites (names beginning with "Área")
   - Passes data to view template

2. **view/omeka/index/index.phtml**
   - Completely redesigned homepage layout
   - Added two sections: Recent Items and Featured Sites
   - Loads Masonry.js and imagesLoaded from CDN
   - Implements semantic HTML with proper ARIA labels

3. **asset/sass/components/resources/_resource-grid.scss**
   - Enhanced styles for resource cards
   - Responsive breakpoints for Masonry grid
   - Hover effects and transitions
   - Placeholder styles for items without thumbnails
   - Typography and spacing improvements

### New Files

1. **asset/js/masonry-init.js**
   - Dedicated JavaScript module for Masonry initialization
   - Handles initialization of both grids
   - Implements debounced window resize handling
   - Integrates imagesLoaded for layout recalculation

## Technical Details

### Dependencies

**External Libraries (CDN):**
- Masonry.js v4 (https://unpkg.com/masonry-layout@4/dist/masonry.pkgd.min.js)
- imagesLoaded v5 (https://unpkg.com/imagesloaded@5/imagesloaded.pkgd.min.js)

**Module Assets:**
- `/asset/js/masonry-init.js` - Custom initialization script
- `/asset/css/style.css` - Compiled CSS with grid styles

### Masonry Configuration

```javascript
{
    itemSelector: '.resource',      // Grid items
    columnWidth: '.resource',       // Column width based on item width
    percentPosition: true,          // Use percentage positioning
    gutter: 20,                     // 20px spacing between items
    horizontalOrder: true           // Maintain order across columns
}
```

### CSS Architecture

The grid uses BEM (Block Element Modifier) naming convention:

```scss
.resource-grid              // Container
  .resource                 // Item block
    .resource__thumbnail    // Thumbnail element
    .resource__content      // Content wrapper
    .resource__title        // Title element
    .resource__meta         // Metadata element
    .resource__type         // Type badge element
```

## Usage

### Enabling the Module

1. Install and enable the GlobalLandingPage module in Omeka S
2. In module configuration, enable "Use custom landing page"
3. The new homepage will be available at the root URL (`/`)

### Customization

#### Changing the Number of Recent Items

Edit `src/Controller/LandingController.php`:

```php
$response = $this->api()->search('items', [
    'sort_by' => 'created',
    'sort_order' => 'desc',
    'limit' => 10,  // Change this number
]);
```

#### Modifying Featured Sites Filter

Edit `src/Controller/LandingController.php`:

```php
// Current filter: sites beginning with "Área"
if (stripos($title, 'Área') === 0) {
    $featuredSites[] = $site;
}

// Example: Change to sites containing "Special"
if (stripos($title, 'Special') !== false) {
    $featuredSites[] = $site;
}
```

#### Adjusting Grid Breakpoints

Edit `asset/sass/components/resources/_resource-grid.scss`:

```scss
.resource {
    width: 100%;  // Mobile default
    
    @media (min-width: 640px) {
        width: calc(50% - 10px);  // Tablet: 2 columns
    }
    
    @media (min-width: 960px) {
        width: calc(33.333% - 14px);  // Desktop: 3 columns
    }
    
    @media (min-width: 1280px) {
        width: calc(25% - 15px);  // Large: 4 columns
    }
}
```

After modifying SCSS, recompile:

```bash
sass asset/sass/style.scss asset/css/style.css
```

#### Changing Gutter Spacing

Edit `asset/js/masonry-init.js`:

```javascript
var defaultOptions = {
    itemSelector: '.resource',
    columnWidth: '.resource',
    percentPosition: true,
    gutter: 20,  // Change this value (in pixels)
    horizontalOrder: true
};
```

## Browser Compatibility

- **Modern Browsers**: Full support (Chrome, Firefox, Safari, Edge)
- **IE11**: Limited support (fallback to CSS Grid where available)
- **Mobile**: Fully responsive on iOS and Android devices

## Performance Considerations

1. **Image Optimization**: Ensure thumbnails are properly sized in Omeka S
2. **CDN Loading**: Masonry and imagesLoaded load from CDN (cached)
3. **Lazy Loading**: Images use native lazy loading
4. **Debounced Resize**: Window resize events are debounced (250ms)

## Accessibility

- Semantic HTML5 elements (`<section>`, `<article>`)
- ARIA labels for screen readers
- Keyboard navigation support
- Sufficient color contrast ratios
- Alt text for all images

## Testing Checklist

- [x] Page loads 10 recent items dynamically
- [x] Featured sites appear below recent items
- [x] Both sections use Masonry layout
- [x] Responsive at all breakpoints
- [x] No layout shifts after images load
- [x] Follows PSR-12 coding standards
- [x] Compatible with Omeka S module conventions

## Troubleshooting

### Masonry Not Initializing

**Symptom**: Grid items overlap or don't arrange properly

**Solutions**:
1. Check browser console for JavaScript errors
2. Verify CDN libraries are loading (check Network tab)
3. Ensure grid container has ID: `recent-items-grid` or `featured-sites-grid`
4. Clear browser cache and reload

### Layout Shifts After Images Load

**Symptom**: Grid rearranges after page load

**Solution**: This is expected behavior. The imagesLoaded library handles this by recalculating layout. If issues persist, ensure:
- Images have proper dimensions
- Thumbnail sizes are consistent
- Network connection is stable

### No Items or Sites Displayed

**Symptom**: Empty grids with "No items/sites available" message

**Solutions**:
1. Verify items exist in Omeka S
2. Check item visibility (must be public)
3. For featured sites, ensure site names begin with "Área"
4. Check PHP error logs for API errors

## Future Enhancements

Potential improvements for future versions:

1. **Filtering**: Add filters for resource types, date ranges
2. **Pagination**: Load more items on scroll or click
3. **Search Integration**: Search within recent items
4. **Animations**: Add entrance animations for grid items
5. **Local Assets**: Option to use local Masonry files instead of CDN
6. **Configuration UI**: Admin settings for grid columns, items count, etc.

## Support

For issues or questions:
- Check module documentation
- Review Omeka S documentation
- Report bugs via GitHub issues
- Contact module maintainers

## Credits

- **Masonry.js**: David DeSandro (https://masonry.desandro.com/)
- **imagesLoaded**: David DeSandro (https://imagesloaded.desandro.com/)
- **Omeka S**: Roy Rosenzweig Center for History and New Media

## License

This implementation follows the same license as the GlobalLandingPage module.
