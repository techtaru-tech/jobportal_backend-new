/**
 * Reads lucide-react's own icon node definitions and emits a PHP array of
 * ready-to-print SVG markup, so the Blade panel draws the exact same glyphs
 * the React panel does rather than hand-traced approximations.
 */
import { readFileSync, writeFileSync } from 'node:fs'
import { join } from 'node:path'

const ICON_DIR =
  'D:/project_folder/new_job_portal_folder/admin_panel/node_modules/lucide-react/dist/esm/icons'

// name-in-php => lucide file name
const WANTED = {
  dashboard: 'layout-dashboard',
  users: 'users',
  briefcase: 'briefcase',
  clipboard: 'clipboard-list',
  badgeCheck: 'badge-check',
  bell: 'bell',
  creditCard: 'credit-card',
  sliders: 'sliders-horizontal',
  fileText: 'file-text',
  logOut: 'log-out',
  logIn: 'log-in',
  panelClose: 'panel-left-close',
  panelOpen: 'panel-left-open',
  x: 'x',
  menu: 'menu',
  search: 'search',
  chevronDown: 'chevron-down',
  chevronRight: 'chevron-right',
  chevronLeft: 'chevron-left',
  shieldCheck: 'shield-check',
  loader: 'loader-circle',
  refresh: 'refresh-cw',
  userCheck: 'user-check',
  mapPinOff: 'map-pin-off',
  msgOff: 'message-circle-off',
  inbox: 'inbox',
  alert: 'triangle-alert',
  check: 'check',
  plus: 'plus',
  trash: 'trash-2',
  arrowUp: 'arrow-up',
  arrowDown: 'arrow-down',
}

/**
 * The icon files are ES modules exporting a `__iconNode` array literal. They
 * are parsed rather than imported because importing would pull in React.
 */
function nodesOf(file) {
  const src = readFileSync(join(ICON_DIR, `${file}.mjs`), 'utf8')
  // Single-glyph icons declare the array on one line, multi-glyph ones span
  // several — match up to the `];` that closes it either way.
  const match = src.match(/const __iconNode = (\[[\s\S]*?\]);/)
  if (!match) throw new Error(`no __iconNode in ${file}`)
  // The literal is plain JS with unquoted keys — eval it in an expression slot.
  return eval(match[1])
}

function toSvgInner(nodes) {
  return nodes
    .map(([tag, attrs]) => {
      const printed = Object.entries(attrs)
        // `key` is React bookkeeping and means nothing in raw SVG.
        .filter(([k]) => k !== 'key')
        .map(([k, v]) => {
          const name = k.replace(/[A-Z]/g, (c) => `-${c.toLowerCase()}`)
          return `${name}="${v}"`
        })
        .join(' ')
      return `<${tag} ${printed}/>`
    })
    .join('')
}

const lines = []
for (const [phpName, file] of Object.entries(WANTED)) {
  lines.push(`    '${phpName}' => '${toSvgInner(nodesOf(file)).replace(/'/g, "\\'")}',`)
}

const php = `<?php

/**
 * Lucide glyphs, generated from the same lucide-react package the React panel
 * uses (v1.32.0, ISC) — see tool/gen-icons.mjs. Each value is the *inner*
 * markup of a 24x24 lucide SVG; the wrapper in partials/icon.blade.php adds
 * the shared attributes.
 *
 * Regenerate rather than hand-editing: a traced path drifts from the real one
 * and the two panels stop looking like the same product.
 */
return [
${lines.join('\n')}
];
`

writeFileSync(process.argv[2], php)
console.log(`wrote ${Object.keys(WANTED).length} icons`)
