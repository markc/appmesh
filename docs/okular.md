# Okular Integration

KDE's universal document viewer (PDF, EPUB, DjVu, etc.) with D-Bus support.

## D-Bus Status

**Good D-Bus interface.** Okular registers as `org.kde.okular-<PID>` with document navigation and control methods.

```bash
# Find running instance
qdbus6 | grep okular
# org.kde.okular-548172
```

## D-Bus Interface

Service: `org.kde.okular-<PID>`
Path: `/okular`
Interface: `org.kde.okular`

### Document Control

```bash
SERVICE="org.kde.okular-$(pgrep -o okular)"

# Open a document
qdbus6 $SERVICE /okular org.kde.okular.openDocument "file:///path/to/document.pdf"

# Reload current document
qdbus6 $SERVICE /okular org.kde.okular.reload

# Get current document path
qdbus6 $SERVICE /okular org.kde.okular.currentDocument

# Open containing folder
qdbus6 $SERVICE /okular org.kde.okular.slotOpenContainingFolder
```

### Page Navigation

```bash
# Get total pages
qdbus6 $SERVICE /okular org.kde.okular.pages

# Get current page (0-indexed)
qdbus6 $SERVICE /okular org.kde.okular.currentPage

# Go to specific page (0-indexed)
qdbus6 $SERVICE /okular org.kde.okular.goToPage 5

# Next page
qdbus6 $SERVICE /okular org.kde.okular.slotNextPage

# Previous page
qdbus6 $SERVICE /okular org.kde.okular.slotPreviousPage

# First page
qdbus6 $SERVICE /okular org.kde.okular.slotGotoFirst

# Last page
qdbus6 $SERVICE /okular org.kde.okular.slotGotoLast
```

### Search

```bash
# Open find dialog
qdbus6 $SERVICE /okular org.kde.okular.slotFind

# Start with find text (on open)
qdbus6 $SERVICE /okular org.kde.okular.enableStartWithFind "search term"
```

### Presentation & Display

```bash
# Toggle presentation mode
qdbus6 $SERVICE /okular org.kde.okular.slotTogglePresentation

# Toggle color inversion (dark mode)
qdbus6 $SERVICE /okular org.kde.okular.slotToggleChangeColors

# Set color change mode
qdbus6 $SERVICE /okular org.kde.okular.slotSetChangeColors true
```

### Printing

```bash
# Print preview
qdbus6 $SERVICE /okular org.kde.okular.slotPrintPreview

# Enable print on open
qdbus6 $SERVICE /okular org.kde.okular.enableStartWithPrint

# Exit after printing
qdbus6 $SERVICE /okular org.kde.okular.enableExitAfterPrint
```

### Metadata

```bash
# Get document metadata
qdbus6 $SERVICE /okular org.kde.okular.documentMetaData "Title"
qdbus6 $SERVICE /okular org.kde.okular.documentMetaData "Author"
qdbus6 $SERVICE /okular org.kde.okular.documentMetaData "Subject"
```

### Editor Integration

```bash
# Set external editor command (for TeX synctex)
qdbus6 $SERVICE /okular org.kde.okular.setEditorCmd "kate %f -l %l"
```

## Available Methods

| Method | Description |
|--------|-------------|
| `openDocument(path)` | Open a document |
| `reload()` | Reload current document |
| `currentDocument()` | Get current document path |
| `pages()` | Get total page count |
| `currentPage()` | Get current page (0-indexed) |
| `goToPage(n)` | Navigate to page n |
| `slotNextPage()` | Go to next page |
| `slotPreviousPage()` | Go to previous page |
| `slotGotoFirst()` | Go to first page |
| `slotGotoLast()` | Go to last page |
| `slotFind()` | Open find dialog |
| `slotTogglePresentation()` | Toggle presentation mode |
| `slotToggleChangeColors()` | Toggle color inversion |
| `slotPrintPreview()` | Open print preview |
| `documentMetaData(key)` | Get document metadata |

## Command-Line Interface

```bash
# Open a document
okular document.pdf

# Open at specific page
okular --page 10 document.pdf

# Start in presentation mode
okular --presentation document.pdf

# Print and exit
okular --print document.pdf

# Open with find
okular --find "search term" document.pdf

# Unique instance (reuse existing window)
okular --unique document.pdf
```

## DAWN Integration

```php
// Helper to get Okular service name
function okularService(): ?string {
    $pid = trim(shell_exec('pgrep -o okular 2>/dev/null'));
    return $pid ? "org.kde.okular-$pid" : null;
}

// Open a PDF
$service = okularService();
if ($service) {
    qdbus($service, '/okular', 'org.kde.okular.openDocument',
          ['file:///home/user/document.pdf']);
}

// Get page count
$pages = qdbus($service, '/okular', 'org.kde.okular.pages');

// Navigate to page 5
qdbus($service, '/okular', 'org.kde.okular.goToPage', ['5']);

// Start presentation
qdbus($service, '/okular', 'org.kde.okular.slotTogglePresentation');
```

## Supported Formats

Okular supports many document formats:

- PDF, PostScript, DjVu
- EPUB, FictionBook, Mobipocket
- TIFF, CHM, ComicBook (CBR/CBZ)
- XPS, OpenDocument (ODP)
- Markdown, plain text

## Notes

- Page numbers are 0-indexed in D-Bus (page 1 = goToPage(0))
- `--unique` flag is useful for scripting (reuses existing instance)
- Editor integration (`setEditorCmd`) enables LaTeX synctex support
- Presentation mode is great for automation (PDF slideshows)

## Comparison

| Feature | Okular | Gwenview | Haruna |
|---------|--------|----------|--------|
| D-Bus interface | Good | None | MPRIS |
| Document navigation | Yes | N/A | N/A |
| Page queries | Yes | N/A | N/A |
| Presentation mode | Yes | No | N/A |
| Metadata access | Yes | No | Yes |
| Automation-friendly | Good | Poor | Excellent |

Okular provides solid automation for document viewing workflows.
