<?php
/**
 * Per-request adjustments to output the parser has already produced.
 *
 * A room with no entry in the navigation map tells the reader "There is nowhere to go from
 * here. Tell The Castle Workers to get busy!" — which is fine for a visitor and useless for
 * the two people who actually are The Castle Workers. They should get a link to the editor.
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
	private const MARKER_HINT = 'nfpar-noentry';

	/**
	 * The marker exactly as NavigationForPagesAsRooms::renderNavigationLine() writes it.
	 * Html::element() emits attributes in the order given, so this shape is deterministic —
	 * but the two must be changed together.
	 */
	private const MARKER_RE = '#<span class="nfpar-noentry" data-nfpar-room="([^"]*)"></span>#';

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

				return ' ' . Html::rawElement( 'span', [ 'class' => 'nfpar-fixit' ],
					Html::element( 'a',
						[ 'href' => $target->getLocalURL() ],
						$out->msg( 'castlenavigation-fixit' )->text()
					)
				);
			},
			$text
		);
	}
}
