# Definitions (Deprecated)

This directory is scheduled for removal. It contains the legacy eager-hydration layer that inlines cross-referenced XML nodes during document loading.

## What's left

- **`Element.php`** - Base class used throughout the codebase (DOM node wrapper with `get()`, `attributesToArray()`, `children()`, `appendNode()`, `hydrateFoundryReference()`). Will be relocated before this directory is removed.
- **`EntityClassDefinition/Components/`** - 13 ECD component hydrators, still active during item loading when `referenceHydrationEnabled = true`.
