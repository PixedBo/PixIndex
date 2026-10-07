/**
 * PixIndex - table of contents module for Joomla
 *
 * Smooth scrolling with offset, active section tracking (highlight and
 * collapsible sub-levels), collapsible-on-mobile, mobile button with panel,
 * sticky positioning, and a client side fallback that writes the heading ids
 * when the server side rewrite could not be applied.
 *
 * Only the features enabled in the module run.
 *
 * @copyright  (C) 2026 Gianluca Gabella
 * @license    GNU General Public License version 2 or later; see LICENSE
 */
(() => {
  'use strict';

  const reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const initialised = new WeakSet();

  // Settings for the "Back to index" links living in the content.
  const contentSettings = { smooth: true };

  const normalize = (text) => (text || '').replace(/[\s ]+/g, ' ').trim();

  const readConfig = (root) => {
    const node = root.querySelector('script.pixindex-config');

    if (!node) {
      return {};
    }

    try {
      return JSON.parse(node.textContent) || {};
    } catch (error) {
      return {};
    }
  };

  const targetOf = (link) => {
    const hash = link.getAttribute('href') || '';

    if (hash.charAt(0) !== '#' || hash.length < 2) {
      return null;
    }

    try {
      return document.getElementById(decodeURIComponent(hash.slice(1)));
    } catch (error) {
      return document.getElementById(hash.slice(1));
    }
  };

  /**
   * Media query matching the "desktop" side of the breakpoint (always true without breakpoint).
   */
  const desktopQuery = (breakpoint) => (breakpoint > 0 && window.matchMedia
    ? window.matchMedia(`(min-width: ${breakpoint}px)`)
    : { matches: true });

  const onQueryChange = (query, callback) => {
    if (query.addEventListener) {
      query.addEventListener('change', callback);
    } else if (query.addListener) {
      query.addListener(callback);
    }
  };

  /**
   * Finds the outermost element wrapping only the index (module chrome included).
   */
  const findHost = (root) => {
    let host = root;

    while (
      host.parentElement
      && host.parentElement !== document.body
      && host.parentElement.offsetHeight - host.offsetHeight < 2
    ) {
      host = host.parentElement;
    }

    return host;
  };

  /**
   * Finds the outermost wrapper holding nothing but the index (module chrome,
   * empty sidebar column...), used to hide the inline index on mobile.
   */
  const findWrapper = (root) => {
    let wrapper = root;

    while (
      wrapper.parentElement
      && wrapper.parentElement !== document.body
      && !wrapper.parentElement.matches('main, article, [role="main"]')
      // Only single-child wrappers: stretched flex/grid columns must not reach the page layout.
      && wrapper.parentElement.children.length === 1
    ) {
      wrapper = wrapper.parentElement;
    }

    return wrapper;
  };


  /**
   * Removes the content tags left in the page when the server side rewrite failed.
   */
  const removeTags = (pattern) => {
    let regex;

    try {
      regex = new RegExp(pattern, 'g');
    } catch (error) {
      return;
    }

    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    const nodes = [];

    while (walker.nextNode()) {
      const node = walker.currentNode;
      const parent = node.parentElement;

      if (parent && !parent.closest('script, style, textarea, pre, code, .pixindex')) {
        regex.lastIndex = 0;

        if (regex.test(node.nodeValue)) {
          nodes.push(node);
        }
      }
    }

    nodes.forEach((node) => {
      const parent = node.parentElement;

      regex.lastIndex = 0;
      node.nodeValue = node.nodeValue.replace(regex, '');

      if (parent && parent.tagName === 'P' && normalize(parent.textContent) === '' && !parent.querySelector('img, iframe, video, svg')) {
        parent.remove();
      }
    });
  };

  /**
   * Writes the missing heading ids by matching level and text.
   *
   * @returns {boolean} False when no entry matches a heading of the page: the index
   *                    belongs to another page (e.g. a position restored by the
   *                    progressive cache for another article).
   */
  const applyFallback = (config) => {
    const items = Array.isArray(config.fallback) ? config.fallback : [];

    if (!items.length) {
      return true;
    }

    let found = items.filter((item) => document.getElementById(item.id)).length;

    if (found === items.length) {
      return true;
    }

    if (config.tagPattern) {
      removeTags(config.tagPattern);
    }

    const headings = Array.from(document.querySelectorAll('h1, h2, h3, h4, h5, h6'))
      .filter((heading) => !heading.closest('.pixindex'));
    let cursor = 0;

    items.forEach((item) => {
      if (document.getElementById(item.id)) {
        return;
      }

      for (let index = cursor; index < headings.length; index += 1) {
        const heading = headings[index];

        if (heading.tagName !== `H${item.level}` || normalize(heading.textContent) !== item.text) {
          continue;
        }

        if (heading.id) {
          // Keep the existing id and point the links to it.
          document.querySelectorAll(`a[href="#${CSS.escape(item.id)}"]`).forEach((link) => {
            link.setAttribute('href', `#${heading.id}`);
          });
        } else {
          heading.id = item.id;
        }

        found += 1;
        cursor = index + 1;
        break;
      }
    });

    return found > 0;
  };

  const scrollToTarget = (target, smooth) => {
    target.scrollIntoView({ behavior: smooth && !reducedMotion ? 'smooth' : 'auto', block: 'start' });

    // Move the focus for keyboard and screen reader users without scrolling again.
    if (!target.hasAttribute('tabindex')) {
      target.setAttribute('tabindex', '-1');
    }

    target.focus({ preventScroll: true });
  };

  const goTo = (link, smooth) => {
    const target = targetOf(link);

    if (!target) {
      return false;
    }

    scrollToTarget(target, smooth);

    if (window.history && window.history.pushState) {
      window.history.pushState(null, '', link.getAttribute('href'));
    }

    return true;
  };

  const isPlainClick = (event) => event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey;


  /**
   * "Back to index" links in the content: one listener for the whole page.
   */
  const bindContentLinks = () => {
    if (document.documentElement.hasAttribute('data-pixindex-bound')) {
      return;
    }

    document.documentElement.setAttribute('data-pixindex-bound', '');
    document.addEventListener('click', (event) => {
      const link = event.target.closest('.pixindex-back a[href^="#"]');

      if (!link || event.defaultPrevented || !isPlainClick(event) || link.closest('.pixindex')) {
        return;
      }

      if (goTo(link, contentSettings.smooth)) {
        event.preventDefault();
      }
    });
  };

  /**
   * Closes the collapsible index on small screens ("collapsible on mobile").
   */
  const collapseOnMobile = (root, config) => {
    if (!config.collapseMobile || desktopQuery(config.breakpoint).matches) {
      return;
    }

    const details = root.querySelector('.pixindex-details');

    if (details) {
      details.open = false;

      return;
    }

    const toggle = root.querySelector('.pixindex-toggle');
    const body = root.querySelector('.pixindex-body');

    if (!toggle || !body) {
      return;
    }

    toggle.setAttribute('aria-expanded', 'false');

    if (config.framework === 'bootstrap5') {
      body.classList.remove('show');
      toggle.classList.add('collapsed');
    } else {
      body.hidden = true;
    }
  };

  /**
   * Mobile button and panel: the list is cloned into the panel, both are moved to
   * <body> (no stacking or overflow issues) and the inline index is hidden below
   * the breakpoint.
   *
   * @returns {HTMLAnchorElement[]} Links of the panel.
   */
  const setupPanel = (root, config) => {
    const button = root.querySelector('.pixindex-fab');
    const panel = root.querySelector('[data-pixindex-panel]');
    const list = root.querySelector('.pixindex-body > .pixindex-list');
    const panelBody = panel && panel.querySelector('.pixindex-panel-body');

    if (!config.panel || !button || !panel || !list || !panelBody) {
      return [];
    }

    const copy = list.cloneNode(true);

    // Ids must stay unique: rename the ids of the copy and the references to them.
    copy.querySelectorAll('[id]').forEach((element) => {
      element.id += '-panel';
    });

    copy.querySelectorAll('[aria-controls]').forEach((element) => {
      element.setAttribute('aria-controls', `${element.getAttribute('aria-controls')}-panel`);
    });

    panelBody.appendChild(copy);
    document.body.append(button, panel);

    // The panel lives outside the index: give it the same list behaviours.
    Array.from(root.classList)
      .filter((name) => name === 'pixindex--compact' || name.startsWith('pixindex--active-'))
      .forEach((name) => panel.classList.add(name));

    panel.style.setProperty('--pixindex-text', getComputedStyle(panel).color);

    const isDialog = panel.tagName === 'DIALOG';

    const close = () => {
      if (isDialog) {
        if (panel.open) {
          panel.close();
        }

        return;
      }

      const closeButton = panel.querySelector('[data-pixindex-close]');

      if (closeButton) {
        closeButton.click();
      }
    };

    if (isDialog) {
      button.addEventListener('click', () => panel.showModal());
      panel.addEventListener('click', (event) => {
        // Close on the close button and on the backdrop.
        if (event.target === panel || event.target.closest('[data-pixindex-close]')) {
          panel.close();
        }
      });
    }

    panel.addEventListener('click', (event) => {
      const link = event.target.closest('a.pixindex-link[href^="#"]');

      if (!link || !isPlainClick(event)) {
        return;
      }

      event.preventDefault();
      close();

      // Bootstrap and UIkit animate the panel and lock the page scroll meanwhile.
      window.setTimeout(() => goTo(link, config.smooth), isDialog ? 0 : 350);
    });

    // Searched after the button and the panel left the index.
    const wrapper = findWrapper(root);
    const query = desktopQuery(config.breakpoint);

    const apply = () => {
      const mobile = !query.matches;

      button.hidden = !mobile;
      wrapper.classList.toggle('pixindex-inline-hidden', mobile);

      if (!mobile) {
        close();
      }
    };

    apply();
    onQueryChange(query, apply);

    return Array.from(panel.querySelectorAll('a.pixindex-link[href^="#"]'));
  };

  /**
   * Duration of the open/close animation, read from --pixindex-duration (default 300 ms).
   */
  const animationDuration = (element) => {
    if (reducedMotion) {
      return 0;
    }

    const value = getComputedStyle(element).getPropertyValue('--pixindex-duration').trim();
    const number = parseFloat(value);

    if (Number.isNaN(number)) {
      return 300;
    }

    return value.endsWith('ms') ? number : number * 1000;
  };

  const animations = new WeakMap();

  /**
   * Opens or closes the sub-list of an entry with a height animation.
   */
  const animateBranch = (item, open, duration) => {
    const list = item.querySelector(':scope > .pixindex-list');
    const running = animations.get(item);

    // A new request ends the running animation at once.
    if (running) {
      running();
    }

    if (!list || duration <= 0) {
      item.classList.toggle('pixindex-item--open', open);

      return;
    }

    if (open) {
      item.classList.add('pixindex-item--open');
    }

    const from = open ? 0 : list.offsetHeight;
    const to = open ? list.scrollHeight : 0;

    list.style.height = `${from}px`;
    list.style.opacity = open ? '0' : '1';
    list.classList.add('pixindex-list--animating');

    // Force the start values before the transition begins.
    list.getBoundingClientRect();

    list.style.height = `${to}px`;
    list.style.opacity = open ? '1' : '0';

    let timer = 0;

    const finish = () => {
      window.clearTimeout(timer);
      list.removeEventListener('transitionend', onEnd);
      list.classList.remove('pixindex-list--animating');
      list.style.removeProperty('height');
      list.style.removeProperty('opacity');
      animations.delete(item);

      if (!open) {
        item.classList.remove('pixindex-item--open');
      }
    };

    const onEnd = (event) => {
      if (event.target === list && event.propertyName === 'height') {
        finish();
      }
    };

    list.addEventListener('transitionend', onEnd);
    timer = window.setTimeout(finish, duration + 100);
    animations.set(item, finish);
  };

  /**
   * Collapsible sub-levels of one list (inline index or panel copy).
   *
   * Branches start closed. The branch of the current section opens by itself and
   * closes when the reader moves on; branches opened by hand stay open until they
   * are closed by hand. Opening and closing are animated.
   */
  const setupBranches = (container) => {
    const parents = Array.from(container.querySelectorAll('.pixindex-item--parent'));
    const duration = animationDuration(container);
    const state = new Map();
    const manual = new Set();
    const suppressed = new Set();
    let automatic = [];
    let followed = false;

    const refresh = (animate) => {
      parents.forEach((item) => {
        const open = !suppressed.has(item) && (manual.has(item) || automatic.includes(item));
        const toggle = item.querySelector(':scope > .pixindex-branch-toggle');

        if (toggle) {
          toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        if (state.get(item) === open) {
          return;
        }

        state.set(item, open);

        // A branch inside a closed parent is not visible: no animation needed.
        const visible = animate && item.offsetParent !== null;

        animateBranch(item, open, visible ? duration : 0);
      });
    };

    parents.forEach((item) => {
      const toggle = item.querySelector(':scope > .pixindex-branch-toggle');

      if (!toggle) {
        return;
      }

      toggle.hidden = false;
      toggle.addEventListener('click', () => {
        if (state.get(item)) {
          manual.delete(item);

          // Closing the current branch by hand wins until the section changes.
          if (automatic.includes(item)) {
            suppressed.add(item);
          }
        } else {
          manual.add(item);
          suppressed.delete(item);
        }

        refresh(true);
      });
    });

    refresh(false);

    return {
      follow(activeLinks) {
        const link = activeLinks.find((candidate) => container.contains(candidate));

        automatic = [];
        suppressed.clear();

        for (let item = link ? link.closest('li') : null; item && container.contains(item); item = item.parentElement.closest('li')) {
          automatic.push(item);
        }

        // The first call happens on page load: the current branch is simply shown open.
        refresh(followed);
        followed = true;
      },
    };
  };

  /**
   * Tracks the section in view: highlights its entries and opens its branch.
   */
  const bindActiveSection = (links, config, branches) => {
    const targets = [];
    const linksByTarget = new Map();

    links.forEach((link) => {
      const target = targetOf(link);

      if (!target) {
        return;
      }

      if (!linksByTarget.has(target)) {
        linksByTarget.set(target, []);
        targets.push(target);
      }

      linksByTarget.get(target).push(link);
    });

    if (!targets.length) {
      return;
    }

    const linkClasses = (config.activeLinkClass || '').split(' ').filter(Boolean);
    const itemClasses = (config.activeItemClass || '').split(' ').filter(Boolean);
    let current = -1;

    const mark = (link, on) => {
      const item = link.closest('li');

      if (config.spy) {
        link.classList.toggle('pixindex-active', on);
        linkClasses.forEach((name) => link.classList.toggle(name, on));

        if (on) {
          link.setAttribute('aria-current', 'location');
        } else {
          link.removeAttribute('aria-current');
        }

        if (item) {
          item.classList.toggle('pixindex-item--active', on);
          itemClasses.forEach((name) => item.classList.toggle(name, on));
        }
      }
    };

    const setActive = (index) => {
      if (index === current) {
        return;
      }

      if (current >= 0) {
        linksByTarget.get(targets[current]).forEach((link) => mark(link, false));
      }

      current = index;

      const activeLinks = current >= 0 ? linksByTarget.get(targets[current]) : [];

      activeLinks.forEach((link) => mark(link, true));

      // Open the branch of the current section, close the ones left behind.
      branches.forEach((branch) => branch.follow(activeLinks));
    };

    const atBottom = () => window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2;

    // The current section is the last heading that reached the top of the viewport
    // (below the offset). Before the first one gets there, it counts as current as
    // soon as it is in the upper part of the screen.
    const measure = () => {
      if (atBottom()) {
        return targets.length - 1;
      }

      const limit = (config.offset || 0) + 10;
      let active = -1;

      targets.forEach((target, index) => {
        if (target.getBoundingClientRect().top - limit <= 0) {
          active = index;
        }
      });

      if (active === -1 && targets[0].getBoundingClientRect().top < window.innerHeight * 0.4) {
        active = 0;
      }

      return active;
    };

    setActive(measure());

    let ticking = false;

    const onScroll = (callback) => {
      window.addEventListener('scroll', () => {
        if (!ticking) {
          ticking = true;
          window.requestAnimationFrame(() => {
            ticking = false;
            callback();
          });
        }
      }, { passive: true });
    };

    if (!('IntersectionObserver' in window)) {
      onScroll(() => setActive(measure()));

      return;
    }

    // The observer only says when to measure: it fires when a heading crosses the
    // top line (below the offset) or the 40% line of the viewport, instead of
    // measuring on every scroll event.
    const observer = new IntersectionObserver(() => setActive(measure()), {
      rootMargin: `-${config.offset || 0}px 0px -60% 0px`,
    });

    targets.forEach((target) => observer.observe(target));

    let settle = 0;

    onScroll(() => {
      // The last sections may never reach the top: activate the last one at the bottom.
      if (atBottom()) {
        setActive(targets.length - 1);
      }

      // One more measure when scrolling stops: smooth scrolling can end with a
      // heading exactly on the line, where the observer does not fire again.
      window.clearTimeout(settle);
      settle = window.setTimeout(() => setActive(measure()), 120);
    });
  };

  /**
   * Makes the host sticky on screens at least as wide as the breakpoint.
   */
  const bindSticky = (host, config) => {
    const query = desktopQuery(config.breakpoint);
    const apply = () => host.classList.toggle('pixindex-sticky-host', query.matches);

    host.style.setProperty('--pixindex-sticky-top', `${config.stickyTop || 0}px`);
    apply();
    onQueryChange(query, apply);
  };

  const init = (root) => {
    if (initialised.has(root)) {
      return;
    }

    initialised.add(root);

    const config = readConfig(root);

    // Theme text color, used to tint the current row and the hover state.
    root.style.setProperty('--pixindex-text', getComputedStyle(root).color);


    contentSettings.smooth = config.smooth !== false;

    // An index made for another page (progressive cache) is hidden rather than shown wrong.
    if (!applyFallback(config)) {
      const wrapper = findWrapper(root);

      wrapper.hidden = true;
      wrapper.classList.add('pixindex-inline-hidden');

      return;
    }

    collapseOnMobile(root, config);

    const links = Array.from(root.querySelectorAll('a.pixindex-link[href^="#"]'));

    // Keep the headings visible below fixed headers, also for direct hash links.
    if (config.offset) {
      links.forEach((link) => {
        const target = targetOf(link);

        if (target) {
          target.style.scrollMarginTop = `${config.offset}px`;
        }
      });
    }

    root.addEventListener('click', (event) => {
      const link = event.target.closest('a.pixindex-link[href^="#"]');

      if (link && !event.defaultPrevented && isPlainClick(event) && goTo(link, config.smooth)) {
        event.preventDefault();
      }
    });

    bindContentLinks();

    const panelLinks = setupPanel(root, config);

    if (config.spy || config.compact) {
      const containers = config.compact
        ? [root.querySelector('.pixindex-body'), panelLinks.length ? panelLinks[0].closest('.pixindex-panel-body') : null].filter(Boolean)
        : [];

      bindActiveSection(links.concat(panelLinks), config, containers.map(setupBranches));
    }

    if (config.sticky) {
      bindSticky(findHost(root), config);
    }

    // Compact sub-levels are hidden only from now on: without the script everything stays visible.
    root.setAttribute('data-pixindex-ready', '');
    panelLinks.forEach((link) => link.closest('.pixindex').setAttribute('data-pixindex-ready', ''));
  };

  const start = () => document.querySelectorAll('.pixindex[data-pixindex]').forEach(init);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
