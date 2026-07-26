import { describe, expect, it } from 'vitest';

import { formatMoney } from './money';

describe( 'formatMoney', () => {
	it( 'formats IDR with Rp prefix and dot thousands, no fraction', () => {
		const out = formatMoney( 1_500_000, 'IDR' );
		expect( out ).toBe( 'Rp 1.500.000' );
		expect( out ).toContain( '1.500.000' );
		expect( out ).toContain( 'Rp' );
		expect( out ).not.toContain( ',' );
		expect( out ).not.toMatch( /\.00$/ );
		expect( out ).not.toContain( '1,500,000' );
	} );

	it( 'accepts lowercase currency codes', () => {
		expect( formatMoney( 1_500_000, 'idr' ) ).toBe( 'Rp 1.500.000' );
	} );

	it( 'formats negative IDR with leading minus before the symbol', () => {
		expect( formatMoney( -250_000, 'IDR' ) ).toBe( '-Rp 250.000' );
	} );

	it( 'formats zero IDR', () => {
		expect( formatMoney( 0, 'IDR' ) ).toBe( 'Rp 0' );
	} );

	it( 'formats small IDR without spurious grouping', () => {
		expect( formatMoney( 999, 'IDR' ) ).toBe( 'Rp 999' );
		expect( formatMoney( 1_000, 'IDR' ) ).toBe( 'Rp 1.000' );
	} );

	it( 'falls back to ISO code + amount for non-IDR two-decimal currencies', () => {
		// 4510 minor = 45.10 major.
		expect( formatMoney( 4510, 'USD' ) ).toBe( 'USD 45.10' );
		expect( formatMoney( -4510, 'USD' ) ).toBe( '-USD 45.10' );
	} );

	it( 'falls back for zero-decimal non-IDR (e.g. JPY)', () => {
		expect( formatMoney( 1500, 'JPY' ) ).toBe( 'JPY 1,500' );
	} );
} );
