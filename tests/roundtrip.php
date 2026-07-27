<?php
/**
 * Proves NavigationSlots can carry the real navigation data without loss.
 *
 * For every entry in the map: parse it into prose + slots, rebuild it, and require the
 * result to be byte-identical to the original. Anything that fails here is data the slot
 * model cannot represent — a finding about the design, not a test to relax.
 *
 * Standalone on purpose: no MediaWiki bootstrap, so it runs anywhere PHP does.
 *   php tests/roundtrip.php
 */

require __DIR__ . '/../includes/NavigationSlots.php';
require __DIR__ . '/../NavigationForPagesAsRooms.php';

use MediaWiki\Extension\NavigationForPagesAsRooms\NavigationSlots;

$method = new ReflectionMethod( 'NavigationForPagesAsRooms', 'getNavigationMap' );
$method->setAccessible( true );
$map = $method->invoke( null );

$failures = [];
$slotTotal = 0;

foreach ( $map as $room => $wikitext ) {
	$parsed = NavigationSlots::parse( $wikitext );
	$rebuilt = NavigationSlots::build( $parsed['prose'], $parsed['slots'] );
	$slotTotal += count( $parsed['slots'] );

	if ( $rebuilt !== $wikitext ) {
		$failures[] = [ 'room' => $room, 'want' => $wikitext, 'got' => $rebuilt ];
	}
}

printf( "rooms checked : %d\n", count( $map ) );
printf( "slots parsed  : %d\n", $slotTotal );
printf( "round-trip    : %d ok, %d failed\n\n", count( $map ) - count( $failures ), count( $failures ) );

foreach ( $failures as $f ) {
	echo "FAIL {$f['room']}\n  want: {$f['want']}\n  got:  {$f['got']}\n\n";
}

// A map that parsed zero slots would round-trip trivially and prove nothing.
if ( $slotTotal === 0 ) {
	echo "ERROR: no slots parsed at all — the round-trip result is meaningless.\n";
	exit( 1 );
}

exit( $failures ? 1 : 0 );
