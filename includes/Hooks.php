<?php
/**
 * Per-request adjustments to output the parser has already produced.
 *
 * Every room says something about where you can go next, and the two people who can change
 * what it says should be able to get at the editor from the room itself rather than by
 * hand-building a Special:CastleNavigation URL. A room with no entry at all says "There is
 * nowhere to go from here. Tell The Castle Workers to get busy!" — fine for a visitor, and
 * useless for the two people who actually are The Castle Workers.
 *
 * That cannot be decided while parsing. Parser output is shared between users, so a tag hook
 * that emitted a sysop-only link would have that link cached and served to everyone who came
 * afterwards — or, if a reader parsed the page first, sysops would never see it. Splitting the
 * parser cache per user would work and is the wrong trade: it multiplies the cache and brings
 * back exactly the staleness the JSON data page was introduced to remove.
 *
 * So the renderer leaves an empty marker in the cached HTML and this hook resolves it per
 * request, after the cache and before the skin. Core solves user-dependent section-edit links
 * the same way. The net effect for a reader is output identical to before the marker existed,
 * because the marker is removed rather than merely hidden.
 */

namespace MediaWiki\Extension\NavigationForPagesAsRooms;

use MediaWiki\Html\Html;
use MediaWiki\Output\Hook\OutputPageBeforeHTMLHook;
use MediaWiki\SpecialPage\SpecialPage;

class Hooks implements OutputPageBeforeHTMLHook {

	/**
	 * Cheap test for "is there anything here for us", run against every page view on the wiki.
	 * Only a handful of pages carry the marker, so this must stay a plain substring search.
	 */
	private const MARKER_HINT = 'nfpar-navmark';

	/**
	 * The marker exactly as NavigationForPagesAsRooms::renderNavigationLine() writes it.
	 * Html::element() emits attributes in the order given, so this shape is deterministic —
	 * but the two must be changed together.
	 */
	private const MARKER_RE =
		'#<span class="nfpar-navmark" data-nfpar-room="([^"]*)" data-nfpar-state="([a-z]+)"></span>#';

	/**
	 * How each marker state reads to a sysop: CSS class, then message key. A state the
	 * renderer knows about and this does not would silently lose its link, so treat an
	 * unlisted state as "no entry" — the wording that invites setting the room up.
	 */
	private const STATES = [
		'room' => [ 'nfpar-editnav', 'castlenavigation-editnav' ],
		'noentry' => [ 'nfpar-fixit', 'castlenavigation-fixit' ],
	];

	/**
	 * @param \MediaWiki\Output\OutputPage $out
	 * @param string &$text
	 */
	public function onOutputPageBeforeHTML( $out, &$text ) {
		if ( !str_contains( $text, self::MARKER_HINT ) ) {
			return;
		}

		// 'editinterface' rather than a right of our own: it is what already guards
		// MediaWiki:Castle-navigation.json, so the people offered the link are exactly the
		// people whose save would be accepted. One right, no second list to keep in sync.
		if ( !$out->getAuthority()->isAllowed( 'editinterface' ) ) {
			$text = preg_replace( self::MARKER_RE, '', $text );
			return;
		}

		$text = preg_replace_callback(
			self::MARKER_RE,
			static function ( array $m ) use ( $out ) {
				// The key went through Html::element() into an attribute, so it is escaped.
				$room = htmlspecialchars_decode( $m[1], ENT_QUOTES );
				$target = SpecialPage::getTitleFor( 'CastleNavigation', $room );
				[ $class, $message ] = self::STATES[$m[2]] ?? self::STATES['noentry'];

				return ' ' . Html::rawElement( 'span', [ 'class' => $class ],
					Html::element( 'a',
						[ 'href' => $target->getLocalURL() ],
						$out->msg( $message )->text()
					)
				);
			},
			$text
		);
	}
}
