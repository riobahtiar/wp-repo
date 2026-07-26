<?php
/**
 * Benchmark dashboard aggregate queries (run via WP-CLI).
 *
 * Usage:
 *   wp eval-file wp-content/plugins/wp-bizwit/scripts/benchmark-dashboard.php
 *   BW_BENCH_CLIENTS=1000 BW_BENCH_INVOICES=5000 BW_BENCH_SEED=1 \
 *     wp eval-file wp-content/plugins/wp-bizwit/scripts/benchmark-dashboard.php
 *
 * @package WP_BizWit
 */

use WP_BizWit\Database\Installer;
use WP_BizWit\Repositories\Client_Repository;
use WP_BizWit\Repositories\Invoice_Repository;
use WP_BizWit\Repositories\Stats_Repository;
use WP_BizWit\Support\Invoice_Status;
use WP_BizWit\Support\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run with: wp eval-file plugins/wp-bizwit/scripts/benchmark-dashboard.php\n" );
	exit( 1 );
}

/**
 * Resolve knobs from env (preferred for WP-CLI) or argv.
 *
 *   BW_BENCH_CLIENTS=200 BW_BENCH_INVOICES=1000 BW_BENCH_SEED=1 \
 *     wp eval-file plugins/wp-bizwit/scripts/benchmark-dashboard.php
 *
 * @return array{clients: int, invoices: int, seed: bool}
 */
function wp_bizwit_bench_args(): array {
	global $argv;

	$clients  = getenv( 'BW_BENCH_CLIENTS' );
	$invoices = getenv( 'BW_BENCH_INVOICES' );
	$seed     = getenv( 'BW_BENCH_SEED' );

	$out = array(
		'clients'  => false !== $clients ? (int) $clients : 200,
		'invoices' => false !== $invoices ? (int) $invoices : 1000,
		'seed'     => ( '1' === $seed || 'true' === $seed ),
	);

	foreach ( (array) $argv as $arg ) {
		if ( preg_match( '/^--([a-z]+)=(.+)$/', (string) $arg, $m ) ) {
			if ( 'clients' === $m[1] || 'invoices' === $m[1] ) {
				$out[ $m[1] ] = (int) $m[2];
			} elseif ( 'seed' === $m[1] ) {
				$out['seed'] = in_array( $m[2], array( '1', 'true', 'yes' ), true );
			}
		} elseif ( '--seed' === $arg ) {
			$out['seed'] = true;
		}
	}

	return $out;
}

$args         = wp_bizwit_bench_args();
$want_clients = max( 0, (int) $args['clients'] );
$want_inv     = max( 0, (int) $args['invoices'] );
$do_seed      = (bool) $args['seed'];

( new Installer() )->maybe_install();
Settings::update( array( 'tax_regime' => Settings::REGIME_NONE ) );

$clients  = new Client_Repository();
$invoices = new Invoice_Repository();
$stats    = new Stats_Repository();

if ( $do_seed && $want_clients > 0 ) {
	echo "Seeding {$want_clients} clients + {$want_inv} invoices…\n";
	$client_ids = array();
	$t0         = microtime( true );
	for ( $i = 0; $i < $want_clients; $i++ ) {
		$id = $clients->create(
			array(
				'display_name' => sprintf( 'Bench Client %05d', $i ),
				'type'         => 'company',
				'status'       => Client_Repository::STATUS_ACTIVE,
			)
		);
		if ( is_int( $id ) ) {
			$client_ids[] = $id;
		}
	}
	$n_cli = count( $client_ids );
	for ( $i = 0; $i < $want_inv && $n_cli > 0; $i++ ) {
		$cid = $client_ids[ $i % $n_cli ];
		$due = gmdate( 'Y-m-d', time() - ( ( $i % 90 ) * DAY_IN_SECONDS ) );
		$invoices->create(
			array(
				'client_id'  => $cid,
				'status'     => Invoice_Status::SENT,
				'issue_date' => gmdate( 'Y-m-d', time() - ( 100 * DAY_IN_SECONDS ) ),
				'due_date'   => $due,
				'currency'   => 'IDR',
				'items'      => array(
					array(
						'description' => 'Bench line',
						'quantity'    => '1',
						'unit_price'  => (string) ( 1000000 + ( $i * 1000 ) ),
					),
				),
			)
		);
	}
	echo sprintf( "Seed done in %.2fs\n", microtime( true ) - $t0 );
}

Stats_Repository::bust_cache();

$runs = array(
	'clients(active)'   => static fn () => $stats->clients( Client_Repository::STATUS_ACTIVE ),
	'outstanding'       => static fn () => $stats->outstanding_minor(),
	'ageing'            => static fn () => $stats->outstanding_ageing_minor(),
	'payments_month'    => static function () use ( $stats ) {
		$from = gmdate( 'Y-m-01', (int) current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$to   = gmdate( 'Y-m-t', (int) current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		return $stats->payments_between( $from, $to );
	},
	'recent_invoices'   => static fn () => $stats->recent_invoices( 5 ),
);

echo "\nDashboard aggregate timings (cold then warm cache):\n";
echo str_pad( 'query', 22 ) . str_pad( 'cold ms', 12 ) . str_pad( 'warm ms', 12 ) . "result\n";
echo str_repeat( '-', 60 ) . "\n";

foreach ( $runs as $label => $fn ) {
	Stats_Repository::bust_cache();
	$t1 = microtime( true );
	$r1 = $fn();
	$c1 = ( microtime( true ) - $t1 ) * 1000;

	$t2 = microtime( true );
	$r2 = $fn();
	$c2 = ( microtime( true ) - $t2 ) * 1000;

	$preview = is_array( $r1 ) ? 'array(' . count( $r1 ) . ')' : (string) $r1;
	echo str_pad( $label, 22 )
		. str_pad( sprintf( '%.2f', $c1 ), 12 )
		. str_pad( sprintf( '%.2f', $c2 ), 12 )
		. $preview . "\n";
}

echo "\nTarget: each cold aggregate < 500ms at 50k invoices (plan 06).\n";
echo "Warm hits should be near-zero (transient bag).\n";
