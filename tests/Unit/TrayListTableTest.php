<?php
/**
 * The row actions and the status column.
 *
 * @package BlueFairy\MicrogreenTracker
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TrayListTableTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['bfmt_meta']    = [];
		$GLOBALS['bfmt_filters'] = [];
	}

	private function cell( string $column ): string {
		$item     = new stdClass();
		$item->ID = 7;

		return ( new BFMT_Tray_List_Table() )->column_default( $item, $column );
	}

	public function test_the_trash_link_asks_through_a_data_attribute_not_inline_script(): void {
		$actions = $this->cell( 'row_actions_col' );

		$this->assertStringContainsString( 'data-bfmt-confirm="Move this tray to the trash?"', $actions );
		$this->assertStringNotContainsString( 'onclick', $actions );
	}

	public function test_the_trash_link_carries_its_own_nonce(): void {
		$this->assertStringContainsString( '_wpnonce=test-bfmt_trash_tray_7', $this->cell( 'row_actions_col' ) );
	}

	public function test_the_status_column_is_a_class_styled_badge(): void {
		$this->assertSame( '<span class="bfmt-badge bfmt-badge--active">Active</span>', $this->cell( 'status' ) );
	}
}
