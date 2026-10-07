# PixIndex – Table of Contents for Joomla

🇮🇹 [Leggi in italiano](README.it.md)

**PixIndex** is a free site module for **Joomla 5 and Joomla 6** that builds a table of contents from the headings (`<h1>`–`<h6>`) of the page it is shown on. Publish it in any template position (sidebar, top, bottom...) or inside the article with `{loadmodule}`. It adds anchors to the headings in the content and links every index entry to its section.

Every feature is included. There is no "Pro" edition.

## Features

- **It's a module, not a plugin.** One package, no plugins, assigned to pages like any other module.
- **Bootstrap 5 or UIkit 3 markup:** native look in **Cassiopeia** (and every Bootstrap template) or in **YOOtheme Pro** (and every UIkit template). There is also a framework-free mode.
- **Glass look (glassmorphism)** or the plain framework style: translucent box with blurred background, thin border and soft shadow. Every tint comes from your theme text and link colors, so it fits light and dark themes; the module never sets fonts.
- **Three displays:** card, plain list or vertical navigation.
  - **Collapsible:** open or closed by default, or closed only on mobile.
- **Smartphone button:** below a chosen screen width the index becomes a floating button that opens a side panel (Bootstrap/UIkit offcanvas, or a native dialog).
- **Long indexes stay readable:**
  - collapsible sub-levels: parent entries get an arrow button, branches start closed, open and close with a short animation, and the branch of the current section opens by itself while scrolling;
  - bullet points can be hidden;
  - long entries shortened after N characters, optionally without cutting words (the full title shows on hover);
  - maximum height with scrollbar;
  - sticky index.
- **Headings in the content** get an id (the address used by the links) and, optionally, custom class names. Their text is never changed.
- **Ordered or unordered lists**, with the style set per level: `1 2 3`, `A B C`, `a b c`, `I II III`, `i ii iii`. Sub-sections can use simple (1, 2, 3) or full numbers (2.1, 2.2, 2.3).
- **Clean entries:** emoji, pictographs, icon-font glyphs and invisible characters are removed from the index. A strict mode also removes every other symbol and leading/trailing bullets or colons. The headings in the content are never changed.
- **Precise selection of headings:**
  - **content area:** a CSS selector such as `#articlebody` or `.article-text` limits the headings to that element; by default only the article text is indexed, so title, custom fields, comments and other plugin output are left out;
  - index level range plus single levels to exclude (e.g. every `h1`);
  - exclude headings by class, by attribute or by container (CSS selector);
  - show some headings as titles inside the index;
  - show the module only for some components or content categories (include/exclude, with child categories).
- **Smooth scrolling** with an offset for fixed headers. The **current section is highlighted** while scrolling: by default with a lighter background on dark themes (darker on light ones) and a side bar in the link color; underline, bold, side line or the framework style are also available.
- **"Back to contents" links** before every section.
- **Ids:**
  - optionally include numbers, parent headings and a prefix;
  - ids starting with a number get a prefix;
  - words can be removed;
  - existing ids are kept;
  - duplicates get a counter.
- **Optional content tags** to limit or tweak the index on a single page: `{pixindex}`, `{/pixindex}`, `{pixindex-skip}`...`{/pixindex-skip}`. The tag characters can be changed.
- **Multilingual:** English and Italian included. The index title and the back link text accept language constants.
- **Accessible:**
  - `<nav>` landmark with `aria-label`;
  - `aria-current` on the active entry;
  - keyboard focus moved to the section;
  - honours `prefers-reduced-motion`;
  - right-to-left layouts supported.
- **Fast:**
  - one pass on the component output, no database query unless the category filter is on;
  - deferred vanilla JavaScript (~8 KB minified, no jQuery), where only the enabled features run;
  - active section tracked with `IntersectionObserver`.

## Requirements

- Joomla **5.x** or **6.x**
- PHP **8.1** or later (Joomla 6 requires PHP 8.3+)

## Installation

1. Download `mod_pixindex-x.y.z.zip` from the [Releases](https://github.com/PixedBo/PixIndex/releases) page (or [build it](#building-the-package)).
2. In the Joomla administrator go to **System → Install → Extensions** and upload the package.
3. Go to **Content → Site Modules → New → PixIndex - Table of Contents**.
4. Choose a position (for example `sidebar-right`), assign the module to the menu items with articles and save.

### Inside the article with `{loadmodule}`

You can also print the index inside the article text with the core *Content - Load Modules* plugin:

1. Create the module without a position (or with a custom position) and assign it to the pages where it should work.
2. Write `{loadmoduleid 123}` in the article (123 = module id), or `{loadmodule mod_pixindex}` for the first published PixIndex module. You can also use `{loadposition yourposition}`.

The index appears exactly where the tag is.

## Quick setup

### Cassiopeia (or any Bootstrap 5 template)

- **Style Framework:** Bootstrap 5
- **Display:** Card (boxed) or Navigation (sidebar with pills)
- **For a sidebar:** enable **Always Visible**, **Highlight the Section Being Read** and **Collapsible Sub-sections**; enable **Smartphone Button** for phones.

### YOOtheme Pro (or any UIkit 3 template)

- **Style Framework:** UIkit 3
- **Display:** Navigation (`uk-nav uk-nav-default`) or Card (`uk-card uk-card-default`)
- **Fixed header:** if your header is sticky, set **Space Above the Section** to its height (e.g. `80`).
- **Content area:** YOOtheme article layouts do not use the Joomla class `com-content-article__body`. Write the selector of your own wrapper in **Look for Headings Only In** (Filters tab), for example `#articlebody` or `.article-text`, or leave it as it is: when the element is missing, the whole article output is indexed.

The YOOtheme article title (`uk-article-title`) and the Joomla article title (inside `.page-header`) are always left out by default.

## How it works

1. When the module is rendered, the component output has already been generated. PixIndex reads it and finds the headings, then builds the index.
2. The module adds a listener on Joomla's `onAfterRender` event, so no separate plugin is needed. The listener writes the ids into the headings of the final page and removes the content tags.
3. **With `{loadmodule}`** the module runs *while* the article is being built, so the article does not exist yet.
   - The module prints an invisible placeholder and registers a listener on `onAfterDispatch`, which fires as soon as the component output is complete.
   - The listener then builds the index from the full article, puts it in place of the placeholder and converts the headings.
4. Some templates or page builders change the component output after it is rendered. When that happens, a small script writes the anchors in the browser instead, matching the headings by level and text.

## Content syntax (optional)

The module works without any tag. Tags give finer control on single pages and are always removed from the output. Tags inside code samples (`<pre>`, `<code>`) or inside HTML attributes are never touched.

| Syntax | Effect |
| --- | --- |
| `{pixindex}` | Only headings **after** the tag are indexed. |
| `{/pixindex}` | Ends the indexed section. |
| `{pixindex-skip}` … `{/pixindex-skip}` | Headings inside the block are ignored. |
| `<h2 class="noindex">` or `<h2 class="pixindex-skip">` | This heading is ignored (class names are configurable). |

The opening tag can override some settings for that page:

```text
{pixindex min="2" max="3"}
{pixindex exclude="1"}
{pixindex ordered="false"}
{pixindex ordered="true,false,true"}
{pixindex ordered-style="numbers,letters,roman"}
{pixindex ordered-2="false" ordered-style-3="i"}
{pixindex sanitize="strict" title="In this article"}
```

| Attribute | Values |
| --- | --- |
| `min`, `max` | Index levels, `1`–`6` (or `h1`–`h6`) |
| `exclude` | Levels to ignore, e.g. `1` or `h1,h4` (replaces the module setting) |
| `ordered` | `true` / `false`, or a comma separated list per level |
| `ordered-N` | `true` / `false` for level N |
| `ordered-style` | `1`/`numbers`, `A`/`letters`, `a`/`letters_lowercase`, `I`/`roman`, `i`/`roman_lowercase`, or a list per level |
| `ordered-style-N` | Style for level N |
| `sanitize` | `none`, `emoji`, `strict` |
| `title` | Index title (language constants allowed) |

Tag word, skip word and tag characters (`{…}`, `[…]`, `«…»`, `{{…}}`, `[[…]]`, `[:…:]`, `[%…%]`) can be changed in the **Filters** tab.

## Options

| Tab | Options |
| --- | --- |
| **Module** | Style framework, display, appearance (glass / theme standard), collapsible (no / open / closed / closed only on smartphones), title (show / text / HTML tag), from level / to level, exclude levels, minimum entries |
| **List** | Numbered entries (yes / no / different for each level), numbering type, sub-section numbers (simple / full) and the character after the number, bullet points, maximum entry length (characters) and keep whole words, plain text only, remove emoji and symbols |
| **Behaviour** | Smooth scrolling, space above the section, highlight the section being read and its style (automatic / background and side bar / underline / bold / side line / let the theme decide), collapsible sub-sections, always visible and distance from the top, maximum height, smartphone button and its position, smartphone width, "Back to contents" links and text |
| **Section Links** | Add the number, add the parent sections, prefix, prefix when it starts with a number, words to remove |
| **Filters** | Look for headings only in (CSS selectors), ignore headings with these classes / attributes / inside these elements (CSS selectors), headings as group titles, components and categories filters, words and brackets of the article commands |
| **CSS Classes** | Classes of container, title, body, list, sub-section lists, entries, entry links, group titles, headings in the article; load the module CSS; HTML comments |

## Customization

### Fonts and colors

The module stylesheet never sets a font and never sets a fixed color. The glass surface, borders, row backgrounds and arrow buttons are tints of the theme text color (`currentColor`, or the color the script reads into `--pixindex-text`); the current-section bar uses the link color; the framework-free panel uses the `Canvas` / `CanvasText` system colors. Everything else comes from your template and from the framework classes you choose in the **CSS Classes** tab: leave a field empty for the default, or replace it with your own classes.

Cosmetic rules are wrapped in `:where()`, so they have zero specificity: any rule in your template or custom CSS wins, without `!important`.

### Custom properties

Set them on `:root` (whole site) or on `.pixindex` / `#pixindex-<module id>` (one module):

| Property | Default | Used for |
| --- | --- | --- |
| `--pixindex-indent` | `1.25rem` | Indentation of nested levels |
| `--pixindex-item-gap` | `.2em` | Space between entries |
| `--pixindex-active-weight` | `600` | Font weight of the active entry and of titles |
| `--pixindex-border-color` | `currentColor` at 20% | Borders of the framework-free card and button |
| `--pixindex-radius` | `.5rem` | Radius of the framework-free card |
| `--pixindex-padding` | `1rem 1.25rem` | Padding of the framework-free card and panel |
| `--pixindex-sticky-top` | from the module option | Distance from the top when sticky |
| `--pixindex-max-height` | from the module option | Maximum height of the list |
| `--pixindex-panel-width` | `min(22rem, 85vw)` | Width of the framework-free panel |
| `--pixindex-panel-bg` / `--pixindex-panel-color` | `Canvas` / `CanvasText` | Colors of the framework-free panel and button |
| `--pixindex-fab-offset` | `1rem` | Distance of the mobile button from the screen edges |
| `--pixindex-backdrop` | black at 40% | Page dimming behind the framework-free panel |
| `--pixindex-active-thickness` | `3px` (background and side bar), `.1em` (underline), `.2em` (side line) | Thickness of the current section mark |
| `--pixindex-active-bg` / `--pixindex-active-bar` | text color at 13% / link color | Background and bar of the current row |
| `--pixindex-hover-bg` | text color at 7% | Background of a row on hover |
| `--pixindex-glass-bg` / `--pixindex-glass-border` | text color tints | Glass surface and border |
| `--pixindex-glass-blur` / `--pixindex-glass-shadow` | `16px` / soft shadow | Glass blur and shadow |
| `--pixindex-toggle-bg` / `--pixindex-toggle-border` | text color tints | Arrow buttons of the sub-sections |
| `--pixindex-duration` | `.3s` | Open/close animation and transitions |

### Classes

The index always has the fixed class `pixindex`. It also gets `pixindex--bootstrap5|uikit3|none` and `pixindex--card|list|nav`.

| Class | Element |
| --- | --- |
| `pixindex-title` | Index title |
| `pixindex-body` | Wrapper around the list |
| `pixindex-list`, `pixindex-list--level-N` | Lists |
| `pixindex-item`, `pixindex-item--title`, `pixindex-item--active`, `pixindex-item--parent`, `pixindex-item--open` | List items |
| `pixindex-branch-toggle` | Arrow opening and closing a sub-level |
| `pixindex--active-underline`, `--active-bold`, `--active-line`, `--active-none` | Current section style (on the index) |
| `pixindex-item-title` | Title entries (plain text, not headings) |
| `pixindex-link`, `pixindex-active` | Entry links |
| `pixindex-index-number` | Hierarchical number inside the index |
| `pixindex-back` | "Back to contents" paragraph |
| `pixindex-fab`, `pixindex--panel` | Smartphone button and panel |

Example (put it in your template's custom CSS):

```css
:root {
  --pixindex-active-weight: 700;
  --pixindex-fab-offset: 1.5rem;
}

.pixindex-index-number {
  opacity: .6;
}
```

If you switch **Load the Module CSS** off, you lose the list markers inside UIkit lists and navs, the collapsible arrow, the always visible behaviour, the smartphone button and panel layout, the collapsible sub-sections with their arrows and the highlight style. Copy what you need from `media/mod_pixindex/css/pixindex.css`.

The layouts can be overridden like any Joomla module: copy `modules/mod_pixindex/tmpl/*.php` to `templates/YOUR_TEMPLATE/html/mod_pixindex/`.

## Cache

PixIndex works with every Joomla cache setting. The module itself is never cached because the index depends on the current page.

| Cache | Module in a template position | Module inside the article (YOOtheme Builder, `{loadmodule}`) |
| --- | --- | --- |
| Off | ✅ | ✅ |
| Conservative ("Conservative caching") | ✅ | ✅ |
| Progressive ("Progressive caching") | ⚠️ see below | ✅ |
| *System - Page Cache* plugin | ✅ | ✅ |

**Module inside the article with conservative caching.** This covers the Module element of a YOOtheme Builder template and `{loadmodule}`.
- Joomla saves the article page in the `com_content` cache *before* the index is inserted, and does not run the module again when the page comes from the cache.
- PixIndex therefore updates that cache entry right away, with the complete index, the heading ids and its CSS/JS.
- If the entry cannot be updated, it is removed: the page stays correct and is simply rebuilt on the next visit.

**Progressive caching.** Joomla caches the module positions per menu item, so articles reached from the same menu item (for example a category blog) share the same copy. When the index shown belongs to another article, PixIndex hides it instead of showing the wrong sections. To always get the index of the current article in a template position, use conservative caching or the *System - Page Cache* plugin.

> After installing or updating PixIndex, clear the cache once (**System → Clear Cache**).
## Notes

- If several PixIndex modules are on the same page, the first one that converts the headings wins. Use the same id settings on all of them.
- **Safety limits:** component outputs larger than 5 MB, or content a regular expression cannot process, are left untouched.

## Building the package

```powershell
./build.ps1
```

This creates `dist/mod_pixindex-<version>.zip`, reading the version from `mod_pixindex/mod_pixindex.xml`. It adds minified copies of the CSS and JavaScript (`*.min.css`, `*.min.js`), which Joomla serves automatically outside debug mode. Minification uses esbuild through `npx` (Node.js required); without Node.js the files are copied as they are.

## License

GNU General Public License version 2 or later – see [LICENSE](LICENSE).

© 2026 Gianluca Gabella
