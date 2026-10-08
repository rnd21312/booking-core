<?php

declare(strict_types=1);

namespace Suntourz\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Suntourz\Core\Domain\BookingCode;

final class BookingCodeTest extends TestCase {

	public function test_generated_codes_are_valid_and_avoid_lookalikes(): void {
		for ( $i = 0; $i < 200; $i++ ) {
			$code = BookingCode::generate();
			$this->assertTrue( BookingCode::is_valid( $code ), $code );
			$this->assertDoesNotMatchRegularExpression( '/[01OI]/', substr( $code, 4 ) );
		}
	}

	public function test_codes_are_not_repeated_in_a_small_sample(): void {
		$codes = array_map( static fn (): string => BookingCode::generate(), range( 1, 300 ) );

		$this->assertCount( 300, array_unique( $codes ) );
	}

	public function test_rejects_malformed_codes(): void {
		foreach ( array( '', 'STZ-', 'STZ-ABC', 'XYZ-ABCDEF', 'STZ-abcdef', 'STZ-ABCDE0', "STZ-ABCDEF\n" ) as $bad ) {
			$this->assertFalse( BookingCode::is_valid( $bad ), $bad );
		}
	}
}
