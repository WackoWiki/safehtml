# SafeHTML

**SafeHTML** is a defensive HTML filter for PHP that strips down all
potentially dangerous content within HTML to protect against **XSS**
(Cross-Site Scripting) and other code-injection attacks.

It is a continuation of the original `HTML_Safe` parser by Roman Ivanov,
maintained by the [WackoWiki](https://wackowiki.org) project.

---

## Features

- Removes dangerous tags: `<script>`, `<style>`, `<iframe>`, `<object>`,
  `<embed>`, `<applet>`, `<base>`, `<link>`, `<meta>`, etc.
- Removes dangerous attributes: any `on*` event handler, `data-*`, `id`,
  `name`, `dynsrc`, etc.
- Filters URL protocols in attributes such as `href`, `src`, `action` —
  defaults to a **whitelist** (`http`, `https`, `mailto`, `ftp`, …), with
  optional **blacklist** mode.
- Strips dangerous CSS keywords from inline `style=""` attributes
  (`expression`, `behavior`, `moz-binding`, …).
- Normalises **UTF-7** obfuscation tricks used by spammers.
- Escapes stray `<` characters that do not start a real tag.
- Balances unclosed / stray tags to produce well-formed output.
- Pure PHP, no extensions required.

---

## Requirements

- PHP **8.1** or newer
- [`wackowiki/htmlsax3`](https://github.com/WackoWiki/htmlsax3) (installed
  automatically via Composer)

---

## Installation

```bash
composer require wackowiki/safehtml
```

---

## Quick Start

```php
<?php

require 'vendor/autoload.php';

use SafeHTML\SafeHTML;

$parser  = new SafeHTML();
$unsafe  = '<p>Hello <script>alert(1)</script>'
         . '<a href="javascript:bad()">click</a>'
         . '<img src="http://example.com/x.png" onerror="bad()"></p>';

$clean = $parser->parse($unsafe);

echo $clean;
// <p>Hello <a>click</a><img src="http://example.com/x.png" /></p>
```

---

## Configuration

All filtering behaviour is controlled by public properties on the
`SafeHTML` class. Override only what you need:

```php
$parser = new SafeHTML();

// Switch to blacklist mode (block only known-bad protocols)
$parser->protocolFiltering = \SafeHTML\ProtocolFilterMode::BLACKLIST;

// Add to or replace the list of dangerous tags
$parser->deleteTags[] = 'marquee';

// Allow specific otherwise-forbidden tags
$parser->setAllowTags(['body']);
```

### Protocol filtering modes

| Mode        | Description                                        |
|-------------|----------------------------------------------------|
| `WHITELIST` | **(default)** Allow only listed `whiteProtocols`.  |
| `BLACKLIST` | Allow everything except `blackProtocols`.          |

### Built-in whitelisted protocols

`ed2k`, `file`, `ftp`, `gopher`, `http`, `https`, `irc`, `mailto`,
`news`, `nntp`, `telnet`, `webcal`, `xmpp`, `callto`

### Built-in blacklisted protocols

`about`, `chrome`, `data`, `disk`, `hcp`, `help`, `javascript`,
`livescript`, `lynxcgi`, `lynxexec`, `ms-help`, `ms-its`, `mhtml`,
`mocha`, `opera`, `res`, `resource`, `shell`, `vbscript`,
`view-source`, `vnd.ms.radio`, `wysiwyg`

---

## API

### `SafeHTML::parse(string $doc): string`

Main entry point. Parses `$doc` and returns the sanitised XHTML.

### `SafeHTML::setAllowTags(array $tags): void`

Whitelist a set of otherwise-forbidden tags.

### `SafeHTML::getAllowTags(): array`

Returns the currently allowed tags.

### `SafeHTML::resetAllowTags(): void`

Clears the allowed-tags list.

### `SafeHTML::getXHTML(): string`

Returns the accumulated output and closes any remaining open tags.

### `SafeHTML::clear(): void`

Resets internal state so the parser instance can be reused.

### `ProtocolFilterMode` (enum)

| Constant         | Value       |
|------------------|-------------|
| `BLACKLIST`      | `'black'`   |
| `WHITELIST`      | `'white'`   |

---

## Running Tests

```bash
composer install
composer test
```

Static analysis:

```bash
composer stan
```

---

## Security Notes

SafeHTML is a **defence-in-depth** layer. It is not a substitute for
context-aware output encoding (`htmlspecialchars()`, etc.). For modern
PHP applications, prefer using a dedicated HTML sanitiser like
[HTML Purifier](https://htmlpurifier.org/) or
[DOMDocument-based](https://github.com/ezyang/htmlpurifier) solutions
when you need full HTML5 support.

SafeHTML still excels in legacy code-bases and as a fast, dependency-light
first-pass filter where you control the input format.

---

## License

This project is licensed under the **BSD 3-Clause License** — see the
[LICENSE](LICENSE) file for details.

---

## Credits

- **Roman Ivanov** — original author
- **Miguel Vazquez Gocobachi** — maintainer
- **WackoWiki Team** — current maintainers
- HTMLSax3 parser by Harry Fuecks
