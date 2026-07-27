<?php
/**
 * Splits a room's exit wikitext into editable prose plus a list of destinations.
 *
 * The navigation map stores each room's exits as a sentence with wikilinks baked in:
 *
 *     relax in [[the library]], go south along the [[upper nave]] of Ellis Chapel
 *     to [[Watanabe Tower:2F guard quarters|guard quarters of Watanabe Tower]].
 *
 * Editing that by hand means retyping long page titles inside brackets, which is where
 * the typos come from. This class turns it into two things a form can present as fields —
 * prose with numbered slots, and a destination per slot:
 *
 *     prose  "relax in {1}, go south along the {2} of Ellis Chapel to {3}."
 *     slots  1 => the library
 *            2 => upper nave
 *            3 => Watanabe Tower:2F guard quarters  (shown as "guard quarters of ...")
 *
 * The room's voice survives untouched; only the destinations become pickable. build() is
 * the exact inverse of parse(), so a round trip must reproduce the original byte for byte.
 */

namespace MediaWiki\Extension\NavigationForPagesAsRooms;

class NavigationSlots {

	/**
	 * Matches a single wikilink. Deliberately strict: the navigation map contains no
	 * nested links and no link carrying more than one pipe (verified across all entries),
	 * so anything more permissive would only invent failure modes.
	 */
	private const LINK_RE = '/\[\[([^\[\]|]+)(?:\|([^\[\]]*))?\]\]/';

	/**
	 * Split exit wikitext into prose-with-slots and the destinations those slots hold.
	 *
	 * @param string $wikitext One value from the navigation map.
	 * @return array{prose:string,slots:array<int,array{target:string,label:?string}>}
	 *   Slots are numbered from 1, in the order they appear. 'label' is null when the
	 *   link had no pipe, and a string (possibly empty) when it did — the distinction
	 *   matters because build() has to reproduce the original exactly.
	 */
	public static function parse( string $wikitext ): array {
		$slots = [];
		$n = 0;

		$prose = preg_replace_callback(
			self::LINK_RE,
			static function ( array $m ) use ( &$slots, &$n ) {
				$n++;
				$slots[$n] = [
					'target' => $m[1],
					// $m[2] is unset for [[foo]] but set (possibly '') for [[foo|]].
					'label' => $m[2] ?? null,
				];
				return '{' . $n . '}';
			},
			$wikitext
		);

		return [ 'prose' => $prose, 'slots' => $slots ];
	}

	/**
	 * Reassemble exit wikitext from prose-with-slots and its destinations.
	 *
	 * Slots the prose does not reference are dropped rather than appended — the prose is
	 * the authority on what the room says. Unknown slot numbers are left as literal text
	 * so a mistyped {9} is visible in the output instead of vanishing silently.
	 *
	 * @param string $prose Prose containing {1}, {2}, ... placeholders.
	 * @param array<int,array{target:string,label:?string}> $slots
	 * @return string
	 */
	public static function build( string $prose, array $slots ): string {
		return preg_replace_callback(
			'/\{(\d+)\}/',
			static function ( array $m ) use ( $slots ) {
				$i = (int)$m[1];
				if ( !isset( $slots[$i] ) ) {
					return $m[0];
				}
				$slot = $slots[$i];
				$label = $slot['label'] ?? null;
				return $label === null
					? '[[' . $slot['target'] . ']]'
					: '[[' . $slot['target'] . '|' . $label . ']]';
			},
			$prose
		);
	}

	/**
	 * The destinations a room links to, in slot order, without the display labels.
	 *
	 * @param string $wikitext One value from the navigation map.
	 * @return string[] Link targets as written, e.g. "Library:OtnoTC - Flying Plan".
	 */
	public static function targets( string $wikitext ): array {
		$parsed = self::parse( $wikitext );
		return array_values( array_map(
			static fn ( array $slot ) => $slot['target'],
			$parsed['slots']
		) );
	}
}
