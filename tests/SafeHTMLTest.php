<?php
/**
 * Unit tests for the SafeHTML class.
 *
 * @category	HTML
 * @package		SafeHTML
 * @author		WackoWiki Team
 * @copyright	2004-2024 WackoWiki Team
 * @license		BSD-3-Clause
 */

declare(strict_types=1);

namespace SafeHTML\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SafeHTML\ProtocolFilterMode;
use SafeHTML\SafeHTML;

#[CoversClass(SafeHTML::class)]
final class SafeHTMLTest extends TestCase
{
	private SafeHTML $parser;

	protected function setUp(): void
	{
		$this->parser = new SafeHTML();
	}

	// ---------------------------------------------------------------------
	// Basic parsing
	// ---------------------------------------------------------------------

	public function testEmptyStringReturnsEmpty(): void
	{
		self::assertSame('', $this->parser->parse(''));
	}

	public function testSimpleTextIsPreserved(): void
	{
		self::assertSame('Hello, world!', $this->parser->parse('Hello, world!'));
	}

	public function testNewlinesAndWhitespaceArePreserved(): void
	{
		self::assertSame("Line1\nLine2", $this->parser->parse("Line1\nLine2"));
	}

	// ---------------------------------------------------------------------
	// Allowed / balanced tags
	// ---------------------------------------------------------------------

	public function testSimpleParagraph(): void
	{
		self::assertSame('<p>Hello</p>', $this->parser->parse('<p>Hello</p>'));
	}

	public function testBoldAndItalic(): void
	{
		self::assertSame(
			'<b>bold<i>italic</i></b>',
			$this->parser->parse('<b>bold<i>italic</i></b>'),
		);
	}

	public function testNestedLists(): void
	{
		$input  = '<ul><li>One<ul><li>Sub</li></ul></li><li>Two</li></ul>';
		$result = $this->parser->parse($input);
		self::assertStringContainsString('<ul>', $result);
		self::assertStringContainsString('<li>One', $result);
		self::assertStringContainsString('<li>Sub</li>', $result);
		self::assertStringContainsString('<li>Two</li>', $result);
	}

	public function testTableStructure(): void
	{
		$result = $this->parser->parse('<table><tr><td>Cell</td></tr></table>');
		self::assertStringContainsString('<table>', $result);
		self::assertStringContainsString('<tr>', $result);
		self::assertStringContainsString('<td>Cell</td>', $result);
	}

	public function testSingleTagsAreSelfClosed(): void
	{
		self::assertSame(
			'<br /><img src="/a.png" />',
			$this->parser->parse('<br><img src="/a.png">'),
		);
	}

	// ---------------------------------------------------------------------
	// Dangerous tags removed
	// ---------------------------------------------------------------------

	public function testScriptTagAndContentRemoved(): void
	{
		$input  = '<p>safe<script>alert(1)</script></p>';
		$result = $this->parser->parse($input);
		self::assertStringNotContainsString('script', $result);
		self::assertStringNotContainsString('alert', $result);
		self::assertStringContainsString('<p>safe</p>', $result);
	}

	public function testIframeRemoved(): void
	{
		$result = $this->parser->parse('<iframe src="evil.html"></iframe>');
		self::assertStringNotContainsString('iframe', $result);
		self::assertStringNotContainsString('evil.html', $result);
	}

	public function testStyleTagAndContentRemoved(): void
	{
		$result = $this->parser->parse('<style>body { color: red }</style>');
		self::assertStringNotContainsString('style', $result);
		self::assertStringNotContainsString('color: red', $result);
	}

	public function testObjectAndEmbedRemoved(): void
	{
		$result = $this->parser->parse('<object data="x"></object><embed src="y">');
		self::assertStringNotContainsString('object', $result);
		self::assertStringNotContainsString('embed', $result);
	}

	// ---------------------------------------------------------------------
	// Dangerous attributes
	// ---------------------------------------------------------------------

	public function testOnclickAttributeRemoved(): void
	{
		$result = $this->parser->parse('<a href="/x" onclick="bad()">link</a>');
		self::assertStringNotContainsString('onclick', $result);
		self::assertStringContainsString('href="/x"', $result);
		self::assertStringContainsString('link</a>', $result);
	}

	public function testDataAttributeRemoved(): void
	{
		$result = $this->parser->parse('<div data-foo="bar">x</div>');
		self::assertStringNotContainsString('data-foo', $result);
		self::assertStringContainsString('x', $result);
	}

	public function testNameAndIdAttributesRemoved(): void
	{
		$result = $this->parser->parse('<div id="x" name="y">z</div>');
		self::assertStringNotContainsString('id="x"', $result);
		self::assertStringNotContainsString('name="y"', $result);
		self::assertStringContainsString('z', $result);
	}

	// ---------------------------------------------------------------------
	// Protocol (URL) filtering
	// ---------------------------------------------------------------------

	public function testWhitelistAllowsHttp(): void
	{
		$this->parser->protocolFiltering = ProtocolFilterMode::WHITELIST;
		$result = $this->parser->parse('<a href="http://example.com">ok</a>');
		self::assertStringContainsString('href="http://example.com"', $result);
	}

	public function testWhitelistBlocksJavascript(): void
	{
		$this->parser->protocolFiltering = ProtocolFilterMode::WHITELIST;
		$result = $this->parser->parse('<a href="javascript:alert(1)">x</a>');
		self::assertStringNotContainsString('href=', $result);
		self::assertStringNotContainsString('javascript', $result);
	}

	public function testBlacklistBlocksJavascript(): void
	{
		$this->parser->protocolFiltering = ProtocolFilterMode::BLACKLIST;
		$result = $this->parser->parse('<a href="javascript:alert(1)">x</a>');
		self::assertStringNotContainsString('javascript', $result);
	}

	public function testBlacklistAllowsHttp(): void
	{
		$this->parser->protocolFiltering = ProtocolFilterMode::BLACKLIST;
		$result = $this->parser->parse('<a href="http://example.com">ok</a>');
		self::assertStringContainsString('http://example.com', $result);
	}

	public function testObfuscatedJavascriptBlocked(): void
	{
		$this->parser->protocolFiltering = ProtocolFilterMode::BLACKLIST;
		$result = $this->parser->parse("<a href=\"j\tav\ta\nscript:alert(1)\">x</a>");
		self::assertStringNotContainsString('alert', $result);
	}

	public function testVbscriptBlocked(): void
	{
		$this->parser->protocolFiltering = ProtocolFilterMode::BLACKLIST;
		$result = $this->parser->parse('<a href="vbscript:msgbox(1)">x</a>');
		self::assertStringNotContainsString('vbscript', $result);
	}

	public function testDataUriBlocked(): void
	{
		$this->parser->protocolFiltering = ProtocolFilterMode::BLACKLIST;
		$result = $this->parser->parse('<a href="data:text/html,<script>1</script>">x</a>');
		self::assertStringNotContainsString('data:', $result);
	}

	// ---------------------------------------------------------------------
	// CSS sanitisation
	// ---------------------------------------------------------------------

	public function testExpressionInStyleBlocked(): void
	{
		$result = $this->parser->parse('<div style="width: expression(alert(1))">x</div>');
		self::assertStringNotContainsString('expression', $result);
	}

	public function testBehaviorInStyleBlocked(): void
	{
		$result = $this->parser->parse('<div style="behavior:url(x.htc)">x</div>');
		self::assertStringNotContainsString('behavior', $result);
	}

	public function testSafeStyleIsPreserved(): void
	{
		$result = $this->parser->parse('<div style="color: red">x</div>');
		self::assertStringContainsString('color: red', $result);
	}

	// ---------------------------------------------------------------------
	// Tag balancing
	// ---------------------------------------------------------------------

	public function testUnclosedTagsAreClosed(): void
	{
		$result = $this->parser->parse('<p>Hello');
		self::assertStringContainsString('</p>', $result);
	}

	public function testStrayClosingTagIsIgnored(): void
	{
		$result = $this->parser->parse('Hello</p>');
		self::assertSame('Hello', $result);
	}

	// ---------------------------------------------------------------------
	// XSS / injection vectors
	// ---------------------------------------------------------------------

	public function testLessThanBeforeNonTagCharacter(): void
	{
		$result = $this->parser->parse('1 < 2 and 3 > 2');
		self::assertStringContainsString('&lt;', $result);
	}

	public function testScriptInsideAttributeValue(): void
	{
		$result = $this->parser->parse('<img src="x" alt="\" onerror=\"alert(1)">');
		self::assertStringNotContainsString('onerror', $result);
	}

	public function testUtf7EncodedPayloadNormalised(): void
	{
		// +ADw-script+AD4- decodes to <script>
		$result = $this->parser->parse('+ADw-script+AD4-alert(1)+ADw-/script+AD4-');
		self::assertStringNotContainsString('alert', $result);
	}

	// ---------------------------------------------------------------------
	// allow-tags API
	// ---------------------------------------------------------------------

	public function testSetAllowTagsPermitsBody(): void
	{
		$this->parser->setAllowTags(['body']);
		$result = $this->parser->parse('<body><p>x</p></body>');
		self::assertStringContainsString('<body>', $result);
		self::assertStringContainsString('<p>x</p>', $result);
	}

	public function testResetAllowTagsRestoresDefaults(): void
	{
		$this->parser->setAllowTags(['body']);
		$this->parser->resetAllowTags();
		self::assertSame([], $this->parser->getAllowTags());
	}

	public function testGetAllowTagsReturnsCurrentValue(): void
	{
		$this->parser->setAllowTags(['div', 'p']);
		self::assertSame(['div', 'p'], $this->parser->getAllowTags());
	}

	// ---------------------------------------------------------------------
	// Misc
	// ---------------------------------------------------------------------

	public function testClearResetsInternalState(): void
	{
		$this->parser->parse('<p>first</p>');
		$this->parser->clear();
		self::assertSame('second', $this->parser->parse('second'));
	}

	public function testQuoteEscapingInAttribute(): void
	{
		$result = $this->parser->parse('<a href="a\"b">x</a>');
		self::assertStringNotContainsString('"b"', $result);
	}

	public function testParagraphClosedWhenBlockOpens(): void
	{
		$result = $this->parser->parse('<p>one<div>two</div></p>');
		// The parser should close the <p> before opening the <div>
		self::assertMatchesRegularExpression('/<p>one<\/p><div>two<\/div>/', $result);
	}
}
