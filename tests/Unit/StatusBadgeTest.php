<?php
/**
 * The badge and the stylesheet that colours it.
 *
 * 1.1.0 coloured each badge with a style attribute. 1.1.1 moves the colour into
 * the enqueued admin stylesheet, and the colours still come through the
 * bfmt_status_colours filter Pro 1.4.0 answers — so the filter's values end up
 * inside a <style> element and have to be checked on the way in.
 *
 * @package BlueFairy\MicrogreenTracker
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StatusBadgeTest extends TestCase {

	protected function setUp(): void {
		bfmt_test_reset();
	}

	public function test_a_badge_carries_its_status_class_and_label(): void {
		$this->assertSame(
			'<span class="bfmt-badge bfmt-badge--harvested">Harvested</span>',
			BFMT_Status::badge( 'harvested' )
		);
	}

	public function test_a_badge_has_no_style_attribute(): void {
		foreach ( [ 'active', 'harvested', 'failed', 'germinating' ] as $status ) {
			$this->assertStringNotContainsString( 'style=', BFMT_Status::badge( $status ), $status );
		}
	}

	/**
	 * A status key an add-on returns is not trusted to be a class name.
	 */
	public function test_a_status_key_is_reduced_to_a_safe_class(): void {
		$this->assertSame( 'bfmt-badge--darkness_lifted', BFMT_Status::badge_class( 'Darkness_Lifted' ) );
		$this->assertSame( 'bfmt-badge--xonclickalert1', BFMT_Status::badge_class( 'x" onclick="alert(1)' ) );
		$this->assertSame( 'bfmt-badge--no-such', BFMT_Status::badge_class( 'No-Such' ), 'hyphens are kept' );
	}

	public function test_the_label_is_escaped_in_the_badge(): void {
		$GLOBALS['bfmt_filters']['bfmt_status_labels'] = static fn( array $labels ): array =>
			[ 'odd' => '<b>Odd</b>' ] + $labels;

		$this->assertStringContainsString( '&lt;b&gt;Odd&lt;/b&gt;', BFMT_Status::badge( 'odd' ) );
	}

	public function test_the_stylesheet_has_a_rule_for_each_built_in_status(): void {
		$css = BFMT_Status::stylesheet();

		$this->assertStringContainsString( '.bfmt-badge.bfmt-badge--active{color:#166534;background:#dcfce7}', $css );
		$this->assertStringContainsString( '.bfmt-badge.bfmt-badge--harvested{color:#1e40af;background:#dbeafe}', $css );
		$this->assertStringContainsString( '.bfmt-badge.bfmt-badge--failed{color:#991b1b;background:#fee2e2}', $css );
		$this->assertCount( 3, explode( "\n", $css ) );
	}

	/**
	 * Pro 1.4.0 adds five statuses through the filter in exactly this shape. If
	 * the stylesheet only knew the built-in three, every Pro badge would render
	 * in the neutral fallback colour.
	 */
	public function test_an_add_on_status_gets_its_own_rule(): void {
		$GLOBALS['bfmt_filters']['bfmt_status_colours'] = static fn( array $c ): array =>
			array_merge( $c, [ 'germinating' => '#854d0e;background:#fef9c3' ] );

		$this->assertStringContainsString(
			'.bfmt-badge.bfmt-badge--germinating{color:#854d0e;background:#fef9c3}',
			BFMT_Status::stylesheet()
		);
	}

	/**
	 * The value lands inside <style>. Anything that is not two hex colours is
	 * dropped, and the status falls back to the neutral colour in admin.css.
	 */
	public function test_a_colour_that_is_not_hex_is_left_out(): void {
		$GLOBALS['bfmt_filters']['bfmt_status_colours'] = static fn( array $c ): array => array_merge( $c, [
			'evil'  => '#000;background:#fff}</style><script>alert(1)</script>',
			'named' => 'red;background:blue',
			'extra' => '#000;background:#fff;position:fixed',
		] );

		$css = BFMT_Status::stylesheet();

		$this->assertStringNotContainsString( 'evil', $css );
		$this->assertStringNotContainsString( 'named', $css );
		$this->assertStringNotContainsString( 'extra', $css );
		$this->assertStringNotContainsString( '<', $css );
		$this->assertCount( 3, explode( "\n", $css ), 'the three good rules survive' );
	}

	/**
	 * Surrounding whitespace is tolerated because only the two captured colours
	 * are ever written out.
	 */
	public function test_parse_colour_accepts_short_and_long_hex(): void {
		$this->assertSame( [ 'color' => '#abc', 'background' => '#ddeeff' ], BFMT_Status::parse_colour( '#ABC;background:#DDEEFF' ) );
		$this->assertSame( [ 'color' => '#000', 'background' => '#fff' ], BFMT_Status::parse_colour( ' #000 ; background : #fff ; ' ) );
	}

	public function test_parse_colour_refuses_everything_else(): void {
		foreach ( [
			'',
			'#000',
			'#0000;background:#fff',
			'#000;color:#fff',
			'#000;background:#fff;}',
			'url(x);background:#fff',
			'x#000;background:#fff',
			'red #000;background:#fff',
		] as $bad ) {
			$this->assertNull( BFMT_Status::parse_colour( $bad ), var_export( $bad, true ) );
		}
	}

	public function test_the_built_in_colours_and_labels(): void {
		$this->assertSame( [ 'active', 'harvested', 'failed' ], array_keys( BFMT_Status::colours() ) );
		$this->assertSame( [ 'active' => 'Active', 'harvested' => 'Harvested', 'failed' => 'Failed' ], BFMT_Status::labels() );
	}
}
